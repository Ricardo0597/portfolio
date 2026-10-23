<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

/**
 * Applies the owner's preview edits to a tailoring plan.
 *
 * Only walks what the plan already holds, so form input can change wording
 * and ticks but never add bullets, highlights or jobs.
 */
final class TailoredPlanEditor {

  /**
   * Returns the plan with the preview edits applied.
   *
   * @param array $plan
   *   The validated plan held in form state.
   * @param string $summary
   *   The edited summary.
   * @param array $highlights
   *   Edited highlight texts, by plan index; empty drops the highlight.
   * @param array $jobs
   *   Checkboxes values per plan job: a ticked box holds its key (core passes
   *   key 0 as the string '0'), an unticked box holds integer 0.
   */
  public function apply(array $plan, string $summary, array $highlights, array $jobs): array {
    $plan['summary'] = TailoringPlanValidator::normalize($summary);

    $edited = [];
    foreach ($plan['highlights'] as $i => $highlight) {
      $text = TailoringPlanValidator::normalize((string) ($highlights[$i] ?? ''));
      if ($text !== '') {
        $edited[] = ['text' => $text] + $highlight;
      }
    }
    $plan['highlights'] = $edited;

    foreach ($plan['jobs'] as $j => $job) {
      // Only integer 0 means unticked; '0' is a ticked box with key 0.
      $ticked = array_filter((array) ($jobs[$j] ?? []), fn($v) => $v !== 0);
      foreach (array_keys($job['bullets']) as $b) {
        $plan['jobs'][$j]['bullets'][$b]['checked'] = isset($ticked[$b]);
      }
    }
    return $plan;
  }

}
