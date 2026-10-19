<?php
declare(strict_types=1);

namespace Drupal\Tests\portfolio_core\Unit;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\portfolio_core\EventSubscriber\DisabledSectionsSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the config-driven redirects for disabled site sections.
 */
#[CoversClass(DisabledSectionsSubscriber::class)]
#[Group('portfolio_core')]
final class DisabledSectionsSubscriberTest extends UnitTestCase {

  private const ENABLED_CONFIG = [
    'disabled_bundles' => ['project', 'article', 'feature_demo', 'skill'],
    'disabled_routes' => [
      'view.portfolio_projects.page_1',
      'view.portfolio_skills.page_1',
    ],
  ];

  /**
   * Builds the subscriber with the given settings.
   */
  private function subscriber(array $settings = self::ENABLED_CONFIG): DisabledSectionsSubscriber {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn(string $key) => $settings[$key] ?? NULL);
    $config->method('getCacheTags')->willReturn(['config:portfolio_core.settings']);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(-1);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('portfolio_core.settings')->willReturn($config);

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    return new DisabledSectionsSubscriber($config_factory, $account);
  }

  /**
   * Disabled bundles redirect temporarily.
   */
  public function testDisabledBundleRedirects302(): void {
    $this->assertSame(302, $this->subscriber()->resolveStatus('entity.node.canonical', 'project', FALSE, 'html', FALSE));
  }

  /**
   * Bundles not in the config are left alone.
   */
  public function testEnabledBundleNotRedirected(): void {
    $this->assertNull($this->subscriber()->resolveStatus('entity.node.canonical', 'page', FALSE, 'html', FALSE));
  }

  /**
   * Disabled routes redirect temporarily.
   */
  public function testDisabledRouteRedirects302(): void {
    $this->assertSame(302, $this->subscriber()->resolveStatus('view.portfolio_projects.page_1', NULL, FALSE, 'html', FALSE));
  }

  /**
   * The profile outside the front page redirects permanently.
   */
  public function testProfileOffFrontRedirects301(): void {
    $this->assertSame(301, $this->subscriber()->resolveStatus('entity.node.canonical', 'profile', FALSE, 'html', FALSE));
  }

  /**
   * The profile at the front page is served.
   */
  public function testProfileOnFrontNotRedirected(): void {
    $this->assertNull($this->subscriber()->resolveStatus('entity.node.canonical', 'profile', TRUE, 'html', FALSE));
  }

  /**
   * Users who bypass node access can view disabled nodes.
   */
  public function testBypassUserNotRedirectedOnNode(): void {
    $this->assertNull($this->subscriber()->resolveStatus('entity.node.canonical', 'article', FALSE, 'html', TRUE));
  }

  /**
   * The profile alias redirects even for users who bypass node access.
   */
  public function testBypassUserStillRedirectedOnProfileAlias(): void {
    $this->assertSame(301, $this->subscriber()->resolveStatus('entity.node.canonical', 'profile', FALSE, 'html', TRUE));
  }

  /**
   * Users who bypass node access can view disabled listings.
   */
  public function testBypassUserNotRedirectedOnRoute(): void {
    $this->assertNull($this->subscriber()->resolveStatus('view.portfolio_projects.page_1', NULL, FALSE, 'html', TRUE));
  }

  /**
   * The front path never redirects, even to a disabled node (no loop).
   */
  public function testFrontPathNeverRedirectsDisabledBundle(): void {
    $this->assertNull($this->subscriber()->resolveStatus('entity.node.canonical', 'project', TRUE, 'html', FALSE));
  }

  /**
   * The front path never redirects, even to a disabled route (no loop).
   */
  public function testFrontPathNeverRedirectsDisabledRoute(): void {
    $this->assertNull($this->subscriber()->resolveStatus('view.portfolio_projects.page_1', NULL, TRUE, 'html', FALSE));
  }

  /**
   * Non-HTML formats (REST, feeds) are never redirected.
   */
  public function testNonHtmlFormatIsIgnored(): void {
    $this->assertNull($this->subscriber()->resolveStatus('entity.node.canonical', 'project', FALSE, 'json', FALSE));
  }

  /**
   * Edit forms of disabled bundles stay reachable.
   */
  public function testEditRouteIgnored(): void {
    $this->assertNull($this->subscriber()->resolveStatus('entity.node.edit_form', 'project', FALSE, 'html', FALSE));
  }

  /**
   * Empty lists re-enable every section.
   */
  public function testEmptyConfigDisablesNothing(): void {
    $subscriber = $this->subscriber(['disabled_bundles' => [], 'disabled_routes' => []]);
    $this->assertNull($subscriber->resolveStatus('entity.node.canonical', 'project', FALSE, 'html', FALSE));
  }

  /**
   * Redirects are invalidated when the settings change.
   */
  public function testRedirectCarriesConfigCacheTag(): void {
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')
      ->willReturnCallback(fn($route, $params = [], $options = [], $collect = FALSE) => $collect ? (new GeneratedUrl())->setGeneratedUrl('/') : '/');
    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);

    $request = Request::create('/projects');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'view.portfolio_projects.page_1');
    $event = new RequestEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
    );

    $this->subscriber()->onRequest($event);

    $response = $event->getResponse();
    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    $this->assertSame(302, $response->getStatusCode());
    $this->assertContains('config:portfolio_core.settings', $response->getCacheableMetadata()->getCacheTags());
  }

}
