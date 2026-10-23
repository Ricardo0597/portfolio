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
   *   Keys: summary, highlights (text, source, source_text, unsupported:
   *   skill labels named in the text but not in the cited fact), skills
   *   (category => labels), jobs (index, bullets: text + checked), gaps.
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

    return [
      'summary' => $this->clamp($summary, self::MAX_SUMMARY),
      'highlights' => $this->highlights($plan['highlights'] ?? [], $sources, $facts['skills'] ?? []),
      'skills' => $this->skills($plan['skills'] ?? [], $facts['skills'] ?? []),
      'jobs' => $this->jobs($plan['jobs'] ?? [], $facts['experience'] ?? []),
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
   * Maps every citable id (j1.b1, n1) to its fact text.
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
      // Skills the highlight names but its cited fact doesn't: shown as a
      // warning in the preview so the owner can correct the wording.
      $unsupported = array_values(array_filter($labels, fn($label) => $this->mentions($text, $label)
        && !$this->mentions($sources[$source], $label)));
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
   * Whether the text names the label as a whole word, ignoring case.
   */
  private function mentions(string $text, string $label): bool {
    return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($label, '/') . '(?![\p{L}\p{N}])/iu', $text);
  }

  /**
   * Reorders skill categories and labels by the plan's priority list.
   */
  private function skills(mixed $chosen, array $by_category): array {
    $rank = [];
    foreach (is_array($chosen) ? $chosen : [] as $label) {
      if (is_string($label) && !isset($rank[$label])) {
        $rank[$label] = count($rank);
      }
    }

    $ordered = [];
    foreach ($by_category as $category => $labels) {
      $picked = array_values(array_filter($labels, fn($l) => isset($rank[$l])));
      usort($picked, fn($a, $b) => $rank[$a] <=> $rank[$b]);
      $rest = array_values(array_filter($labels, fn($l) => !isset($rank[$l])));
      $ordered[$category] = [
        'labels' => array_merge($picked, $rest),
        'first' => $picked ? $rank[$picked[0]] : PHP_INT_MAX,
      ];
    }
    // Stable sort: categories without chosen skills keep their order.
    uasort($ordered, fn($a, $b) => $a['first'] <=> $b['first']);

    return array_map(fn($c) => $c['labels'], $ordered);
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
      $jobs[] = ['index' => $i, 'bullets' => $items];
    }
    return $jobs;
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
