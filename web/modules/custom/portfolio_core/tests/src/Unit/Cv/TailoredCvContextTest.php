<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Cv;

use Drupal\portfolio_core\Cv\TailoredCvContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the request-scoped tailored CV plan holder.
 */
#[CoversClass(TailoredCvContext::class)]
#[Group('portfolio_core')]
final class TailoredCvContextTest extends UnitTestCase {

  /**
   * A plan can be set, read and cleared.
   */
  public function testContextSetGetClear(): void {
    $context = new TailoredCvContext();
    $this->assertNull($context->get());
    $context->set(['summary' => 'x']);
    $this->assertSame(['summary' => 'x'], $context->get());
    $context->clear();
    $this->assertNull($context->get());
  }

}
