<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\portfolio_core\Service\ProfileDataService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests splitting work-experience achievements into bullets.
 */
#[CoversClass(ProfileDataService::class)]
#[Group('portfolio_core')]
final class ProfileDataServiceAchievementsTest extends UnitTestCase {

  /**
   * Parses achievements with the service under test.
   */
  private function parse(string $raw): array {
    return (new ProfileDataService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(FileUrlGeneratorInterface::class),
    ))->parseAchievements($raw);
  }

  /**
   * Plain text: one bullet per non-empty line.
   */
  public function testPlainTextLines(): void {
    $this->assertSame(['First.', 'Second.'], $this->parse("First.\r\n\r\n  Second.  \n"));
  }

  /**
   * Rich text paragraphs become separate bullets without tags.
   */
  public function testHtmlParagraphs(): void {
    $this->assertSame(['First.', 'Second.'], $this->parse('<p>First.</p><p>Second.</p>'));
  }

  /**
   * List items and line breaks split; entities are decoded.
   */
  public function testHtmlListsBreaksAndEntities(): void {
    $this->assertSame(
      ['Git &amp; Docker', 'Twig', 'Views'],
      array_map('htmlspecialchars', $this->parse('<ul><li>Git &amp; Docker</li><li>Twig<br>Views</li></ul>')),
    );
  }

  /**
   * Inline markup is stripped, never printed.
   */
  public function testStripsInlineTags(): void {
    $this->assertSame(['Built a fast site.'], $this->parse('<p>Built a <strong>fast</strong> site.</p>'));
  }

}
