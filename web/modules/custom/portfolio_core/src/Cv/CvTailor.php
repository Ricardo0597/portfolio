<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

use Drupal\Core\Flood\FloodInterface;
use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Assistant\LlmChain;

/**
 * Produces a validated CV tailoring plan for a job description.
 */
final class CvTailor {

  private const MAX_TOKENS = 1200;

  /**
   * Same limit as Droopy's fit mode; the form enforces it too.
   */
  public const MAX_JOB_DESCRIPTION = 6000;

  /**
   * Drafts per hour per client, so a stolen session can't drain the quota.
   */
  private const FLOOD = ['portfolio_core.cv_tailor', 20, 3600];

  /**
   * Constructs a CvTailor.
   */
  public function __construct(
    private readonly AssistantContextBuilder $contextBuilder,
    private readonly CvTailoringPrompt $prompt,
    private readonly LlmChain $chain,
    private readonly TailoringPlanValidator $validator,
    private readonly FloodInterface $flood,
  ) {}

  /**
   * Returns a plan built only from the profile facts.
   *
   * @param string $job_description
   *   The pasted job description.
   *
   * @return array
   *   A plan as returned by TailoringPlanValidator::validate().
   *
   * @throws \Drupal\portfolio_core\Cv\TailoringLimitException
   *   When the job description is too long or the hourly limit is reached.
   * @throws \RuntimeException
   *   When no provider answers or the answer can't be used.
   */
  public function tailor(string $job_description): array {
    if (mb_strlen(trim($job_description)) > self::MAX_JOB_DESCRIPTION) {
      throw new TailoringLimitException('The job description is too long.', TailoringLimitException::TOO_LONG);
    }
    [$event, $limit, $window] = self::FLOOD;
    if (!$this->flood->isAllowed($event, $limit, $window)) {
      throw new TailoringLimitException('Too many drafts in the last hour.', TailoringLimitException::TOO_MANY);
    }
    // Counted before the call: failed drafts use provider quota too.
    $this->flood->register($event, $window);

    $facts = $this->contextBuilder->facts();
    $result = $this->chain->complete(
      $this->prompt->compose($facts),
      [['role' => 'user', 'content' => trim($job_description)]],
      self::MAX_TOKENS,
    );
    if ($result === NULL) {
      throw new \RuntimeException('No AI provider answered.');
    }
    try {
      return $this->validator->validate($result->text, $facts);
    }
    catch (\InvalidArgumentException $e) {
      throw new \RuntimeException('The AI answer could not be used: ' . $e->getMessage(), 0, $e);
    }
  }

}
