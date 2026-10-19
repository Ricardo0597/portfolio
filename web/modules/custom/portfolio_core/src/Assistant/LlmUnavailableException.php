<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Assistant;

/**
 * Thrown when a provider cannot produce an answer; the chain tries the next.
 */
final class LlmUnavailableException extends \RuntimeException {}
