<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\portfolio_core\Assistant\AssistantContextBuilder;
use Drupal\portfolio_core\Cv\CvPdfGenerator;
use Drupal\portfolio_core\Cv\CvTailor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Private tool: tailor the CV to a job description and download it.
 *
 * Step 1 takes the job description; step 2 previews the AI plan (editable
 * summary and highlights, bullets as checkboxes, gaps) and downloads the PDF.
 * Nothing is stored: the plan lives in the form state.
 */
final class TailoredCvForm extends FormBase {

  /**
   * Constructs the form.
   *
   * Plain (not readonly) properties: multi-step forms are serialized between
   * steps and DependencySerializationTrait re-injects services on wakeup,
   * which readonly properties don't allow.
   */
  public function __construct(
    protected CvTailor $tailor,
    protected CvPdfGenerator $pdfGenerator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AssistantContextBuilder $contextBuilder,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('portfolio_core.cv_tailor'),
      $container->get('portfolio_core.cv_pdf_generator'),
      $container->get('entity_type.manager'),
      $container->get('portfolio_core.assistant_context'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'portfolio_core_tailored_cv';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $plan = $form_state->get('plan');
    $input = $form_state->get('input') ?? ['job_description' => '', 'company' => ''];

    $form['job_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Job description'),
      '#default_value' => $input['job_description'],
      '#rows' => $plan ? 4 : 14,
      '#maxlength' => 6000,
      '#required' => TRUE,
      '#description' => $this->t('Paste the posting. It is sent to the AI provider and not stored.'),
    ];
    $form['company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company (optional, used in the file name)'),
      '#default_value' => $input['company'],
      '#maxlength' => 80,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['generate'] = [
      '#type' => 'submit',
      '#value' => $plan ? $this->t('Regenerate') : $this->t('Generate draft'),
      '#submit' => ['::generateSubmit'],
      '#button_type' => $plan ? 'secondary' : 'primary',
    ];

    if ($plan) {
      $this->buildPreview($form, $plan);
    }
    return $form;
  }

  /**
   * Adds the preview of a plan to the form.
   */
  private function buildPreview(array &$form, array $plan): void {
    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Draft'),
      '#open' => TRUE,
      '#weight' => 50,
    ];
    $preview = &$form['preview'];

    $preview['summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Professional summary'),
      '#default_value' => $plan['summary'],
      '#rows' => 5,
      '#maxlength' => 600,
      '#required' => TRUE,
      '#description' => $this->t('AI-written — check before downloading. @count words; keep it under about 80 to fit one page.', [
        '@count' => str_word_count($plan['summary']),
      ]),
    ];

    $preview['highlights'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Key highlights'),
      '#description' => $this->t('AI-written — check before downloading. Clear a line to drop it.'),
      '#tree' => TRUE,
    ];
    foreach ($plan['highlights'] as $i => $highlight) {
      $note = $this->t('From: @source', ['@source' => $highlight['source_text']]);
      if (!empty($highlight['unsupported'])) {
        $note = $this->t('From: @source — Warning: mentions @terms, which this fact does not.', [
          '@source' => $highlight['source_text'],
          '@terms' => implode(', ', $highlight['unsupported']),
        ]);
      }
      $preview['highlights'][$i] = [
        '#type' => 'textfield',
        '#title' => $this->t('Highlight @n', ['@n' => $i + 1]),
        '#default_value' => $highlight['text'],
        '#maxlength' => 160,
        '#description' => $note,
      ];
    }

    $preview['skills'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Skills, most relevant first'),
      '#items' => array_map(
        fn(string $category, array $labels): string => $category . ': ' . implode(', ', $labels),
        array_keys($plan['skills']),
        $plan['skills'],
      ),
    ];

    $experience = $this->contextBuilder->facts()['experience'] ?? [];
    $preview['jobs'] = ['#tree' => TRUE];
    foreach ($plan['jobs'] as $j => $job) {
      $meta = $experience[$job['index']] ?? [];
      $options = array_column($job['bullets'], 'text');
      $preview['jobs'][$j] = [
        '#type' => 'checkboxes',
        '#title' => trim(($meta['job_title'] ?? '') . ' — ' . ($meta['company'] ?? ''), ' —'),
        '#options' => $options,
        '#default_value' => array_keys(array_filter(array_column($job['bullets'], 'checked'))),
        '#description' => $this->t('Ticked bullets print, in this order.'),
      ];
    }

    $preview['gaps'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Gaps for this role (not printed)'),
      '#items' => $plan['gaps'] ?: [$this->t('None found.')],
    ];

    $preview['download'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download PDF'),
      '#submit' => ['::downloadSubmit'],
      '#button_type' => 'primary',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Each button has its own handler.
  }

  /**
   * Builds a new draft from the job description.
   */
  public function generateSubmit(array &$form, FormStateInterface $form_state): void {
    $input = [
      'job_description' => (string) $form_state->getValue('job_description'),
      'company' => trim((string) $form_state->getValue('company')),
    ];
    $form_state->set('input', $input);
    try {
      $form_state->set('plan', $this->tailor->tailor($input['job_description']));
      // New defaults for the preview fields.
      $form_state->setUserInput(array_intersect_key($form_state->getUserInput(), $input));
    }
    catch (\RuntimeException $e) {
      $this->messenger()->addError($this->t("Couldn't build a draft, try again."));
      $this->getLogger('portfolio_core')->warning('Tailored CV draft failed: @message', ['@message' => $e->getMessage()]);
    }
    $form_state->setRebuild();
  }

  /**
   * Applies the edits and returns the PDF.
   */
  public function downloadSubmit(array &$form, FormStateInterface $form_state): void {
    $plan = $form_state->get('plan');
    $profile = $this->profile();
    if (!$plan || !$profile) {
      $form_state->setRebuild();
      return;
    }

    $plan['summary'] = trim((string) $form_state->getValue('summary'));
    $edited = [];
    foreach ($plan['highlights'] as $i => $highlight) {
      $text = trim((string) $form_state->getValue(['highlights', $i]));
      if ($text !== '') {
        $edited[] = ['text' => $text] + $highlight;
      }
    }
    $plan['highlights'] = $edited;
    foreach ($plan['jobs'] as $j => $job) {
      $ticked = array_filter((array) $form_state->getValue(['jobs', $j]), fn($v) => $v !== 0 && $v !== '0');
      foreach ($job['bullets'] as $b => $bullet) {
        $plan['jobs'][$j]['bullets'][$b]['checked'] = isset($ticked[$b]);
      }
    }

    $input = $form_state->get('input');
    $filename = CvPdfGenerator::filename((string) $this->config('system.site')->get('name'), (string) ($input['company'] ?? ''));
    $response = new Response($this->pdfGenerator->generate($profile, $plan), 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      'Cache-Control' => 'no-store, private',
    ]);
    $form_state->setResponse($response);
  }

  /**
   * Returns the published profile node.
   */
  private function profile(): ?NodeInterface {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'profile', 'status' => 1]);
    $node = reset($nodes);
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
