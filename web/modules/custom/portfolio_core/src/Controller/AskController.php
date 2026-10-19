<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\portfolio_core\Assistant\AnswerParser;
use Drupal\portfolio_core\Assistant\AskRequestValidator;
use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Assistant\LlmChain;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Answers visitor questions about Ricardo for the Droopy guide.
 */
final class AskController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  private const FLOOD = [
    'portfolio_core.ask_minute' => [10, 60],
    'portfolio_core.ask_day' => [60, 86400],
  ];

  /**
   * Constructs an AskController.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FloodInterface $flood,
    private readonly AskRequestValidator $validator,
    private readonly AssistantContextBuilder $contextBuilder,
    private readonly LlmChain $chain,
    private readonly AnswerParser $parser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('flood'),
      $container->get('portfolio_core.ask_validator'),
      $container->get('portfolio_core.assistant_context'),
      $container->get('portfolio_core.llm_chain'),
      $container->get('portfolio_core.answer_parser'),
    );
  }

  /**
   * Handles POST /api/portfolio/ask.
   */
  public function ask(Request $request): JsonResponse {
    $config = $this->configFactory->get('portfolio_core.assistant');
    if (!$config->get('enabled')) {
      throw new NotFoundHttpException();
    }

    if (!$this->validator->isAllowedOrigin($request->headers->get('Origin'), $request->getHost())) {
      return $this->json(['error' => 'Forbidden origin.'], 403);
    }

    $ip = (string) $request->getClientIp();
    foreach (self::FLOOD as $event => [$limit, $window]) {
      if (!$this->flood->isAllowed($event, $limit, $window, $ip)) {
        return $this->json([
          'answer' => (string) $this->t("I've answered a lot of questions — let me catch my breath! 💧 Try again in a bit, or email Ricardo directly."),
          'actions' => [['type' => 'email']],
          'provider' => 'none',
        ], 429);
      }
    }

    try {
      $input = $this->validator->validate(json_decode($request->getContent(), TRUE));
    }
    catch (\InvalidArgumentException $e) {
      return $this->json(['error' => $e->getMessage()], 400);
    }

    foreach (self::FLOOD as $event => [, $window]) {
      $this->flood->register($event, $window, $ip);
    }

    $messages = [...$input['history'], ['role' => 'user', 'content' => $input['question']]];
    $result = $this->chain->complete(
      $this->contextBuilder->build($input['mode']),
      $messages,
      (int) $config->get('max_tokens_' . $input['mode']),
    );

    if ($result === NULL) {
      return $this->json([
        'answer' => (string) $this->t("I'm napping right now 😴 — email Ricardo instead and he'll get back to you."),
        'actions' => [['type' => 'email']],
        'provider' => 'none',
      ]);
    }

    $parsed = $this->parser->parse($result->text);
    return $this->json([
      'answer' => $parsed['text'],
      'actions' => $parsed['actions'],
      'provider' => $result->provider,
    ]);
  }

  /**
   * Returns a never-cached JSON response.
   */
  private function json(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'no-store');
    return $response;
  }

}
