<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block displaying featured skills.
 *
 * @Block(
 *   id = "portfolio_current_skills",
 *   admin_label = @Translation("Portfolio: Current Skills"),
 *   category = @Translation("Portfolio"),
 * )
 */
final class CurrentSkillsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a CurrentSkillsBlock.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $nids = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'skill')
      ->condition('status', 1)
      ->condition('field_featured', 1)
      ->sort('field_skill_level', 'DESC')
      ->execute();

    if (empty($nids)) {
      return [];
    }

    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);

    $items = [];
    foreach ($nodes as $node) {
      $category_terms = $node->get('field_category')->referencedEntities();
      $category_label = !empty($category_terms) ? $category_terms[0]->label() : '';

      $items[] = [
        '#type' => 'component',
        '#component' => 'portfolio_theme:skill-card',
        '#props' => [
          'name'       => $node->label(),
          'level'      => (int) $node->get('field_skill_level')->value,
          'category'   => $category_label,
          'icon_class' => $node->get('field_icon_class')->value ?? '',
        ],
        '#cache' => [
          'tags' => $node->getCacheTags(),
        ],
      ];
    }

    return [
      '#theme_wrappers' => ['container'],
      '#attributes'     => ['class' => ['skills-block']],
      'items'           => $items,
      '#cache'          => [
        'tags' => ['node_list:skill'],
      ],
    ];
  }

}
