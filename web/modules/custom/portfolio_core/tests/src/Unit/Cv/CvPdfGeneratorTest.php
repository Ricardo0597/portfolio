<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Cv;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Drupal\portfolio_core\Cv\CvPdfGenerator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the tailored CV file name.
 */
#[CoversClass(CvPdfGenerator::class)]
#[Group('portfolio_core')]
final class CvPdfGeneratorTest extends UnitTestCase {

  /**
   * Site name and company make the file name.
   */
  public function testFilenameWithCompany(): void {
    $this->assertSame('Ricardo-Contreras-CV-SohoSGS.pdf', CvPdfGenerator::filename('Ricardo Contreras', 'SohoSGS'));
  }

  /**
   * Without a company the name ends in "Tailored".
   */
  public function testFilenameWithoutCompany(): void {
    $this->assertSame('Ricardo-Contreras-CV-Tailored.pdf', CvPdfGenerator::filename('Ricardo Contreras', '  '));
  }

  /**
   * Unsafe characters become single hyphens; accents are transliterated.
   */
  public function testFilenameStripsUnsafeCharacters(): void {
    $this->assertSame('Ricardo-Contreras-CV-Acme-Corp-Inc.pdf', CvPdfGenerator::filename('Ricardo Contreras', 'Acme / Corp, Inc.'));
    $this->assertSame('Jose-Garcia-CV-Telefonica.pdf', CvPdfGenerator::filename('José García', '"Telefónica"'));
  }

  /**
   * Dompdf's canvas gives the page count when the engine exposes it.
   */
  public function testPageCountFromCanvas(): void {
    $canvas = $this->createMock(Canvas::class);
    $canvas->method('get_page_count')->willReturn(2);
    $dompdf = $this->createMock(Dompdf::class);
    $dompdf->method('getCanvas')->willReturn($canvas);
    $this->assertSame(2, CvPdfGenerator::pageCount($dompdf, ''));
  }

  /**
   * Without a canvas, page objects are counted in the bytes.
   */
  public function testPageCountFromBytes(): void {
    $pdf = "<< /Type /Pages /Count 2 >>\n<< /Type /Page >>\n<< /Type/Page /Parent 1 0 R >>";
    $this->assertSame(2, CvPdfGenerator::pageCount(NULL, $pdf));
    $this->assertSame(1, CvPdfGenerator::pageCount(NULL, 'not a pdf'));
  }

}
