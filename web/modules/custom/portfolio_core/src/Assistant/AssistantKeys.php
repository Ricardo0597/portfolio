<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

use Drupal\Core\Site\Settings;

/**
 * Looks up LLM provider API keys without ever storing them in config.
 *
 * Order: $settings['portfolio_assistant']['<provider>_api_key'], then the
 * <PROVIDER>_API_KEY environment variable (e.g. from .ddev/.env).
 */
final class AssistantKeys {

  /**
   * Constructs an AssistantKeys service.
   */
  public function __construct(
    private readonly Settings $settings,
  ) {}

  /**
   * Returns the API key for a provider, or NULL when none is configured.
   *
   * @param string $provider
   *   Provider id, e.g. 'groq' or 'gemini'.
   */
  public function get(string $provider): ?string {
    $from_settings = $this->settings->get('portfolio_assistant', [])[$provider . '_api_key'] ?? '';
    if (is_string($from_settings) && $from_settings !== '') {
      return $from_settings;
    }
    $from_env = getenv(strtoupper($provider) . '_API_KEY');
    return is_string($from_env) && $from_env !== '' ? $from_env : NULL;
  }

}
