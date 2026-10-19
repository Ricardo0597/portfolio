<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

/**
 * An answer and the provider that produced it.
 */
final class LlmResult {

  /**
   * Constructs an LlmResult.
   */
  public function __construct(
    public readonly string $text,
    public readonly string $provider,
  ) {}

}
