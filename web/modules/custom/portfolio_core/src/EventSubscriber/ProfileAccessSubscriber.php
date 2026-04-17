<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects direct /node/{nid} access on profile nodes to /about.
 *
 * Ensures the profile content type is only ever served at its canonical
 * alias (/about) and not at the system path (/node/{nid}).
 */
final class ProfileAccessSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a ProfileAccessSubscriber.
   */
  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  /**
   * Redirects /node/{nid} for profile nodes to /about.
   */
  public function onRequest(RequestEvent $event): void {
    if ($this->routeMatch->getRouteName() !== 'entity.node.canonical') {
      return;
    }

    $node = $this->routeMatch->getParameter('node');
    if (!$node) {
      return;
    }

    if (is_numeric($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if (!$node || $node->bundle() !== 'profile') {
      return;
    }

    $request = $event->getRequest();
    if ($request->getPathInfo() === '/about') {
      return;
    }

    $event->setResponse(new RedirectResponse('/about', 301));
  }

}
