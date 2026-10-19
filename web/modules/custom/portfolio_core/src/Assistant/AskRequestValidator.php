<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

/**
 * Validates and normalises /api/portfolio/ask payloads.
 */
final class AskRequestValidator {

  private const MAX_QUESTION = ['chat' => 1000, 'fit' => 6000];
  private const MAX_HISTORY_ITEMS = 6;
  private const MAX_HISTORY_CHARS = 1000;

  /**
   * Returns the normalised payload.
   *
   * @param mixed $payload
   *   The decoded JSON body.
   *
   * @return array{question: string, mode: string, history: list<array{role: string, content: string}>}
   *   The validated payload.
   *
   * @throws \InvalidArgumentException
   *   With a short reason when the payload is invalid.
   */
  public function validate(mixed $payload): array {
    if (!is_array($payload)) {
      throw new \InvalidArgumentException('Expected a JSON object.');
    }
    $mode = $payload['mode'] ?? 'chat';
    if (!is_string($mode) || !isset(self::MAX_QUESTION[$mode])) {
      throw new \InvalidArgumentException('Unknown mode.');
    }
    $question = is_string($payload['question'] ?? NULL) ? trim($payload['question']) : '';
    if ($question === '') {
      throw new \InvalidArgumentException('Question is required.');
    }
    if (mb_strlen($question) > self::MAX_QUESTION[$mode]) {
      throw new \InvalidArgumentException('Question is too long.');
    }

    $history = $payload['history'] ?? [];
    if (!is_array($history) || count($history) > self::MAX_HISTORY_ITEMS) {
      throw new \InvalidArgumentException('History is invalid.');
    }
    $clean = [];
    foreach ($history as $turn) {
      $role = $turn['role'] ?? NULL;
      $content = $turn['content'] ?? NULL;
      if (!in_array($role, ['user', 'assistant'], TRUE) || !is_string($content) || mb_strlen($content) > self::MAX_HISTORY_CHARS) {
        throw new \InvalidArgumentException('History is invalid.');
      }
      $clean[] = ['role' => $role, 'content' => $content];
    }

    return ['question' => $question, 'mode' => $mode, 'history' => $clean];
  }


  /**
   * Whether a browser Origin header belongs to this site.
   *
   * Compares hosts only, so a TLS-terminating proxy (browser https, PHP http)
   * does not cause false rejections. A missing Origin (non-browser client) is
   * allowed: flood control, not this check, is the quota protection.
   *
   * @param string|null $origin
   *   The Origin header, if any.
   * @param string $host
   *   The request host as Drupal sees it.
   */
  public function isAllowedOrigin(?string $origin, string $host): bool {
    if ($origin === NULL) {
      return TRUE;
    }
    $origin_host = parse_url($origin, PHP_URL_HOST);
    return is_string($origin_host) && strcasecmp($origin_host, $host) === 0;
  }

}
