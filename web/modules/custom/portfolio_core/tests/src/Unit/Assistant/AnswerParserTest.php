<?php
declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\portfolio_core\Assistant\AnswerParser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests extraction of whitelisted action tags from model answers.
 */
#[CoversClass(AnswerParser::class)]
#[Group('portfolio_core')]
final class AnswerParserTest extends UnitTestCase {

  /**
   * A scroll tag becomes a scroll action and is stripped from the text.
   */
  public function testExtractsScrollAction(): void {
    $result = (new AnswerParser())->parse('He has 7 years. [[scroll:experience]]');
    $this->assertSame('He has 7 years.', $result['text']);
    $this->assertSame([['type' => 'scroll', 'target' => 'experience']], $result['actions']);
  }

  /**
   * CV and email tags become actions in order.
   */
  public function testExtractsCvAndEmail(): void {
    $result = (new AnswerParser())->parse('Grab it [[cv]] or write [[email]]');
    $this->assertSame('Grab it or write', $result['text']);
    $this->assertSame([['type' => 'cv'], ['type' => 'email']], $result['actions']);
  }

  /**
   * Scroll targets outside the section whitelist are dropped.
   */
  public function testDropsUnknownSection(): void {
    $result = (new AnswerParser())->parse('x [[scroll:../../etc]]');
    $this->assertSame([], $result['actions']);
    $this->assertSame('x', $result['text']);
  }

  /**
   * Unknown tags are dropped and stripped.
   */
  public function testDropsUnknownTag(): void {
    $result = (new AnswerParser())->parse('x [[delete]]');
    $this->assertSame([], $result['actions']);
    $this->assertSame('x', $result['text']);
  }

  /**
   * A tag cut off at the end of a truncated answer is stripped.
   */
  public function testStripsTrailingUnclosedTag(): void {
    $result = (new AnswerParser())->parse('He is available now. [[scr');
    $this->assertSame([], $result['actions']);
    $this->assertSame('He is available now.', $result['text']);
  }

  /**
   * Tags are recognised regardless of case.
   */
  public function testUppercaseTagsRecognised(): void {
    $result = (new AnswerParser())->parse('See [[Scroll:Experience]] and [[CV]]');
    $this->assertSame([['type' => 'scroll', 'target' => 'experience'], ['type' => 'cv']], $result['actions']);
    $this->assertSame('See and', $result['text']);
  }

  /**
   * Repeated tags produce one action.
   */
  public function testDeduplicatesActions(): void {
    $result = (new AnswerParser())->parse('[[cv]] a [[cv]]');
    $this->assertSame([['type' => 'cv']], $result['actions']);
    $this->assertSame('a', $result['text']);
  }

  /**
   * Stripping tags leaves no double or trailing spaces.
   */
  public function testCollapsesWhitespace(): void {
    $result = (new AnswerParser())->parse("Hi  [[cv]]  there.\n\nNext [[email]] ");
    $this->assertSame("Hi there.\n\nNext", $result['text']);
  }

}
