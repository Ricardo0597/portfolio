<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

/**
 * Groq chat completions (OpenAI-compatible API, free tier).
 */
final class GroqClient extends HttpLlmClientBase {

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'groq';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRequest(string $key, string $model, string $system, array $messages, int $max_tokens): array {
    return [
      'https://api.groq.com/openai/v1/chat/completions',
      ['Authorization' => 'Bearer ' . $key],
      [
        'model' => $model,
        'messages' => [['role' => 'system', 'content' => $system], ...$messages],
        'max_tokens' => $max_tokens,
        'temperature' => 0.4,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function extractText(array $data): ?string {
    return $data['choices'][0]['message']['content'] ?? NULL;
  }

}
