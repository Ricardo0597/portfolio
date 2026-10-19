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
        // gpt-oss is a reasoning model: reasoning tokens count against
        // max_tokens, so keep reasoning short and out of the reply.
        'reasoning_effort' => 'low',
        'include_reasoning' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function extractText(array $data): mixed {
    return $data['choices'][0]['message']['content'] ?? NULL;
  }

}
