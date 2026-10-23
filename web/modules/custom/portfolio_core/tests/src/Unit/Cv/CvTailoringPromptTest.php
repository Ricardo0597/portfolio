<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Cv;

use Drupal\portfolio_core\Cv\CvTailoringPrompt;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the CV tailoring prompt.
 */
#[CoversClass(CvTailoringPrompt::class)]
#[Group('portfolio_core')]
final class CvTailoringPromptTest extends UnitTestCase {

  use CvFactsTrait;

  /**
   * Jobs and bullets are listed with stable ids and their text.
   */
  public function testListsJobAndBulletIds(): void {
    $prompt = (new CvTailoringPrompt())->compose($this->cvFacts());
    $this->assertStringContainsString('[j1] Fullstack Developer at TanRen Tech (Aug 2022 – Present)', $prompt);
    $this->assertStringContainsString('[j1.b3] Integrated Acquia DAM with Drupal.', $prompt);
    $this->assertStringContainsString('[j2.b1] Developed and maintained Drupal 8 websites.', $prompt);
  }

  /**
   * Extra-knowledge notes are listed with ids.
   */
  public function testListsNoteIds(): void {
    $prompt = (new CvTailoringPrompt())->compose($this->cvFacts());
    $this->assertStringContainsString('[n1] Recently managed a full Drupal 10 to Drupal 11 migration', $prompt);
  }

  /**
   * Skills are listed verbatim.
   */
  public function testListsSkillsVerbatim(): void {
    $prompt = (new CvTailoringPrompt())->compose($this->cvFacts());
    $this->assertStringContainsString('Skills: Drupal, Twig, Acquia Cloud, Docker, PHP, MySQL', $prompt);
  }

  /**
   * The rules and the exact JSON shape are stated.
   */
  public function testStatesRulesAndJsonShape(): void {
    $prompt = (new CvTailoringPrompt())->compose($this->cvFacts());
    $needles = [
      'only use facts listed',
      'reference bullets by id',
      'never invent',
      'nothing that is not in the cited fact',
      '"summary"',
      '"highlights"',
      '"skills"',
      '"jobs"',
      '"gaps"',
      'first person',
    ];
    foreach ($needles as $needle) {
      $this->assertStringContainsStringIgnoringCase($needle, $prompt);
    }
  }

  /**
   * Droopy's tone rules also apply: no salary, plain text, English.
   */
  public function testStatesSalaryPlainTextAndEnglishRules(): void {
    $prompt = (new CvTailoringPrompt())->compose($this->cvFacts());
    foreach (['salary', 'plain text', 'English'] as $needle) {
      $this->assertStringContainsStringIgnoringCase($needle, $prompt);
    }
  }

  /**
   * Availability facts are listed so the summary may use them.
   */
  public function testListsAvailability(): void {
    $prompt = (new CvTailoringPrompt())->compose($this->cvFacts());
    $this->assertStringContainsString('Availability: Immediately; Remote only; Full-time, Contract; Flexible with time zones', $prompt);
  }

}
