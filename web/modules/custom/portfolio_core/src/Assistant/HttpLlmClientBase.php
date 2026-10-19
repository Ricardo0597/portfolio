<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Shared HTTP plumbing for JSON chat-completion providers.
 */
abstract class HttpLlmClientBase implements LlmClientInterface {

  /**
   * Constructs the client.
   */
  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly AssistantKeys $keys,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    return $this->keys->get($this->id()) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function complete(string $system, array $messages, int $max_tokens): string {
    $key = $this->keys->get($this->id());
    if ($key === NULL) {
      throw new LlmUnavailableException($this->id() . ': no API key');
    }
    $model = (string) $this->configFactory->get('portfolio_core.assistant')->get($this->id() . '_model');
    [$url, $headers, $body] = $this->buildRequest($key, $model, $system, $messages, $max_tokens);

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => $headers + ['Content-Type' => 'application/json'],
        'json' => $body,
        'timeout' => 8,
        'connect_timeout' => 4,
        'http_errors' => TRUE,
      ]);
    }
    catch (GuzzleException $e) {
      // Never include the request (it carries the key) in the message.
      throw new LlmUnavailableException(sprintf('%s: request failed (%s)', $this->id(), $e->getCode()), 0);
    }

    $data = json_decode((string) $response->getBody(), TRUE);
    $text = is_array($data) ? $this->extractText($data) : NULL;
    if (!is_string($text) || trim($text) === '') {
      throw new LlmUnavailableException($this->id() . ': empty completion');
    }
    return trim($text);
  }

  /**
   * Builds the provider request.
   *
   * @return array{0: string, 1: array<string, string>, 2: array<string, mixed>}
   *   URL, headers and JSON body.
   */
  abstract protected function buildRequest(string $key, string $model, string $system, array $messages, int $max_tokens): array;

  /**
   * Returns the raw reply value from a decoded response.
   *
   * Untyped on purpose: providers may return non-strings, which complete()
   * treats as unavailable instead of crashing.
   */
  abstract protected function extractText(array $data): mixed;

}
