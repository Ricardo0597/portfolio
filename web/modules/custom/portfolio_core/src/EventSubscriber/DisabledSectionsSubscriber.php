<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects disabled site sections and the profile alias to the front page.
 *
 * Driven by portfolio_core.settings so sections can be re-enabled through
 * config alone. Only HTML node and listing routes are affected; APIs, edit
 * forms and entity_print are left alone.
 */
final class DisabledSectionsSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a DisabledSectionsSubscriber.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after the router (priority 32) so the route is matched.
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

  /**
   * Sets a redirect response when the current route is disabled.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $route_name = (string) $request->attributes->get(RouteObjectInterface::ROUTE_NAME, '');
    $node = $request->attributes->get('node');
    $bundle = $node instanceof NodeInterface ? $node->bundle() : NULL;

    $status = $this->resolveStatus(
      $route_name,
      $bundle,
      // The raw path: '/', '/about' and '/node/1' all resolve to the same
      // system path, so PathMatcher::isFrontPage() cannot tell them apart.
      $request->getPathInfo() === '/',
      $request->getRequestFormat(),
      $this->currentUser->hasPermission('bypass node access'),
    );
    if ($status === NULL) {
      return;
    }

    $response = new LocalRedirectResponse(Url::fromRoute('<front>')->toString(), $status);
    $response->addCacheableDependency($this->configFactory->get('portfolio_core.settings'));
    $event->setResponse($response);
  }

  /**
   * Decides whether a request should be redirected to the front page.
   *
   * @param string $route_name
   *   The matched route name.
   * @param string|null $node_bundle
   *   The bundle of the route's node, if any.
   * @param bool $is_front
   *   Whether the raw request path is '/'.
   * @param string $format
   *   The request format.
   * @param bool $can_bypass
   *   Whether the user may view disabled content.
   *
   * @return int|null
   *   301 or 302 to redirect, NULL to leave the request alone.
   */
  public function resolveStatus(string $route_name, ?string $node_bundle, bool $is_front, string $format, bool $can_bypass): ?int {
    // Never redirect the front path itself: redirecting '/' to '/' would loop
    // if the front page were ever pointed at a disabled node or route.
    if ($is_front || $format !== 'html') {
      return NULL;
    }
    $config = $this->configFactory->get('portfolio_core.settings');

    if ($route_name === 'entity.node.canonical' && $node_bundle !== NULL) {
      // The profile is served at the front page (system.site:page.front).
      if ($node_bundle === 'profile') {
        return 301;
      }
      if (!$can_bypass && in_array($node_bundle, $config->get('disabled_bundles') ?? [], TRUE)) {
        return 302;
      }
      return NULL;
    }

    return !$can_bypass && in_array($route_name, $config->get('disabled_routes') ?? [], TRUE) ? 302 : NULL;
  }

}
