<?php

/**
 * @file
 * One-off: reconcile the achievement bullets on the current (most recent) work
 * experience entry of the profile node — Fullstack Developer, TanRen Tech.
 *
 * Adds bullets covering Drupal content architecture, page building, WCAG
 * accessibility and Acquia DAM, and removes an earlier draft phrasing.
 *
 * Idempotent. Run: ddev drush scr scripts/seed-experience-bullets.php
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;

// Earlier draft bullets to drop if present.
$remove = [
  'Built and maintained landing pages, reusable components, and structured content using Layout Builder and Acquia Site Studio.',
  'Applied WCAG accessibility best practices across templates and forms: semantic markup, keyboard support, colour contrast, alt text, and ARIA.',
  'Integrated Acquia DAM with Drupal for centralised asset management, metadata, and asset reuse across multiple sites.',
];

// Bullets to ensure are present, appended in this order.
$add = [
  'Built and maintained Content Types, Taxonomies, Views, and Blocks, plus page layouts and reusable components with Layout Builder and Acquia Site Studio.',
  'Created, formatted, and published pages and landing pages, including structured content, menus, and media.',
  'Applied WCAG accessibility best practices: semantic HTML, heading structure, keyboard support, colour contrast, accessible forms, alt text, and ARIA.',
  'Integrated Acquia DAM with Drupal for centralised digital asset management, metadata, and asset reuse across multiple sites.',
];

$nodes = \Drupal::entityTypeManager()
  ->getStorage('node')
  ->loadByProperties(['type' => 'profile']);
$profile = reset($nodes);

if (!$profile instanceof Node) {
  throw new \RuntimeException('No profile node found.');
}

$paragraphs = $profile->get('field_work_experience')->referencedEntities();
if ($paragraphs === []) {
  throw new \RuntimeException('Profile has no work experience entries.');
}

// The template renders entries in field order, most-recent-first.
$current = $paragraphs[0];
$existing = $current->get('field_we_achievements')->value ?? '';

$lines = array_values(array_filter(
  array_map('trim', preg_split('/\r\n|\r|\n/', $existing) ?: []),
  static fn(string $line): bool => $line !== '' && !in_array($line, $remove, TRUE),
));

foreach ($add as $bullet) {
  if (!in_array($bullet, $lines, TRUE)) {
    $lines[] = $bullet;
  }
}

$current->set('field_we_achievements', implode("\n", $lines));
$current->save();
$profile->save();

echo sprintf(
  "Reconciled bullets on \"%s — %s\" (%d lines).\n",
  $current->get('field_we_job_title')->value,
  $current->get('field_we_company')->value,
  count($lines),
);
