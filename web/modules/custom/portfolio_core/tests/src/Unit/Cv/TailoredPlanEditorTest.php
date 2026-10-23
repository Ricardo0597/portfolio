<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Cv;

use Drupal\portfolio_core\Cv\TailoredPlanEditor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests applying the owner's preview edits to a plan.
 */
#[CoversClass(TailoredPlanEditor::class)]
#[Group('portfolio_core')]
final class TailoredPlanEditorTest extends UnitTestCase {

  /**
   * A plan with one job of three bullets and two highlights.
   */
  private function plan(): array {
    return [
      'summary' => 'AI summary',
      'highlights' => [
        ['text' => 'AI one', 'source' => 'n1', 'source_text' => 'Fact one', 'unsupported' => []],
        ['text' => 'AI two', 'source' => 'n2', 'source_text' => 'Fact two', 'unsupported' => []],
      ],
      'skills' => ['CMS' => ['Drupal']],
      'jobs' => [[
        'index' => 0,
        'bullets' => [
          ['text' => 'First', 'checked' => TRUE],
          ['text' => 'Second', 'checked' => TRUE],
          ['text' => 'Third', 'checked' => FALSE],
        ],
      ],
      ],
      'gaps' => [],
    ];
  }

  /**
   * Core checkboxes report a ticked key 0 as the string '0'; it must count.
   */
  public function testTickedFirstBulletIsKept(): void {
    // Shape of Checkboxes values: ticked = its key as a string, unticked = 0.
    $values = [[0 => '0', 1 => 0, 2 => '2']];
    $plan = (new TailoredPlanEditor())->apply($this->plan(), 'Mine', ['AI one', 'AI two'], $values);
    $this->assertSame([TRUE, FALSE, TRUE], array_column($plan['jobs'][0]['bullets'], 'checked'));
  }

  /**
   * Unticked boxes (integer 0) are unchecked.
   */
  public function testUntickedBulletsAreDropped(): void {
    $values = [[0 => 0, 1 => 0, 2 => 0]];
    $plan = (new TailoredPlanEditor())->apply($this->plan(), 'Mine', ['AI one', 'AI two'], $values);
    $this->assertSame([FALSE, FALSE, FALSE], array_column($plan['jobs'][0]['bullets'], 'checked'));
  }

  /**
   * Summary and highlight edits apply; a cleared highlight is dropped.
   */
  public function testSummaryAndHighlightEdits(): void {
    $values = [[0 => '0', 1 => '1', 2 => 0]];
    $plan = (new TailoredPlanEditor())->apply($this->plan(), '  My summary ', ['  ', 'My two'], $values);
    $this->assertSame('My summary', $plan['summary']);
    $this->assertCount(1, $plan['highlights']);
    $this->assertSame('My two', $plan['highlights'][0]['text']);
    $this->assertSame('n2', $plan['highlights'][0]['source']);
  }

  /**
   * Extra or forged keys cannot add bullets or highlights.
   */
  public function testForgedValuesAddNothing(): void {
    $values = [[0 => '0', 1 => '1', 2 => '2', 7 => '7'], [0 => '0']];
    $plan = (new TailoredPlanEditor())->apply($this->plan(), 'Mine', ['a', 'b', 'Injected'], $values);
    $this->assertCount(2, $plan['highlights']);
    $this->assertCount(1, $plan['jobs']);
    $this->assertCount(3, $plan['jobs'][0]['bullets']);
  }

}
