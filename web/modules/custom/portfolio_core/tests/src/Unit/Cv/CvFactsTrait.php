<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Cv;

/**
 * Shared profile facts for CV tailoring tests.
 */
trait CvFactsTrait {

  /**
   * Returns a small, complete facts array.
   */
  protected function cvFacts(): array {
    return [
      'name' => 'Ricardo Alonso Contreras García',
      'title' => 'Drupal & Full-Stack Engineer',
      'years' => '7',
      'bio' => 'Drupal and full-stack engineer with 7 years of experience.',
      'skills' => [
        'CMS' => ['Drupal', 'Twig'],
        'DevOps' => ['Acquia Cloud', 'Docker'],
        'Backend' => ['PHP', 'MySQL'],
      ],
      'experience' => [
        [
          'job_title' => 'Fullstack Developer',
          'company' => 'TanRen Tech',
          'location' => 'Remote',
          'start' => 'Aug 2022',
          'end' => 'Present',
          'achievements' => [
            'Developed and maintained Drupal 8, 9, 10, and 11 websites.',
            'Managed local development environments using DDEV.',
            'Integrated Acquia DAM with Drupal.',
          ],
        ],
        [
          'job_title' => 'Web Developer',
          'company' => 'Decimo Inc.',
          'location' => 'Costa Rica',
          'start' => 'Aug 2019',
          'end' => 'Jul 2022',
          'achievements' => [
            'Developed and maintained Drupal 8 websites.',
          ],
        ],
      ],
      'notes' => [
        'Recently managed a full Drupal 10 to Drupal 11 migration, including the PHP version upgrade.',
        'Runs production maintenance off-hours by preference.',
      ],
    ];
  }

}
