<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Drupal\portfolio_core\Service\ProfileDataService;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ProfileDataService::getSkillsByCategory().
 */
#[CoversClass(ProfileDataService::class)]
#[Group('portfolio_core')]
final class ProfileDataServiceSkillsTest extends UnitTestCase {

  /**
   * Builds a category term mock.
   */
  private function term(string $label, int $weight): TermInterface {
    $term = $this->createMock(TermInterface::class);
    $term->method('label')->willReturn($label);
    $term->method('getWeight')->willReturn($weight);
    return $term;
  }

  /**
   * Builds a skill node mock.
   */
  private function skill(string $label, ?TermInterface $category, int $level): NodeInterface {
    $categories = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $categories->method('referencedEntities')->willReturn($category ? [$category] : []);
    $levels = $this->createMock(FieldItemListInterface::class);
    $levels->method('__get')->with('value')->willReturn((string) $level);

    $node = $this->createMock(NodeInterface::class);
    $node->method('label')->willReturn($label);
    $node->method('get')->willReturnMap([
      ['field_category', $categories],
      ['field_skill_level', $levels],
    ]);
    return $node;
  }

  /**
   * Builds the service with the given skill nodes.
   */
  private function service(array $skills): ProfileDataService {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn($skills);
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->willReturn($storage);
    return new ProfileDataService(
      $etm,
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(FileUrlGeneratorInterface::class),
    );
  }

  /**
   * Categories follow term weight; skills go strongest first, then by name.
   */
  public function testOrdersByCategoryWeightThenSkillLevel(): void {
    $cms = $this->term('CMS', 0);
    $architecture = $this->term('Architecture', 4);
    $result = $this->service([
      $this->skill('GA4 / GTM', $architecture, 4),
      $this->skill('Configuration Management', $cms, 4),
      $this->skill('Twig', $cms, 5),
      $this->skill('Drupal', $cms, 5),
      $this->skill('Loose', NULL, 3),
    ])->getSkillsByCategory();

    $this->assertSame([
      'CMS' => ['Drupal', 'Twig', 'Configuration Management'],
      'Architecture' => ['GA4 / GTM'],
      'Other' => ['Loose'],
    ], $result);
  }

}
