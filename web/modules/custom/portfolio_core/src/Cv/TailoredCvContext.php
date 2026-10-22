<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

/**
 * Holds the approved tailoring plan while one PDF is rendered.
 *
 * Lives only in memory for the current request; the profile PDF preprocess
 * reads it, so the public CV is untouched when nothing is set.
 */
final class TailoredCvContext {

  /**
   * The plan being rendered, or NULL.
   */
  private ?array $plan = NULL;

  /**
   * Sets the plan to render.
   */
  public function set(array $plan): void {
    $this->plan = $plan;
  }

  /**
   * Returns the plan being rendered, if any.
   */
  public function get(): ?array {
    return $this->plan;
  }

  /**
   * Forgets the plan.
   */
  public function clear(): void {
    $this->plan = NULL;
  }

}
