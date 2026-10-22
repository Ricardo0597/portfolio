<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Cv;

use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Assistant\LlmChain;
use Drupal\portfolio_core\Assistant\LlmResult;
use Drupal\portfolio_core\Cv\CvTailor;
use Drupal\portfolio_core\Cv\CvTailoringPrompt;
use Drupal\portfolio_core\Cv\TailoringPlanValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the CV tailoring service.
 */
#[CoversClass(CvTailor::class)]
#[Group('portfolio_core')]
final class CvTailorTest extends UnitTestCase {

  use CvFactsTrait;

  /**
   * Builds the service with a chain that returns the given answer.
   */
  private function tailor(?string $answer, ?array &$sent = NULL): CvTailor {
    $context = $this->createMock(AssistantContextBuilder::class);
    $context->method('facts')->willReturn($this->cvFacts());
    $chain = $this->createMock(LlmChain::class);
    $chain->method('complete')->willReturnCallback(function (string $system, array $messages, int $max) use ($answer, &$sent) {
      $sent = ['system' => $system, 'messages' => $messages, 'max' => $max];
      return $answer === NULL ? NULL : new LlmResult($answer, 'groq');
    });
    return new CvTailor($context, new CvTailoringPrompt(), $chain, new TailoringPlanValidator());
  }

  /**
   * A valid answer becomes a validated plan.
   */
  public function testReturnsValidatedPlan(): void {
    $plan = $this->tailor('{"summary": "I build Drupal 11 sites.", "jobs": [{"id": "j1", "bullets": ["j1.b3"]}]}')->tailor('Drupal job');
    $this->assertSame('I build Drupal 11 sites.', $plan['summary']);
    $this->assertSame('Integrated Acquia DAM with Drupal.', $plan['jobs'][0]['bullets'][0]['text']);
  }

  /**
   * No provider answering is a runtime error.
   */
  public function testNoProviderThrows(): void {
    $this->expectException(\RuntimeException::class);
    $this->tailor(NULL)->tailor('Drupal job');
  }

  /**
   * An unusable answer is a runtime error.
   */
  public function testInvalidPlanThrows(): void {
    $this->expectException(\RuntimeException::class);
    $this->tailor('I cannot do that.')->tailor('Drupal job');
  }

  /**
   * The job description is the only user message; the prompt is the system.
   */
  public function testSendsJobDescriptionAsUserMessage(): void {
    $sent = NULL;
    $this->tailor('{"summary": "x"}', $sent)->tailor('  Senior Drupal role  ');
    $this->assertSame([['role' => 'user', 'content' => 'Senior Drupal role']], $sent['messages']);
    $this->assertStringContainsString('[j1.b1]', $sent['system']);
    $this->assertSame(1200, $sent['max']);
  }

}
