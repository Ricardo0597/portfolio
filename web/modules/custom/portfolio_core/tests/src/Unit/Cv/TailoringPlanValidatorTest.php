<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Cv;

use Drupal\portfolio_core\Cv\TailoringPlanValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that tailoring plans are reduced to facts that exist.
 */
#[CoversClass(TailoringPlanValidator::class)]
#[Group('portfolio_core')]
final class TailoringPlanValidatorTest extends UnitTestCase {

  use CvFactsTrait;

  /**
   * Validates a plan given as an array (encoded to JSON first).
   */
  private function validate(array|string $plan): array {
    $raw = is_string($plan) ? $plan : json_encode($plan);
    return (new TailoringPlanValidator())->validate($raw, $this->cvFacts());
  }

  /**
   * A minimal valid plan.
   */
  private function plan(array $overrides = []): array {
    return $overrides + [
      'summary' => 'I build Drupal 11 platforms on Acquia Cloud.',
      'highlights' => [],
      'skills' => [],
      'jobs' => [],
      'gaps' => [],
    ];
  }

  /**
   * Fenced ```json blocks are accepted.
   */
  public function testParsesFencedJson(): void {
    $result = $this->validate("```json\n" . json_encode($this->plan()) . "\n```");
    $this->assertSame('I build Drupal 11 platforms on Acquia Cloud.', $result['summary']);
  }

  /**
   * Prose around the JSON object is ignored.
   */
  public function testParsesJsonWithSurroundingProse(): void {
    $result = $this->validate('Here is the plan: ' . json_encode($this->plan()) . ' Hope it helps {x}');
    $this->assertSame('I build Drupal 11 platforms on Acquia Cloud.', $result['summary']);
  }

  /**
   * No JSON object means an exception.
   */
  public function testInvalidJsonThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->validate('Sorry, I cannot help with that.');
  }

  /**
   * A missing or empty summary means an exception.
   */
  public function testMissingSummaryThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->validate($this->plan(['summary' => '  ']));
  }

  /**
   * Unknown job and bullet ids are dropped.
   */
  public function testDropsUnknownBulletAndJobIds(): void {
    $result = $this->validate($this->plan([
      'jobs' => [
      ['id' => 'j1', 'bullets' => ['j1.b99', 'j1.b3', 'j2.b1']],
      ['id' => 'j9', 'bullets' => ['j9.b1']],
      ],
    ]));
    $this->assertCount(2, $result['jobs']);
    $this->assertSame(['text' => 'Integrated Acquia DAM with Drupal.', 'checked' => TRUE], $result['jobs'][0]['bullets'][0]);
    $this->assertSame(1, array_sum(array_column($result['jobs'][0]['bullets'], 'checked')));
  }

  /**
   * Skills that are not in the profile are dropped.
   */
  public function testDropsUnknownSkills(): void {
    $result = $this->validate($this->plan(['skills' => ['Kubernetes', 'PHP']]));
    $all = array_merge(...array_values($result['skills']));
    $this->assertNotContains('Kubernetes', $all);
    $this->assertSame('PHP', $all[0]);
  }

  /**
   * Highlights citing unknown sources are dropped.
   */
  public function testDropsHighlightWithUnknownSource(): void {
    $result = $this->validate($this->plan([
      'highlights' => [
      ['text' => 'Ran a team of 40', 'source' => 'n9'],
      ['text' => 'No source at all'],
      ],
    ]));
    $this->assertSame([], $result['highlights']);
  }

  /**
   * A kept highlight carries the text of the fact it cites.
   */
  public function testHighlightGetsSourceText(): void {
    $result = $this->validate($this->plan([
      'highlights' => [
      ['text' => 'Led a Drupal 10 to 11 migration with a PHP upgrade', 'source' => 'n1'],
      ['text' => 'Acquia DAM integration', 'source' => 'J1.B3'],
      ],
    ]));
    $this->assertSame('Recently managed a full Drupal 10 to Drupal 11 migration, including the PHP version upgrade.', $result['highlights'][0]['source_text']);
    $this->assertSame('j1.b3', $result['highlights'][1]['source']);
  }

  /**
   * A job the plan leaves out keeps every bullet, checked, in order.
   */
  public function testOmittedJobKeepsAllBulletsChecked(): void {
    $result = $this->validate($this->plan(['jobs' => [['id' => 'j1', 'bullets' => ['j1.b2']]]]));
    $this->assertSame([['text' => 'Developed and maintained Drupal 8 websites.', 'checked' => TRUE]], $result['jobs'][1]['bullets']);
  }

  /**
   * Bullets the plan didn't choose follow, unchecked, in original order.
   */
  public function testUnchosenBulletsAppendedUnchecked(): void {
    $result = $this->validate($this->plan(['jobs' => [['id' => 'j1', 'bullets' => ['j1.b3']]]]));
    $this->assertSame([
      ['text' => 'Integrated Acquia DAM with Drupal.', 'checked' => TRUE],
      ['text' => 'Developed and maintained Drupal 8, 9, 10, and 11 websites.', 'checked' => FALSE],
      ['text' => 'Managed local development environments using DDEV.', 'checked' => FALSE],
    ], $result['jobs'][0]['bullets']);
    $this->assertSame(0, $result['jobs'][0]['index']);
  }

  /**
   * Categories follow their first chosen skill; chosen skills lead.
   */
  public function testSkillsReorderedAcrossAndWithinCategories(): void {
    $result = $this->validate($this->plan(['skills' => ['Acquia Cloud', 'Twig', 'PHP']]));
    $this->assertSame([
      'DevOps' => ['Acquia Cloud', 'Docker'],
      'CMS' => ['Twig', 'Drupal'],
      'Backend' => ['PHP', 'MySQL'],
    ], $result['skills']);
  }

  /**
   * Lengths and counts are clamped.
   */
  public function testClampsLengthsAndCounts(): void {
    $long = str_repeat('word ', 300);
    $result = $this->validate($this->plan([
      'summary' => $long,
      'highlights' => array_fill(0, 5, ['text' => $long, 'source' => 'n1']),
      'gaps' => array_fill(0, 9, $long),
    ]));
    $this->assertLessThanOrEqual(600, mb_strlen($result['summary']));
    $this->assertCount(3, $result['highlights']);
    $this->assertLessThanOrEqual(160, mb_strlen($result['highlights'][0]['text']));
    $this->assertCount(6, $result['gaps']);
    $this->assertLessThanOrEqual(200, mb_strlen($result['gaps'][0]));
  }

  /**
   * A highlight naming a skill its source doesn't mention is flagged.
   */
  public function testFlagsSkillsMissingFromHighlightSource(): void {
    $result = $this->validate($this->plan([
      'highlights' => [
      ['text' => 'Maintained Drupal 11 sites on Acquia Cloud with Docker', 'source' => 'j1.b1'],
      ['text' => 'Integrated Acquia DAM with Drupal', 'source' => 'j1.b3'],
      ],
    ]));
    $this->assertSame(['Acquia Cloud', 'Docker'], $result['highlights'][0]['unsupported']);
    $this->assertSame([], $result['highlights'][1]['unsupported']);
  }

  /**
   * Typographic hyphens and arrows the PDF font lacks become plain text.
   */
  public function testNormalisesCharactersThePdfFontLacks(): void {
    $result = $this->validate($this->plan([
      'summary' => "Drupal 8\u{2011}11 sites, off\u{2010}hours work, 10 \u{2192} 11 migration.",
      'highlights' => [['text' => "Drupal 10 \u{2192} 11 migration\u{2011}ready", 'source' => 'n1']],
    ]));
    $this->assertSame('Drupal 8-11 sites, off-hours work, 10 to 11 migration.', $result['summary']);
    $this->assertSame('Drupal 10 to 11 migration-ready', $result['highlights'][0]['text']);
  }

  /**
   * A skill label inside a longer word ("PHP" in "PHPUnit") is not a mention.
   */
  public function testSkillWarningMatchesWholeWordsOnly(): void {
    $result = $this->validate($this->plan([
      'highlights' => [
        ['text' => 'Kept PHPUnit suites green during off-hours releases', 'source' => 'n2'],
        ['text' => 'Ran off-hours work on Docker', 'source' => 'n2'],
      ],
    ]));
    $this->assertSame([], $result['highlights'][0]['unsupported']);
    $this->assertSame(['Docker'], $result['highlights'][1]['unsupported']);
  }

}
