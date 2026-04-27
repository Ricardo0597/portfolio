<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\MarkupInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\node\NodeInterface;

/**
 * Provides pre-processed data for profile-related templates.
 *
 * Centralises all entity queries and formatting logic that would otherwise
 * live as procedural code in the .module file, enabling proper DI and testing.
 */
class ProfileDataService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Returns skill nodes grouped by category, sorted alphabetically.
   *
   * @return array<string, list<string>>
   */
  public function getSkillsByCategory(): array {
    $skill_nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => 'skill', 'status' => 1]);

    $by_category = [];
    foreach ($skill_nodes as $skill) {
      $terms = $skill->get('field_category')->referencedEntities();
      $category = !empty($terms) ? $terms[0]->label() : 'Other';
      $by_category[$category][] = $skill->label();
    }

    ksort($by_category);
    foreach ($by_category as &$labels) {
      sort($labels);
    }

    return $by_category;
  }

  /**
   * Returns content-type counts for the stats strip.
   *
   * @return array<string, int>
   */
  public function getContentStats(NodeInterface $profile): array {
    $storage = $this->entityTypeManager->getStorage('node');
    return [
      'experience' => (int) ($profile->get('field_years_experience')->value ?? 0),
      'projects'   => count($storage->loadByProperties(['type' => 'project', 'status' => 1])),
      'skills'     => count($storage->loadByProperties(['type' => 'skill',   'status' => 1])),
      'articles'   => count($storage->loadByProperties(['type' => 'article', 'status' => 1])),
    ];
  }

  /**
   * Returns formatted work experience items from the profile paragraph field.
   *
   * @return list<array<string, mixed>>
   */
  public function getWorkExperience(NodeInterface $profile, bool $includeEmploymentType = FALSE): array {
    $items = [];
    foreach ($profile->get('field_work_experience')->referencedEntities() as $paragraph) {
      $start_raw = $paragraph->get('field_we_start_date')->value;
      $end_raw   = $paragraph->get('field_we_end_date')->value;

      $achievements_raw = $paragraph->get('field_we_achievements')->value ?? '';
      $achievements = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $achievements_raw)),
        fn(string $line): bool => $line !== '',
      ));

      $item = [
        'job_title' => $paragraph->get('field_we_job_title')->value ?? '',
        'company'   => $paragraph->get('field_we_company')->value ?? '',
        'location'  => $paragraph->get('field_we_location')->value ?? '',
        'start'     => $start_raw
          ? $this->dateFormatter->format(strtotime($start_raw), 'custom', 'M Y')
          : '',
        'end'       => $end_raw
          ? $this->dateFormatter->format(strtotime($end_raw), 'custom', 'M Y')
          : 'Present',
        'achievements' => $achievements,
      ];

      if ($includeEmploymentType) {
        $item['employment_type'] = $paragraph->get('field_we_employment_type')->value ?? '';
      }

      $items[] = $item;
    }
    return $items;
  }

  /**
   * Returns the absolute URL for a file field, or empty string if unset.
   */
  public function fileUrl(NodeInterface $node, string $field_name): string {
    $field = $node->get($field_name);
    if ($field->isEmpty() || !$field->entity) {
      return '';
    }
    return $this->fileUrlGenerator->generateAbsoluteString($field->entity->getFileUri());
  }

}
