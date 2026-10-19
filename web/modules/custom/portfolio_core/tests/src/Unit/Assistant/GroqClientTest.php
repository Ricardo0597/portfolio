<?php
declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\portfolio_core\Assistant\GroqClient;
use Drupal\portfolio_core\Assistant\LlmUnavailableException;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Groq (OpenAI-compatible) client.
 */
#[CoversClass(GroqClient::class)]
#[Group('portfolio_core')]
final class GroqClientTest extends UnitTestCase {

  use AssistantTestTrait;

  private const MESSAGES = [
    ['role' => 'user', 'content' => 'Hi'],
    ['role' => 'assistant', 'content' => 'Hello!'],
    ['role' => 'user', 'content' => 'Is he available?'],
  ];

  /**
   * Builds a client answering with the queued responses.
   */
  private function client(array $queue, ?string $key = 'gsk_test'): GroqClient {
    return new GroqClient($this->http($queue), $this->keys(['groq' => $key]), $this->assistantConfig());
  }

  /**
   * Builds a successful chat completion response.
   */
  private function ok(string $text): Response {
    return new Response(200, [], json_encode(['choices' => [['message' => ['content' => $text]]]]));
  }

  /**
   * The request carries the model, key, system prompt and messages.
   */
  public function testSendsModelSystemAndMessages(): void {
    $this->client([$this->ok('Yes')])->complete('SYSTEM', self::MESSAGES, 400);

    $request = $this->httpHistory[0]['request'];
    $this->assertSame('https://api.groq.com/openai/v1/chat/completions', (string) $request->getUri());
    $this->assertSame('Bearer gsk_test', $request->getHeaderLine('Authorization'));
    $body = json_decode((string) $request->getBody(), TRUE);
    $this->assertSame('openai/gpt-oss-120b', $body['model']);
    $this->assertSame(400, $body['max_tokens']);
    $this->assertSame(['role' => 'system', 'content' => 'SYSTEM'], $body['messages'][0]);
    $this->assertSame(self::MESSAGES, array_slice($body['messages'], 1));
    $this->assertSame('low', $body['reasoning_effort']);
    $this->assertFalse($body['include_reasoning']);
  }

  /**
   * Non-string completion content means unavailable, not a crash.
   */
  public function testMalformedContentThrowsUnavailable(): void {
    $this->expectException(LlmUnavailableException::class);
    $this->client([new Response(200, [], json_encode(['choices' => [['message' => ['content' => ['x']]]]]))])->complete('S', self::MESSAGES, 400);
  }

  /**
   * The completion text is returned.
   */
  public function testReturnsCompletionText(): void {
    $this->assertSame('Yes, immediately.', $this->client([$this->ok('Yes, immediately.')])->complete('S', self::MESSAGES, 400));
  }

  /**
   * HTTP 429 means unavailable.
   */
  public function testRateLimitThrowsUnavailable(): void {
    $this->expectException(LlmUnavailableException::class);
    $this->client([new Response(429)])->complete('S', self::MESSAGES, 400);
  }

  /**
   * HTTP 5xx means unavailable.
   */
  public function testServerErrorThrowsUnavailable(): void {
    $this->expectException(LlmUnavailableException::class);
    $this->client([new Response(503)])->complete('S', self::MESSAGES, 400);
  }

  /**
   * A 200 without completion text means unavailable.
   */
  public function testEmptyCompletionThrowsUnavailable(): void {
    $this->expectException(LlmUnavailableException::class);
    $this->client([new Response(200, [], '{}')])->complete('S', self::MESSAGES, 400);
  }

  /**
   * Without a key the client is not configured and refuses to call.
   */
  public function testNotConfiguredWithoutKey(): void {
    $client = $this->client([], NULL);
    $this->assertFalse($client->isConfigured());
    $this->expectException(LlmUnavailableException::class);
    $client->complete('S', self::MESSAGES, 400);
  }

}
