<?php
declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit\Assistant;

use Drupal\Core\Site\Settings;
use Drupal\portfolio_core\Assistant\AssistantKeys;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the assistant API key lookup.
 */
#[CoversClass(AssistantKeys::class)]
#[Group('portfolio_core')]
final class AssistantKeysTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Real keys may exist in the environment (.ddev/.env).
    putenv('GROQ_API_KEY');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv('GROQ_API_KEY');
    parent::tearDown();
  }

  /**
   * A key in settings.php wins over the environment.
   */
  public function testSettingsWinOverEnv(): void {
    putenv('GROQ_API_KEY=env-key');
    $keys = new AssistantKeys(new Settings(['portfolio_assistant' => ['groq_api_key' => 'settings-key']]));
    $this->assertSame('settings-key', $keys->get('groq'));
  }

  /**
   * Without a settings key the environment variable is used.
   */
  public function testFallsBackToEnv(): void {
    putenv('GROQ_API_KEY=env-key');
    $this->assertSame('env-key', (new AssistantKeys(new Settings([])))->get('groq'));
  }

  /**
   * No key anywhere returns NULL.
   */
  public function testMissingKeyReturnsNull(): void {
    $this->assertNull((new AssistantKeys(new Settings([])))->get('groq'));
  }

  /**
   * Empty strings count as missing.
   */
  public function testEmptyStringIsTreatedAsMissing(): void {
    putenv('GROQ_API_KEY=');
    $keys = new AssistantKeys(new Settings(['portfolio_assistant' => ['groq_api_key' => '']]));
    $this->assertNull($keys->get('groq'));
  }

}
