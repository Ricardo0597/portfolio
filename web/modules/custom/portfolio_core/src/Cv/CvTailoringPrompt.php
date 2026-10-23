<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

/**
 * Builds the system prompt that asks an LLM for a CV tailoring plan.
 *
 * Every fact the plan may use gets a stable id (jobs j1…, bullets j1.b1…,
 * extra-knowledge lines n1…, certifications c1…) so the answer can reference
 * facts instead of restating them; TailoringPlanValidator drops anything that
 * doesn't exist.
 */
final class CvTailoringPrompt {

  /**
   * Composes the prompt from assistant facts.
   *
   * @param array $facts
   *   Facts as returned by AssistantContextBuilder::facts().
   */
  public function compose(array $facts): string {
    $lines = [];
    $name = $facts['name'] ?? 'the candidate';
    $lines[] = 'Name: ' . $name;
    if (!empty($facts['title'])) {
      $lines[] = 'Title: ' . $facts['title'];
    }
    if (!empty($facts['years'])) {
      $lines[] = 'Total years of professional experience: ' . $facts['years'];
    }
    if (!empty($facts['bio'])) {
      $lines[] = 'Current summary: ' . $facts['bio'];
    }

    $availability = $facts['availability'] ?? [];
    $available = array_filter([
      $availability['available_from'] ?? '',
      $availability['work_mode'] ?? '',
      implode(', ', $availability['engagement_types'] ?? []),
      $availability['schedule'] ?? '',
    ]);
    if ($available) {
      $lines[] = 'Availability: ' . implode('; ', $available);
    }

    $skills = array_merge(...array_values($facts['skills'] ?? [[]]));
    if ($skills) {
      $lines[] = 'Skills: ' . implode(', ', $skills);
    }

    foreach (array_values($facts['experience'] ?? []) as $i => $job) {
      $j = 'j' . ($i + 1);
      $dates = implode(' – ', array_filter([$job['start'] ?? '', $job['end'] ?? '']));
      $heading = implode(' at ', array_filter([$job['job_title'] ?? '', $job['company'] ?? '']));
      $lines[] = '[' . $j . '] ' . $heading . ($dates !== '' ? ' (' . $dates . ')' : '');
      foreach (array_values($job['achievements'] ?? []) as $b => $bullet) {
        $lines[] = '  [' . $j . '.b' . ($b + 1) . '] ' . $bullet;
      }
    }

    foreach (array_values($facts['notes'] ?? []) as $k => $note) {
      $lines[] = '[n' . ($k + 1) . '] ' . $note;
    }

    foreach (array_values($facts['certifications'] ?? []) as $k => $cert) {
      $lines[] = '[c' . ($k + 1) . '] Certification: ' . $cert['title'];
    }

    $rules = [
      "You tailor {$name}'s CV to a job description the user sends. You do not write a new CV: you choose and order existing facts.",
      'Only use facts listed below; never invent employers, dates, numbers, tools or skills. Reference bullets by id (j1.b2), never retype them.',
      'summary: at most 80 words in CV style with no personal pronouns (never I, me, my, he or his): start with the role, state the total years of experience (for example "Drupal engineer with 7+ years of experience"), then lead with what the job asks for that the facts support.',
      'highlights: up to 3 short lines (at most 20 words each), each restating one listed fact, with its id in "source" (a bullet id like j1.b2, a note id like n1 or a certification id like c1). Highlights must add something the printed bullets do not already say: never cite a bullet id you also list in jobs, and prefer notes and certifications relevant to the job. A highlight says nothing that is not in the cited fact: do not add tools, places or tasks from the job description, even if the job asks for them.',
      'The summary follows the same rule: every claim must be in the facts; a requirement the facts do not cover goes in gaps, not in the summary.',
      'skills: exact skill labels from the Skills line that matter for the job, most relevant first; leave out skills unrelated to the job.',
      'jobs: for each job id, the bullet ids that best fit the job, most relevant first; leave out weak matches.',
      'gaps: job requirements the facts do not cover, in short phrases.',
      'Write in English and in plain text (no Markdown). Never mention salary or rates.',
      'Answer with ONE JSON object and nothing else, exactly this shape: {"summary": "...", "highlights": [{"text": "...", "source": "n1"}], "skills": ["..."], "jobs": [{"id": "j1", "bullets": ["j1.b1"]}], "gaps": ["..."]}',
    ];

    return implode("\n", $rules) . "\n\nFACTS:\n" . implode("\n", $lines);
  }

}
