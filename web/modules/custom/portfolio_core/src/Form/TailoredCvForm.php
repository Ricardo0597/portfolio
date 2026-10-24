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
use Drupal\portfolio_core\Cv\TailoredPlanEditor;
use Drupal\portfolio_core\Cv\TailoringLimitException;
use Drupal\portfolio_core\Cv\TailoringPlanValidator;
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
    protected TailoredPlanEditor $planEditor,
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
      $container->get('portfolio_core.tailored_plan_editor'),
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
      '#maxlength' => CvTailor::MAX_JOB_DESCRIPTION,
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
      '#limit_validation_errors' => [['job_description'], ['company']],
      '#button_type' => $plan ? 'secondary' : 'primary',
    ];

    if ($plan) {
      $this->buildPreview($form, $plan, (bool) $form_state->get('allow_multi_page'));
    }
    return $form;
  }

  /**
   * Adds the preview of a plan to the form.
   */
  private function buildPreview(array &$form, array $plan, bool $allow_multi_page): void {
    $form['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Draft'),
      '#open' => TRUE,
      '#weight' => 50,
    ];
    $preview = &$form['preview'];

    $messages = [
      'pronouns' => $this->t('Warning: the summary uses personal pronouns (I, my, he…); CVs usually leave them out.'),
      'years' => $this->t('Warning: the summary does not state your years of experience.'),
    ];
    $warnings = array_intersect_key($messages, array_flip($plan['warnings'] ?? []));
    $preview['summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Professional summary'),
      '#default_value' => $plan['summary'],
      '#rows' => 5,
      '#maxlength' => 600,
      '#required' => TRUE,
      '#id' => 'tailored-cv-summary',
      '#description' => $this->t('AI-written — check before downloading. <span data-word-count-for="tailored-cv-summary">@count</span> words; keep it under about 80 to fit one page. @warnings', [
        '@count' => str_word_count($plan['summary']),
        '@warnings' => implode(' ', array_map('strval', $warnings)),
      ]),
    ];
    $form['#attached']['library'][] = 'portfolio_core/tailored_cv';

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
      '#type' => 'fieldset',
      '#title' => $this->t('Skills'),
      '#description' => $this->t('Ticked skills print, in this order. The AI ticks the ones that matter for this job.'),
      '#tree' => TRUE,
    ];
    foreach (array_values($plan['skills']) as $c => $skills) {
      $preview['skills'][$c] = [
        '#type' => 'checkboxes',
        '#title' => array_keys($plan['skills'])[$c],
        '#options' => array_column($skills, 'label'),
        '#default_value' => array_keys(array_filter(array_column($skills, 'checked'))),
      ];
    }

    $experience = $this->contextBuilder->facts()['experience'] ?? [];
    $preview['jobs'] = ['#tree' => TRUE];
    foreach ($plan['jobs'] as $j => $job) {
      $key = TailoringPlanValidator::locateJob($job, $experience);
      $meta = $key !== NULL ? $experience[$key] : [];
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
      '#value' => $allow_multi_page ? $this->t('Download anyway') : $this->t('Download PDF'),
      '#submit' => ['::downloadSubmit'],
      '#limit_validation_errors' => [['summary'], ['highlights'], ['skills'], ['jobs'], ['company']],
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
    $form_state->set('allow_multi_page', FALSE);
    try {
      $form_state->set('plan', $this->tailor->tailor($input['job_description']));
      // New defaults for the preview fields.
      $form_state->setUserInput(array_intersect_key($form_state->getUserInput(), $input));
    }
    catch (TailoringLimitException $e) {
      $this->messenger()->addError($e->getCode() === TailoringLimitException::TOO_MANY
        ? $this->t("You've generated many drafts in the last hour; try again later.")
        : $this->t('The job description is too long.'));
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

    $plan = $this->planEditor->apply(
      $plan,
      (string) $form_state->getValue('summary'),
      (array) $form_state->getValue('highlights'),
      (array) $form_state->getValue('jobs'),
      (array) $form_state->getValue('skills'),
    );

    // The company field stays editable in the preview: read it now.
    $filename = CvPdfGenerator::filename(
      (string) $this->config('system.site')->get('name'),
      trim((string) $form_state->getValue('company')),
    );
    try {
      $pdf = $this->pdfGenerator->generate($profile, $plan);
    }
    catch (\Throwable $e) {
      $this->getLogger('portfolio_core')->error('Tailored CV PDF failed: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t("Couldn't build the PDF, try again."));
      $form_state->setRebuild();
      return;
    }
    // The CV should fit one A4 page: warn once, and let a second click
    // download it anyway.
    if ($pdf->pages > 1 && !$form_state->get('allow_multi_page')) {
      $form_state->set('allow_multi_page', TRUE);
      $this->messenger()->addWarning($this->t('The PDF runs to @count pages. Shorten the summary or untick bullets, or download it anyway.', ['@count' => $pdf->pages]));
      $form_state->setRebuild();
      return;
    }
    $form_state->setResponse(new Response($pdf->bytes, 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      'Cache-Control' => 'no-store, private',
    ]));
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
