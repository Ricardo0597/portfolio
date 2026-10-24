<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

/**
 * Thrown when a CV tailoring request is refused by a limit.
 *
 * The code says which limit: TOO_LONG or TOO_MANY.
 */
final class TailoringLimitException extends \RuntimeException {

  /**
   * The job description is longer than CvTailor::MAX_JOB_DESCRIPTION.
   */
  public const TOO_LONG = 1;

  /**
   * The hourly draft limit is reached.
   */
  public const TOO_MANY = 2;

}
