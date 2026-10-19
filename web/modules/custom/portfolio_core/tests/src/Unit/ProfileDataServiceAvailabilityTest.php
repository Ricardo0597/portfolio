<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Drupal\portfolio_core\Service\ProfileDataService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ProfileDataService::getAvailability().
 */
#[CoversClass(ProfileDataService::class)]
#[Group('portfolio_core')]
final class ProfileDataServiceAvailabilityTest extends UnitTestCase {

  private const WORK_MODES = ['remote' => 'Remote only', 'hybrid' => 'Hybrid', 'onsite' => 'On-site'];

  private const ENGAGEMENTS = [
    'full_time' => 'Full-time',
    'part_time' => 'Part-time',
    'contract' => 'Contract',
    'freelance' => 'Freelance',
  ];

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
   * Builds a field item list mock holding the given raw values.
   */
  private function items(array $values, array $allowed = []): FieldItemListInterface {
    $storage = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage->method('getSetting')->with('allowed_values')->willReturn($allowed);
    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getFieldStorageDefinition')->willReturn($storage);

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('isEmpty')->willReturn($values === []);
    $items->method('getValue')->willReturn(array_map(fn($v) => ['value' => $v], $values));
    $items->method('getString')->willReturn(implode(', ', $values));
    $items->method('getFieldDefinition')->willReturn($definition);
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
   * List fields are returned as their human-readable labels.
   */
  public function testReturnsLabelsForListFields(): void {
    $result = $this->service()->getAvailability($this->profile([
      'field_available_from' => $this->items(['Immediately']),
      'field_work_mode' => $this->items(['remote'], self::WORK_MODES),
      'field_engagement_types' => $this->items(['full_time', 'contract'], self::ENGAGEMENTS),
      'field_schedule_flexibility' => $this->items(['Flexible with time zones and project length']),
    ]));

    $this->assertSame([
      'available_from' => 'Immediately',
      'work_mode' => 'Remote only',
      'engagement_types' => ['Full-time', 'Contract'],
      'schedule' => 'Flexible with time zones and project length',
    ], $result);
  }

  /**
   * Empty fields produce empty values.
   */
  public function testReturnsEmptyValuesWhenUnset(): void {
    $result = $this->service()->getAvailability($this->profile([
      'field_available_from' => $this->items([]),
      'field_work_mode' => $this->items([], self::WORK_MODES),
      'field_engagement_types' => $this->items([], self::ENGAGEMENTS),
      'field_schedule_flexibility' => $this->items([]),
    ]));

    $this->assertSame(['available_from' => '', 'work_mode' => '', 'engagement_types' => [], 'schedule' => ''], $result);
  }

  /**
   * Fields missing from the bundle are treated as empty.
   */
  public function testMissingFieldIsTreatedAsEmpty(): void {
    $result = $this->service()->getAvailability($this->profile([]));

    $this->assertSame(['available_from' => '', 'work_mode' => '', 'engagement_types' => [], 'schedule' => ''], $result);
  }

}
