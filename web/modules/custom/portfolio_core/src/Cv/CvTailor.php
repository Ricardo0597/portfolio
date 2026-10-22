<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Assistant\LlmChain;

/**
 * Produces a validated CV tailoring plan for a job description.
 */
final class CvTailor {

  private const MAX_TOKENS = 1200;

  /**
   * Constructs a CvTailor.
   */
  public function __construct(
    private readonly AssistantContextBuilder $contextBuilder,
    private readonly CvTailoringPrompt $prompt,
    private readonly LlmChain $chain,
    private readonly TailoringPlanValidator $validator,
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
   * @throws \RuntimeException
   *   When no provider answers or the answer can't be used.
   */
  public function tailor(string $job_description): array {
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
