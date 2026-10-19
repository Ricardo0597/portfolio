<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\portfolio_core\Assistant\LlmChain;
use Drupal\portfolio_core\Assistant\LlmClientInterface;
use Drupal\portfolio_core\Assistant\LlmUnavailableException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests provider ordering and fallback.
 */
#[CoversClass(LlmChain::class)]
#[Group('portfolio_core')]
final class LlmChainTest extends UnitTestCase {

  use AssistantTestTrait;

  /**
   * Builds a fake client.
   *
   * @param string $id
   *   Provider id.
   * @param string|null $answer
   *   Text to return, or NULL to throw LlmUnavailableException.
   * @param bool $configured
   *   Whether the client has a key.
   */
  private function fake(string $id, ?string $answer, bool $configured = TRUE): LlmClientInterface {
    $client = $this->createMock(LlmClientInterface::class);
    $client->method('id')->willReturn($id);
    $client->method('isConfigured')->willReturn($configured);
    if ($answer === NULL) {
      $client->method('complete')->willThrowException(new LlmUnavailableException("$id down"));
    }
    else {
      $client->method('complete')->willReturn($answer);
    }
    return $client;
  }

  /**
   * Builds the chain.
   */
  private function chain(array $clients, array $providers = ['groq', 'gemini']): LlmChain {
    return new LlmChain($this->assistantConfig(['providers' => $providers]), $clients, $this->createMock(LoggerInterface::class));
  }

  /**
   * The first configured provider answers.
   */
  public function testUsesFirstConfiguredProvider(): void {
    $result = $this->chain([$this->fake('groq', 'from groq'), $this->fake('gemini', 'from gemini')])->complete('S', [], 400);
    $this->assertSame('from groq', $result->text);
    $this->assertSame('groq', $result->provider);
  }

  /**
   * An unavailable provider falls through to the next one.
   */
  public function testFallsBackWhenFirstUnavailable(): void {
    $result = $this->chain([$this->fake('groq', NULL), $this->fake('gemini', 'from gemini')])->complete('S', [], 400);
    $this->assertSame('gemini', $result->provider);
  }

  /**
   * Providers without a key are skipped.
   */
  public function testSkipsUnconfiguredProvider(): void {
    $result = $this->chain([$this->fake('groq', 'from groq', FALSE), $this->fake('gemini', 'from gemini')])->complete('S', [], 400);
    $this->assertSame('gemini', $result->provider);
  }

  /**
   * The configured order wins over registration order.
   */
  public function testRespectsConfiguredOrder(): void {
    $result = $this->chain([$this->fake('groq', 'from groq'), $this->fake('gemini', 'from gemini')], ['gemini', 'groq'])->complete('S', [], 400);
    $this->assertSame('gemini', $result->provider);
  }

  /**
   * All providers failing returns NULL.
   */
  public function testReturnsNullWhenAllFail(): void {
    $this->assertNull($this->chain([$this->fake('groq', NULL), $this->fake('gemini', NULL)])->complete('S', [], 400));
  }

}
