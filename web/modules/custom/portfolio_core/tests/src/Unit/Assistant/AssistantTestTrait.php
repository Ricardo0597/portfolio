<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;
use Drupal\portfolio_core\Assistant\AssistantKeys;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * Shared fixtures for assistant unit tests.
 */
trait AssistantTestTrait {

  /**
   * Requests captured by the mock HTTP client.
   */
  protected array $httpHistory = [];

  /**
   * Returns a config factory serving portfolio_core.assistant.
   */
  protected function assistantConfig(array $overrides = []): ConfigFactoryInterface {
    $values = $overrides + [
      'enabled' => TRUE,
      'providers' => ['groq', 'gemini'],
      'groq_model' => 'openai/gpt-oss-120b',
      'gemini_model' => 'gemini-flash-lite-latest',
      'max_tokens_chat' => 400,
      'max_tokens_fit' => 700,
    ];
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn(string $key) => $values[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    return $factory;
  }

  /**
   * Returns a key lookup with the given provider keys.
   */
  protected function keys(array $keys): AssistantKeys {
    // Real keys may exist in the environment (.ddev/.env); tests must not
    // see them.
    putenv('GROQ_API_KEY');
    putenv('GEMINI_API_KEY');
    $settings = [];
    foreach ($keys as $provider => $key) {
      $settings[$provider . '_api_key'] = $key;
    }
    return new AssistantKeys(new Settings(['portfolio_assistant' => $settings]));
  }

  /**
   * Returns a Guzzle client answering with the queued responses/exceptions.
   */
  protected function http(array $queue): Client {
    $stack = HandlerStack::create(new MockHandler($queue));
    $this->httpHistory = [];
    $stack->push(Middleware::history($this->httpHistory));
    return new Client(['handler' => $stack]);
  }

}
