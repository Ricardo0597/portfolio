<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;
use Drupal\node\NodeInterface;
use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Service\ProfileDataService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how profile facts are loaded and cached.
 */
#[CoversClass(AssistantContextBuilder::class)]
#[Group('portfolio_core')]
final class AssistantContextFactsTest extends UnitTestCase {

  /**
   * Builds the service with the given profile (or none) and cache.
   */
  private function builder(?NodeInterface $profile, CacheBackendInterface $cache, ?EntityStorageInterface &$storage = NULL): AssistantContextBuilder {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn($profile ? [1 => $profile] : []);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($storage);

    $profile_data = $this->createMock(ProfileDataService::class);
    $profile_data->method('getSkillsByCategory')->willReturn(['CMS' => ['Drupal']]);
    $profile_data->method('getWorkExperience')->willReturn([
      ['id' => 7, 'job_title' => 'Developer', 'achievements' => ['Built sites.']],
    ]);
    $profile_data->method('getCertifications')->willReturn([['title' => 'Acquia Certified (2023)', 'url' => '']]);
    $profile_data->method('getAvailability')->willReturn(['available_from' => 'Immediately']);

    return new AssistantContextBuilder($entity_type_manager, $profile_data, $cache, $this->createMock(TimeInterface::class));
  }

  /**
   * A text field returning the given value.
   */
  private function field(?string $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->willReturnCallback(fn(string $name) => $name === 'value' ? $value : NULL);
    $field->method('isEmpty')->willReturn($value === NULL);
    return $field;
  }

  /**
   * A link field pointing at the given URL.
   */
  private function link(string $url): FieldItemListInterface {
    $url_object = $this->createMock(Url::class);
    $url_object->method('toString')->willReturn($url);
    $item = $this->createMock(LinkItemInterface::class);
    $item->method('getUrl')->willReturn($url_object);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('first')->willReturn($item);
    return $field;
  }

  /**
   * A profile node with the given fields.
   */
  private function profile(array $fields): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('label')->willReturn('Ricardo Contreras');
    $node->method('hasField')->willReturnCallback(fn(string $name) => isset($fields[$name]));
    $node->method('get')->willReturnCallback(fn(string $name) => $fields[$name] ?? $this->field(NULL));
    $node->method('getCacheTags')->willReturn(['node:1']);
    return $node;
  }

  /**
   * A cache miss with nothing stored.
   */
  private function emptyCache(): CacheBackendInterface {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    return $cache;
  }

  /**
   * Cached facts are returned without loading the profile.
   */
  public function testCacheHitSkipsLoading(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn((object) ['data' => ['name' => 'Cached']]);
    $builder = $this->builder(NULL, $cache, $storage);
    $storage->expects($this->never())->method('loadByProperties');
    $this->assertSame(['name' => 'Cached'], $builder->facts());
  }

  /**
   * Without a published profile there are no facts, and nothing is cached.
   */
  public function testNoProfileReturnsNothing(): void {
    $cache = $this->emptyCache();
    $cache->expects($this->never())->method('set');
    $this->assertSame([], $this->builder(NULL, $cache)->facts());
  }

  /**
   * Fields become plain facts; the bio loses its markup and entities.
   */
  public function testReadsProfileFields(): void {
    $facts = $this->builder($this->profile([
      'field_subtitle' => $this->field('Drupal Engineer'),
      'field_years_experience' => $this->field('7'),
      'field_bio' => $this->field('<p>Drupal &amp; PHP developer.</p>'),
      'field_edu_degree' => $this->field('BSc Systems Engineering'),
      'field_edu_institution' => $this->field('UNA'),
      'field_edu_start_year' => $this->field('2016'),
      'field_edu_end_year' => $this->field('2020'),
      'field_github_url' => $this->link('https://github.com/example'),
      'field_assistant_notes' => $this->field("First fact.\r\n\r\nSecond fact."),
    ]), $this->emptyCache())->facts();

    $this->assertSame('Ricardo Contreras', $facts['name']);
    $this->assertSame('Drupal Engineer', $facts['title']);
    $this->assertSame('7', $facts['years']);
    $this->assertSame('Drupal & PHP developer.', $facts['bio']);
    $this->assertSame('BSc Systems Engineering — UNA, 2016–2020', $facts['education']);
    $this->assertSame(['GitHub' => 'https://github.com/example'], $facts['links']);
    $this->assertSame(['First fact.', 'Second fact.'], $facts['notes']);
    $this->assertSame(7, $facts['experience'][0]['id']);
    $this->assertSame([], $facts['languages']);
  }

  /**
   * Facts are cached with the profile's tags plus skill list tags.
   */
  public function testCachesWithProfileAndSkillTags(): void {
    $cache = $this->emptyCache();
    $cache->expects($this->once())->method('set')->with(
      'portfolio_core:assistant_facts',
      $this->anything(),
      CacheBackendInterface::CACHE_PERMANENT,
      $this->callback(fn(array $tags) => !array_diff(['node:1', 'node_list:skill', 'taxonomy_term_list:skill_category'], $tags)),
    );
    $this->builder($this->profile([]), $cache)->facts();
  }

}
