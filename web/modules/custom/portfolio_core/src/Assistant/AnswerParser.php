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
      '/\[\[([a-z]+)(?::([^\]]*))?\]\]/i',
      function (array $match) use (&$actions): string {
        $tag = strtolower($match[1]);
        $target = isset($match[2]) ? strtolower(trim($match[2])) : NULL;
        $action = match (TRUE) {
          $tag === 'scroll' && in_array($target, self::SECTIONS, TRUE) => ['type' => 'scroll', 'target' => $target],
          in_array($tag, ['cv', 'email'], TRUE) && $target === NULL => ['type' => $tag],
          default => NULL,
        };
        if ($action !== NULL && !in_array($action, $actions, TRUE)) {
          $actions[] = $action;
        }
        return '';
      },
      $raw,
    );

    // The panel shows plain text: flatten markdown emphasis and headings.
    $text = preg_replace('/(\*\*|__)(.+?)\1/s', '$2', $text);
    $text = preg_replace('/^#{1,6}[ \t]+/m', '', $text);

    // A tag cut off by the token limit at the very end ("[[scr").
    $text = preg_replace('/\[\[[^\]\s]*$/', '', $text);

    // Collapse the gaps left by removed tags, keeping paragraph breaks.
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);
    $text = preg_replace('/[ \t]+$/m', '', $text);
    $text = preg_replace('/^[ \t]+/m', '', $text);

    return ['text' => trim($text), 'actions' => $actions];
  }

}
