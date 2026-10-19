<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

/**
 * Google Gemini generateContent API (free tier).
 */
final class GeminiClient extends HttpLlmClientBase {

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'gemini';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRequest(string $key, string $model, string $system, array $messages, int $max_tokens): array {
    $contents = array_map(fn(array $m): array => [
      'role' => $m['role'] === 'assistant' ? 'model' : 'user',
      'parts' => [['text' => $m['content']]],
    ], $messages);

    return [
      'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
      ['x-goog-api-key' => $key],
      [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => $contents,
        // Thinking tokens count against maxOutputTokens; turn thinking off.
        'generationConfig' => [
          'maxOutputTokens' => $max_tokens,
          'temperature' => 0.4,
          'thinkingConfig' => ['thinkingBudget' => 0],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function extractText(array $data): mixed {
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? NULL;
  }

}
