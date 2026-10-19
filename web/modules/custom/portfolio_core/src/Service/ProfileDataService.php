<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
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
   *   Skill labels keyed by category name.
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
   *   Counts keyed by experience, projects, skills and articles.
   */
  public function getContentStats(NodeInterface $profile): array {
    $storage = $this->entityTypeManager->getStorage('node');
    return [
      'experience' => (int) ($profile->get('field_years_experience')->value ?? 0),
      'projects'   => count($storage->loadByProperties(['type' => 'project', 'status' => 1])),
      'skills'     => count($storage->loadByProperties(['type' => 'skill', 'status' => 1])),
      'articles'   => count($storage->loadByProperties(['type' => 'article', 'status' => 1])),
    ];
  }

  /**
   * Returns formatted work experience items from the profile paragraph field.
   *
   * @return list<array<string, mixed>>
   *   One item per job: job_title, company, location, start, end,
   *   achievements (and employment_type when requested).
   */
  public function getWorkExperience(NodeInterface $profile, bool $includeEmploymentType = FALSE): array {
    $items = [];
    foreach ($profile->get('field_work_experience')->referencedEntities() as $paragraph) {
      $start_raw = $paragraph->get('field_we_start_date')->value;
      $end_raw   = $paragraph->get('field_we_end_date')->value;

      $achievements = $this->parseAchievements($paragraph->get('field_we_achievements')->value ?? '');

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
   * Splits an achievements field into plain-text bullets.
   *
   * One bullet per line. If the value was saved through a rich-text editor,
   * paragraphs, list items and line breaks also split, and all markup is
   * stripped so tags are never printed.
   *
   * @return list<string>
   *   Non-empty, trimmed bullets.
   */
  public function parseAchievements(string $raw): array {
    $text = preg_replace('#<br\s*/?>|</(p|li|div|h[1-6])>#i', "\n", $raw);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return array_values(array_filter(
      array_map('trim', preg_split('/\R/u', $text)),
      fn(string $line): bool => $line !== '',
    ));
  }

  /**
   * Returns the profile's availability facts as display labels.
   *
   * @return array{available_from: string, work_mode: string, engagement_types: list<string>, schedule: string}
   *   Empty strings / array for unset or missing fields.
   */
  public function getAvailability(NodeInterface $profile): array {
    return [
      'available_from' => $this->stringValue($profile, 'field_available_from'),
      'work_mode' => $this->listLabels($profile, 'field_work_mode')[0] ?? '',
      'engagement_types' => $this->listLabels($profile, 'field_engagement_types'),
      'schedule' => $this->stringValue($profile, 'field_schedule_flexibility'),
    ];
  }

  /**
   * Returns a single string field value, or '' when unset or missing.
   */
  private function stringValue(NodeInterface $node, string $field_name): string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }
    return trim($node->get($field_name)->getString());
  }

  /**
   * Returns the allowed-value labels of a list field's selected keys.
   *
   * @return list<string>
   *   Labels in field order; empty when unset or missing.
   */
  private function listLabels(NodeInterface $node, string $field_name): array {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return [];
    }
    $items = $node->get($field_name);
    $allowed = $items->getFieldDefinition()->getFieldStorageDefinition()->getSetting('allowed_values') ?? [];
    return array_values(array_map(
      fn(array $item): string => (string) ($allowed[$item['value']] ?? $item['value']),
      $items->getValue(),
    ));
  }

  /**
   * Returns the absolute URL for a file field, or empty string if unset.
   */
  public function fileUrl(NodeInterface $node, string $field_name): string {
    if (!$node->hasField($field_name)) {
      return '';
    }
    $field = $node->get($field_name);
    if ($field->isEmpty() || !$field->entity) {
      return '';
    }
    return $this->fileUrlGenerator->generateAbsoluteString($field->entity->getFileUri());
  }

}
