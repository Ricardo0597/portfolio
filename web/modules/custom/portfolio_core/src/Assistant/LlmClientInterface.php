<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

/**
 * A chat-completion provider used by the assistant.
 */
interface LlmClientInterface {

  /**
   * Returns the provider id used in config ('groq', 'gemini').
   */
  public function id(): string;

  /**
   * Whether the provider has an API key.
   */
  public function isConfigured(): bool;

  /**
   * Returns the model's reply.
   *
   * @param string $system
   *   The system prompt.
   * @param list<array{role: string, content: string}> $messages
   *   Conversation turns, roles 'user' or 'assistant'.
   * @param int $max_tokens
   *   Maximum tokens in the reply.
   *
   * @throws \Drupal\portfolio_core\Assistant\LlmUnavailableException
   *   When the provider is unconfigured, unreachable, rate limited, failing
   *   or returns no text.
   */
  public function complete(string $system, array $messages, int $max_tokens): string;

}
