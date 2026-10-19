<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\Core\Flood\FloodInterface;
use Drupal\portfolio_core\Assistant\AnswerParser;
use Drupal\portfolio_core\Assistant\AskRequestValidator;
use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Assistant\LlmChain;
use Drupal\portfolio_core\Assistant\LlmResult;
use Drupal\portfolio_core\Controller\AskController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests the order of checks in the ask endpoint.
 */
#[CoversClass(AskController::class)]
#[Group('portfolio_core')]
final class AskControllerTest extends UnitTestCase {

  use AssistantTestTrait;

  /**
   * Builds the controller.
   */
  private function controller(FloodInterface $flood, ?LlmResult $result = NULL, bool $enabled = TRUE): AskController {
    $context = $this->createMock(AssistantContextBuilder::class);
    $context->method('build')->willReturn('SYSTEM');
    $chain = $this->createMock(LlmChain::class);
    $chain->method('complete')->willReturn($result);
    $controller = new AskController(
      $this->assistantConfig(['enabled' => $enabled]),
      $flood,
      new AskRequestValidator(),
      $context,
      $chain,
      new AnswerParser(),
    );
    $controller->setStringTranslation($this->getStringTranslationStub());
    return $controller;
  }

  /**
   * Builds a POST request.
   */
  private function request(string $body, ?string $origin = NULL): Request {
    $request = Request::create('https://example.com/api/portfolio/ask', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.7'], $body);
    if ($origin !== NULL) {
      $request->headers->set('Origin', $origin);
    }
    return $request;
  }

  /**
   * Builds a flood mock.
   */
  private function flood(bool $allowed = TRUE): FloodInterface {
    $flood = $this->createMock(FloodInterface::class);
    $flood->method('isAllowed')->willReturn($allowed);
    return $flood;
  }

  /**
   * The kill switch turns the endpoint into a 404.
   */
  public function testDisabledReturns404(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->controller($this->flood(), NULL, FALSE)->ask($this->request('{"question":"Hi"}'));
  }

  /**
   * A foreign origin is rejected before flood is counted.
   */
  public function testForeignOriginRejectedWithoutCounting(): void {
    $flood = $this->flood();
    $flood->expects($this->never())->method('register');
    $response = $this->controller($flood)->ask($this->request('{"question":"Hi"}', 'https://evil.example'));
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Over the limit returns 429 with the friendly answer, without counting.
   */
  public function testFloodLimitReturns429(): void {
    $flood = $this->flood(FALSE);
    $flood->expects($this->never())->method('register');
    $response = $this->controller($flood)->ask($this->request('{"question":"Hi"}'));
    $this->assertSame(429, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame([['type' => 'email']], $data['actions']);
  }

  /**
   * Invalid requests return 400 and are not counted.
   */
  public function testInvalidRequestNotCounted(): void {
    $flood = $this->flood();
    $flood->expects($this->never())->method('register');
    $this->assertSame(400, $this->controller($flood)->ask($this->request('{"question":""}'))->getStatusCode());
  }

  /**
   * A valid question is counted in both windows and answered.
   */
  public function testValidQuestionCountedAndAnswered(): void {
    $flood = $this->flood();
    $flood->expects($this->exactly(2))->method('register');
    $response = $this->controller($flood, new LlmResult('Yes! [[email]]', 'groq'))->ask($this->request('{"question":"Available?"}'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame(['answer' => 'Yes!', 'actions' => [['type' => 'email']], 'provider' => 'groq'], json_decode($response->getContent(), TRUE));
  }

  /**
   * No provider answering yields the napping answer.
   */
  public function testAllProvidersDownNaps(): void {
    $data = json_decode($this->controller($this->flood())->ask($this->request('{"question":"Hi"}'))->getContent(), TRUE);
    $this->assertSame('none', $data['provider']);
    $this->assertSame([['type' => 'email']], $data['actions']);
  }

}
