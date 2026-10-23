<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Drupal\portfolio_core\Service\ProfileDataService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ProfileDataService::getCertifications().
 */
#[CoversClass(ProfileDataService::class)]
#[Group('portfolio_core')]
final class ProfileDataServiceCertificationsTest extends UnitTestCase {

  /**
   * Builds the service under test.
   */
  private function service(): ProfileDataService {
    return new ProfileDataService(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(FileUrlGeneratorInterface::class),
    );
  }

  /**
   * Builds a link field item list mock holding the given raw items.
   */
  private function items(array $values): FieldItemListInterface {
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('isEmpty')->willReturn($values === []);
    $items->method('getValue')->willReturn($values);
    return $items;
  }

  /**
   * Builds a profile node mock; fields absent from $fields do not exist.
   */
  private function profile(array $fields): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturnCallback(fn(string $name) => isset($fields[$name]));
    $node->method('get')->willReturnCallback(fn(string $name) => $fields[$name]);
    return $node;
  }

  /**
   * Each link becomes a title and its verification URL, in field order.
   */
  public function testReturnsTitleAndUrl(): void {
    $result = $this->service()->getCertifications($this->profile([
      'field_certifications' => $this->items([
        ['uri' => 'https://certification.acquia.com/x', 'title' => ' Acquia Certified Site Builder – Drupal 10 (2023) '],
        ['uri' => 'https://example.com/untitled', 'title' => ''],
      ]),
    ]));
    $this->assertSame([
      ['title' => 'Acquia Certified Site Builder – Drupal 10 (2023)', 'url' => 'https://certification.acquia.com/x'],
    ], $result);
  }

  /**
   * A missing or empty field gives no certifications.
   */
  public function testMissingOrEmptyFieldGivesNone(): void {
    $this->assertSame([], $this->service()->getCertifications($this->profile([])));
    $this->assertSame([], $this->service()->getCertifications($this->profile(['field_certifications' => $this->items([])])));
  }

}
