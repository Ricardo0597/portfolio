<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

/**
 * Reduces an LLM tailoring plan to references that exist in the facts.
 *
 * The model answers with ids (j1.b2, n1) and skill labels. Anything that does
 * not match the profile facts is dropped, so the CV can only contain facts
 * Ricardo wrote. The summary and highlight wording are the only AI text.
 */
final class TailoringPlanValidator {

  private const MAX_SUMMARY = 600;
  private const MAX_HIGHLIGHTS = 3;
  private const MAX_HIGHLIGHT = 160;
  private const MAX_GAPS = 6;
  private const MAX_GAP = 200;

  /**
   * Validates a raw model answer against the facts.
   *
   * @param string $raw
   *   The model's answer: a JSON object, possibly fenced or with prose.
   * @param array $facts
   *   Facts as returned by AssistantContextBuilder::facts().
   *
   * @return array
   *   Keys: summary, warnings (codes for the preview: 'pronouns' when the
   *   summary uses personal pronouns, 'years' when it omits the years figure),
   *   highlights (text, source, source_text, unsupported: skill labels and
   *   numbers in the text but not in the cited fact; never a bullet that
   *   prints),
   *   skills (category => list of label + checked), jobs (index, id: the
   *   work experience paragraph id when known, bullets: text + checked),
   *   gaps.
   *
   * @throws \InvalidArgumentException
   *   When no JSON object can be read or the summary is empty.
   */
  public function validate(string $raw, array $facts): array {
    $plan = $this->decode($raw);
    $summary = is_string($plan['summary'] ?? NULL) ? trim($plan['summary']) : '';
    if ($summary === '') {
      throw new \InvalidArgumentException('The plan has no summary.');
    }

    $sources = $this->sources($facts);
    $jobs = $this->jobs($plan['jobs'] ?? [], $facts['experience'] ?? []);
    // Bullets that already print: a highlight restating one would repeat it.
    $printed = [];
    foreach ($jobs as $job) {
      foreach ($job['bullets'] as $bullet) {
        if ($bullet['checked']) {
          $printed[$bullet['text']] = TRUE;
        }
      }
    }
    $summary = $this->clamp($summary, self::MAX_SUMMARY);

    return [
      'summary' => $summary,
      'warnings' => $this->warnings($summary, (string) ($facts['years'] ?? '')),
      'highlights' => $this->highlights($plan['highlights'] ?? [], array_diff_key($sources, array_filter(
        $sources, fn(string $text) => isset($printed[$text]),
      )), $facts['skills'] ?? []),
      'skills' => $this->skills($plan['skills'] ?? [], $facts['skills'] ?? []),
      'jobs' => $jobs,
      'gaps' => $this->gaps($plan['gaps'] ?? []),
    ];
  }

  /**
   * Returns the first balanced JSON object in the text, decoded.
   */
  private function decode(string $raw): array {
    $start = strpos($raw, '{');
    while ($start !== FALSE) {
      $depth = 0;
      $in_string = FALSE;
      $escaped = FALSE;
      for ($i = $start, $n = strlen($raw); $i < $n; $i++) {
        $c = $raw[$i];
        if ($in_string) {
          if ($escaped) {
            $escaped = FALSE;
          }
          elseif ($c === '\\') {
            $escaped = TRUE;
          }
          elseif ($c === '"') {
            $in_string = FALSE;
          }
          continue;
        }
        if ($c === '"') {
          $in_string = TRUE;
        }
        elseif ($c === '{') {
          $depth++;
        }
        elseif ($c === '}' && --$depth === 0) {
          $decoded = json_decode(substr($raw, $start, $i - $start + 1), TRUE);
          if (is_array($decoded)) {
            return $decoded;
          }
          break;
        }
      }
      $start = strpos($raw, '{', $start + 1);
    }
    throw new \InvalidArgumentException('No JSON object found in the answer.');
  }

  /**
   * Maps every citable id (j1.b1, n1, c1) to its fact text.
   *
   * @return array<string, string>
   *   Fact text keyed by lowercase id.
   */
  private function sources(array $facts): array {
    $sources = [];
    foreach (array_values($facts['experience'] ?? []) as $i => $job) {
      foreach (array_values($job['achievements'] ?? []) as $b => $bullet) {
        $sources['j' . ($i + 1) . '.b' . ($b + 1)] = $bullet;
      }
    }
    foreach (array_values($facts['notes'] ?? []) as $k => $note) {
      $sources['n' . ($k + 1)] = $note;
    }
    foreach (array_values($facts['certifications'] ?? []) as $k => $cert) {
      $sources['c' . ($k + 1)] = $cert['title'];
    }
    return $sources;
  }

  /**
   * Keeps highlights whose source exists.
   */
  private function highlights(mixed $items, array $sources, array $skills): array {
    $labels = array_merge(...array_values($skills ?: [[]]));
    $kept = [];
    foreach (is_array($items) ? $items : [] as $item) {
      $text = is_string($item['text'] ?? NULL) ? trim($item['text']) : '';
      $source = is_string($item['source'] ?? NULL) ? strtolower(trim($item['source'])) : '';
      if ($text === '' || !isset($sources[$source])) {
        continue;
      }
      // Skills and numbers the highlight names but its cited fact doesn't:
      // shown as a warning in the preview so the owner can fix the wording.
      $unsupported = array_values(array_filter($labels, fn($label) => $this->mentions($text, $label)
        && !$this->mentions($sources[$source], $label)));
      $unsupported = array_merge($unsupported, array_values(array_diff(
        $this->numbers($text),
        $this->numbers($sources[$source]),
      )));
      $kept[] = [
        'text' => $this->clamp($text, self::MAX_HIGHLIGHT),
        'source' => $source,
        'source_text' => $sources[$source],
        'unsupported' => $unsupported,
      ];
      if (count($kept) === self::MAX_HIGHLIGHTS) {
        break;
      }
    }
    return $kept;
  }

  /**
   * Returns the distinct numbers in a text ("10", "3.5", "1,200").
   *
   * @return list<string>
   *   The numbers as written.
   */
  private function numbers(string $text): array {
    preg_match_all('/(?<![\p{L}\p{N}])\d+(?:[.,]\d+)*/u', $text, $matches);
    return array_values(array_unique($matches[0]));
  }

  /**
   * Whether the text names the label as a whole word, ignoring case.
   */
  private function mentions(string $text, string $label): bool {
    return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($label, '/') . '(?![\p{L}\p{N}])/iu', $text);
  }

  /**
   * Reorders skill categories and labels by the plan's priority list.
   *
   * Chosen skills are ticked and lead; the rest follow unticked. When the plan
   * picks no known skill, every skill stays ticked.
   */
  private function skills(mixed $chosen, array $by_category): array {
    $known = array_flip(array_merge(...array_values($by_category ?: [[]])));
    $rank = [];
    foreach (is_array($chosen) ? $chosen : [] as $label) {
      if (is_string($label) && isset($known[$label]) && !isset($rank[$label])) {
        $rank[$label] = count($rank);
      }
    }

    $ordered = [];
    foreach ($by_category as $category => $labels) {
      $picked = array_values(array_filter($labels, fn($l) => isset($rank[$l])));
      usort($picked, fn($a, $b) => $rank[$a] <=> $rank[$b]);
      $rest = array_values(array_filter($labels, fn($l) => !isset($rank[$l])));
      $ordered[$category] = [
        'skills' => array_merge(
          array_map(fn($l) => ['label' => $l, 'checked' => TRUE], $picked),
          array_map(fn($l) => ['label' => $l, 'checked' => !$rank], $rest),
        ),
        'first' => $picked ? $rank[$picked[0]] : PHP_INT_MAX,
      ];
    }
    // Stable sort: categories without chosen skills keep their order.
    uasort($ordered, fn($a, $b) => $a['first'] <=> $b['first']);

    return array_map(fn($c) => $c['skills'], $ordered);
  }

  /**
   * Flags what the owner should fix in the summary before downloading.
   *
   * @return list<string>
   *   'pronouns' and/or 'years'.
   */
  private function warnings(string $summary, string $years): array {
    $warnings = [];
    if (preg_match('/\b(?:I|I\'m|I\'ve|me|my|myself|he|his|him)\b/i', $summary)) {
      $warnings[] = 'pronouns';
    }
    if ($years !== '' && !preg_match('/\b' . preg_quote($years, '/') . '\+?\s*(?:years?|yrs)\b/i', $summary)) {
      $warnings[] = 'years';
    }
    return $warnings;
  }

  /**
   * Orders and checks each job's bullets by the plan.
   */
  private function jobs(mixed $plan_jobs, array $experience): array {
    $chosen = [];
    foreach (is_array($plan_jobs) ? $plan_jobs : [] as $item) {
      $id = is_string($item['id'] ?? NULL) ? strtolower(trim($item['id'])) : '';
      if (preg_match('/^j(\d+)$/', $id, $m) && is_array($item['bullets'] ?? NULL)) {
        $chosen[(int) $m[1] - 1] = $item['bullets'];
      }
    }

    $jobs = [];
    foreach (array_values($experience) as $i => $job) {
      $bullets = array_values($job['achievements'] ?? []);
      if (!isset($chosen[$i])) {
        $jobs[] = [
          'index' => $i,
          'id' => $job['id'] ?? NULL,
          'bullets' => array_map(fn($t) => ['text' => $t, 'checked' => TRUE], $bullets),
        ];
        continue;
      }
      $picked = [];
      foreach ($chosen[$i] as $ref) {
        if (is_string($ref) && preg_match('/^j' . ($i + 1) . '\.b(\d+)$/', strtolower(trim($ref)), $m)) {
          $b = (int) $m[1] - 1;
          if (isset($bullets[$b]) && !in_array($b, $picked, TRUE)) {
            $picked[] = $b;
          }
        }
      }
      $items = array_map(fn($b) => ['text' => $bullets[$b], 'checked' => TRUE], $picked);
      foreach ($bullets as $b => $text) {
        if (!in_array($b, $picked, TRUE)) {
          $items[] = ['text' => $text, 'checked' => FALSE];
        }
      }
      $jobs[] = ['index' => $i, 'id' => $job['id'] ?? NULL, 'bullets' => $items];
    }
    return $jobs;
  }

  /**
   * Finds a plan job in the current work experience.
   *
   * Matches by paragraph id, so reordering experience after the draft was
   * built never moves bullets to another job; falls back to the position for
   * plans without ids.
   *
   * @param array $plan_job
   *   One entry of the plan's 'jobs'.
   * @param array $experience
   *   Work experience as returned by ProfileDataService::getWorkExperience().
   *
   * @return int|null
   *   The key in $experience, or NULL when the job no longer exists.
   */
  public static function locateJob(array $plan_job, array $experience): ?int {
    if (($plan_job['id'] ?? NULL) !== NULL) {
      foreach ($experience as $key => $job) {
        if ((string) ($job['id'] ?? '') === (string) $plan_job['id']) {
          return $key;
        }
      }
      return NULL;
    }
    return isset($experience[$plan_job['index'] ?? -1]) ? $plan_job['index'] : NULL;
  }

  /**
   * Cleans the list of gaps.
   */
  private function gaps(mixed $items): array {
    $gaps = [];
    foreach (is_array($items) ? $items : [] as $gap) {
      if (is_string($gap) && trim($gap) !== '') {
        $gaps[] = $this->clamp(trim($gap), self::MAX_GAP);
      }
      if (count($gaps) === self::MAX_GAPS) {
        break;
      }
    }
    return $gaps;
  }

  /**
   * Replaces characters the PDF font lacks and collapses whitespace.
   *
   * The PDF's built-in fonts lack these glyphs (they print as "?"), so both
   * AI text and the owner's edits go through this before rendering.
   */
  public static function normalize(string $text): string {
    $text = strtr($text, [
      "\u{2010}" => '-',
      "\u{2011}" => '-',
      "\u{2012}" => '-',
      "\u{2212}" => '-',
      "\u{00A0}" => ' ',
      "\u{2192}" => ' to ',
    ]);
    return trim(preg_replace('/\s+/u', ' ', $text));
  }

  /**
   * Normalises the text, then shortens it to at most $max characters.
   */
  private function clamp(string $text, int $max): string {
    $text = self::normalize($text);
    if (mb_strlen($text) <= $max) {
      return $text;
    }
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== FALSE && $space > $max / 2 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
  }

}
