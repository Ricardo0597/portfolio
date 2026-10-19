<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Custom Twig filters for the portfolio site.
 *
 * Provides:
 *   - skill_level_label: converts 1-5 integer to readable label.
 *   - reading_time: estimates reading time from an HTML string.
 */
final class PortfolioTwigExtension extends AbstractExtension {

  private const LEVEL_LABELS = [
    1 => 'Beginner',
    2 => 'Elementary',
    3 => 'Intermediate',
    4 => 'Advanced',
    5 => 'Expert',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('skill_level_label', $this->skillLevelLabel(...)),
      new TwigFilter('reading_time', $this->readingTime(...), ['is_safe' => ['html']]),
    ];
  }

  /**
   * Converts a skill level integer (1-5) to a human-readable label.
   *
   * @param mixed $level
   *   The skill level value.
   *
   * @return string
   *   The label, or an empty string for out-of-range values.
   */
  public function skillLevelLabel(mixed $level): string {
    $int = (int) $level;
    return self::LEVEL_LABELS[$int] ?? '';
  }

  /**
   * Estimates reading time from an HTML string.
   *
   * Strips HTML tags, counts words, and divides by 200 wpm.
   *
   * @param mixed $html
   *   The HTML body content.
   *
   * @return string
   *   A string like "5 min read".
   */
  public function readingTime(mixed $html): string {
    if (empty($html)) {
      return '1 min read';
    }
    $text = strip_tags((string) $html);
    $words = str_word_count($text);
    $minutes = (int) ceil($words / 200);
    $minutes = max(1, $minutes);
    return $minutes . ' min read';
  }

}
