<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Cv;

use Drupal\Component\Transliteration\PhpTransliteration;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\node\NodeInterface;

/**
 * Renders a tailored CV with the profile's PDF template.
 */
final class CvPdfGenerator {

  /**
   * Constructs a CvPdfGenerator.
   */
  public function __construct(
    private readonly PrintBuilderInterface $printBuilder,
    private readonly EntityPrintPluginManagerInterface $printEngines,
    private readonly TailoredCvContext $context,
  ) {}

  /**
   * Returns the PDF bytes for the profile rendered with the plan.
   *
   * @param \Drupal\node\NodeInterface $profile
   *   The profile node.
   * @param array $plan
   *   The approved plan (TailoringPlanValidator shape, edited by the owner).
   */
  public function generate(NodeInterface $profile, array $plan): string {
    $this->context->set($plan);
    try {
      $html = $this->printBuilder->printHtml($profile);
      $engine = $this->printEngines->createSelectedInstance('pdf');
      $engine->addPage($html);
      return $engine->getBlob();
    }
    finally {
      $this->context->clear();
    }
  }

  /**
   * Returns the download file name, e.g. Ricardo-Contreras-CV-SohoSGS.pdf.
   *
   * @param string $site_name
   *   The site name (the owner's name).
   * @param string $company
   *   The optional company name.
   */
  public static function filename(string $site_name, string $company): string {
    $slug = static function (string $text): string {
      $ascii = (new PhpTransliteration())->transliterate($text, 'en');
      return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii), '-');
    };
    $company_slug = $slug($company);
    return $slug($site_name) . '-CV-' . ($company_slug !== '' ? $company_slug : 'Tailored') . '.pdf';
  }

}
