<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Tries the configured providers in order until one answers.
 */
final class LlmChain {

  /**
   * Clients keyed by provider id.
   *
   * @var array<string, \Drupal\portfolio_core\Assistant\LlmClientInterface>
   */
  private array $clients = [];

  /**
   * Constructs an LlmChain.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param iterable<\Drupal\portfolio_core\Assistant\LlmClientInterface> $clients
   *   Services tagged portfolio_core.llm_client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The portfolio_core logger.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    iterable $clients,
    private readonly LoggerInterface $logger,
  ) {
    foreach ($clients as $client) {
      $this->clients[$client->id()] = $client;
    }
  }

  /**
   * Returns the first provider's answer, or NULL when all fail.
   *
   * @param string $system
   *   The system prompt.
   * @param list<array{role: string, content: string}> $messages
   *   Conversation turns.
   * @param int $max_tokens
   *   Maximum tokens in the reply.
   */
  public function complete(string $system, array $messages, int $max_tokens): ?LlmResult {
    $order = $this->configFactory->get('portfolio_core.assistant')->get('providers') ?? [];
    foreach ($order as $id) {
      $client = $this->clients[$id] ?? NULL;
      if ($client === NULL || !$client->isConfigured()) {
        continue;
      }
      try {
        return new LlmResult($client->complete($system, $messages, $max_tokens), $id);
      }
      catch (LlmUnavailableException $e) {
        $this->logger->warning('Assistant provider unavailable: @message', ['@message' => $e->getMessage()]);
      }
    }
    return NULL;
  }

}
