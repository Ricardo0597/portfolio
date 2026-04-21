<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\EventSubscriber;

use Drupal\Core\Extension\ExtensionList;
use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Attaches CV PDF stylesheet to Entity Print output for profile nodes.
 */
final class EntityPrintCssSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an EntityPrintCssSubscriber.
   */
  public function __construct(
    private readonly ExtensionList $themeList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [PrintEvents::CSS_ALTER => 'onCssAlter'];
  }

  /**
   * Attaches cv-pdf library when printing a profile node.
   */
  public function onCssAlter(PrintCssAlterEvent $event): void {
    foreach ($event->getEntities() as $entity) {
      if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'profile') {
        $event->getBuild()['#attached']['library'][] = 'portfolio_theme/cv-pdf';
        return;
      }
    }
  }

}
