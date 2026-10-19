<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

/**
 * Extracts whitelisted action tags from an LLM answer.
 *
 * The model is told to append tags such as [[scroll:experience]], [[cv]] and
 * [[email]]. Unknown tags and targets are dropped; all tags are stripped.
 */
final class AnswerParser {

  /**
   * Page sections a scroll action may target.
   */
  public const SECTIONS = ['about', 'skills', 'experience', 'education', 'contact'];

  /**
   * Splits an answer into display text and actions.
   *
   * @return array{text: string, actions: list<array{type: string, target?: string}>}
   *   The cleaned text and the de-duplicated, whitelisted actions.
   */
  public function parse(string $raw): array {
    $actions = [];
    $text = preg_replace_callback(
      '/\[\[([a-z]+)(?::([^\]]*))?\]\]/',
      function (array $match) use (&$actions): string {
        $action = match (TRUE) {
          $match[1] === 'scroll' && in_array($match[2] ?? '', self::SECTIONS, TRUE) => ['type' => 'scroll', 'target' => $match[2]],
          in_array($match[1], ['cv', 'email'], TRUE) && !isset($match[2]) => ['type' => $match[1]],
          default => NULL,
        };
        if ($action !== NULL && !in_array($action, $actions, TRUE)) {
          $actions[] = $action;
        }
        return '';
      },
      $raw,
    );

    // Collapse the gaps left by removed tags, keeping paragraph breaks.
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);
    $text = preg_replace('/[ \t]+$/m', '', $text);
    $text = preg_replace('/^[ \t]+/m', '', $text);

    return ['text' => trim($text), 'actions' => $actions];
  }

}
