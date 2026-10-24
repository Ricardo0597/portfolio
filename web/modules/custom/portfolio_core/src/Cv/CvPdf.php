<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

/**
 * A rendered CV: the PDF bytes and how many pages they hold.
 */
final class CvPdf {

  /**
   * Constructs a CvPdf.
   */
  public function __construct(
    public readonly string $bytes,
    public readonly int $pages,
  ) {}

}
