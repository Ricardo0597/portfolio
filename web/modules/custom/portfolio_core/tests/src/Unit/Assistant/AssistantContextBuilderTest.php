<?php
declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Service\ProfileDataService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the system prompt composed from profile facts.
 */
#[CoversClass(AssistantContextBuilder::class)]
#[Group('portfolio_core')]
final class AssistantContextBuilderTest extends UnitTestCase {

  /**
   * Returns a complete set of facts.
   */
  private function facts(array $overrides = []): array {
    return $overrides + [
      'name' => 'Ricardo Alonso Contreras García',
      'title' => 'Full Stack Developer',
      'location' => 'Costa Rica (Remote)',
      'email' => 'ricardo@example.com',
      'bio' => 'Proficient Drupal developer.',
      'skills' => ['CMS' => ['Drupal', 'Twig'], 'Backend' => ['PHP']],
      'experience' => [[
        'job_title' => 'Fullstack Developer',
        'company' => 'TanRen Tech',
        'location' => 'Remote',
        'start' => 'Aug 2022',
        'end' => 'Present',
        'achievements' => ['Integrated Acquia DAM with Drupal.'],
      ]],
      'education' => "Bachelor's degree in Systems Engineering — Universidad Nacional (UNA), 2016–2020",
      'languages' => ['Spanish — Native', 'English — Intermediate (B2)'],
      'links' => ['GitHub' => 'https://github.com/example'],
      'availability' => [
        'available_from' => 'Immediately',
        'work_mode' => 'Remote only',
        'engagement_types' => ['Full-time', 'Part-time', 'Contract', 'Freelance'],
        'schedule' => 'Flexible with time zones and project length',
      ],
    ];
  }

  /**
   * Builds the service under test.
   */
  private function builder(): AssistantContextBuilder {
    return new AssistantContextBuilder(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ProfileDataService::class),
      $this->createMock(CacheBackendInterface::class),
    );
  }

  /**
   * Availability facts are part of the prompt.
   */
  public function testIncludesAvailabilityFacts(): void {
    $prompt = $this->builder()->compose($this->facts(), 'chat');
    $this->assertStringContainsString('Immediately', $prompt);
    $this->assertStringContainsString('Remote only', $prompt);
    $this->assertStringContainsString('Full-time, Part-time, Contract, Freelance', $prompt);
    $this->assertStringContainsString('Flexible with time zones and project length', $prompt);
  }

  /**
   * Empty facts produce no label line.
   */
  public function testOmitsEmptyFacts(): void {
    $prompt = $this->builder()->compose($this->facts(['education' => '']), 'chat');
    $this->assertStringNotContainsString('Education:', $prompt);
  }

  /**
   * Every job, its dates and achievements are listed.
   */
  public function testIncludesEachJobAndAchievement(): void {
    $prompt = $this->builder()->compose($this->facts(), 'chat');
    $this->assertStringContainsString('Fullstack Developer at TanRen Tech', $prompt);
    $this->assertStringContainsString('Aug 2022 – Present', $prompt);
    $this->assertStringContainsString('Integrated Acquia DAM with Drupal.', $prompt);
  }

  /**
   * A job without a company keeps its full title.
   */
  public function testJobWithoutCompanyKeepsTitle(): void {
    $prompt = $this->builder()->compose($this->facts(['experience' => [[
      'job_title' => 'analyst',
      'company' => '',
      'location' => '',
      'start' => '',
      'end' => '',
      'achievements' => [],
    ]]]), 'chat');
    $this->assertStringContainsString("Experience: analyst\n", $prompt);
  }

  /**
   * The guardrails and the action tag syntax are always present.
   */
  public function testPromptContainsGuardrails(): void {
    $prompt = $this->builder()->compose($this->facts(), 'chat');
    foreach ([
      'only answer from the facts',
      'never state salary or rates',
      'politely decline off-topic',
      "reply in the visitor's language",
      '[[scroll:SECTION]]',
      'about, skills, experience, education, contact',
      '[[cv]]',
      '[[email]]',
      'earlier assistant turns',
    ] as $needle) {
      $this->assertStringContainsStringIgnoringCase($needle, $prompt);
    }
  }

  /**
   * Fit mode asks for a match assessment with a longer limit.
   */
  public function testFitModeAddsMatchInstructions(): void {
    $fit = $this->builder()->compose($this->facts(), 'fit');
    $chat = $this->builder()->compose($this->facts(), 'chat');
    foreach (['strengths', 'gaps', 'verdict', '200 words'] as $needle) {
      $this->assertStringContainsStringIgnoringCase($needle, $fit);
    }
    $this->assertStringContainsString('120 words', $chat);
    $this->assertStringNotContainsStringIgnoringCase('verdict', $chat);
  }

}
