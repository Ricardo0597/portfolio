<?php

declare(strict_types=1);

namespace Drupal\portfolio_core\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the /api/portfolio/projects endpoint.
 *
 * Returns published project nodes as JSON with full cache metadata.
 *
 * @RestResource(
 *   id = "portfolio_projects",
 *   label = @Translation("Portfolio: Projects listing"),
 *   uri_paths = {
 *     "canonical" = "/api/portfolio/projects"
 *   }
 * )
 */
final class ProjectsResource extends ResourceBase {

  /**
   * Constructs a ProjectsResource.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   JSON list of published projects with cache metadata.
   */
  public function get(): ResourceResponse {
    $storage = $this->entityTypeManager->getStorage('node');

    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'project')
      ->condition('status', 1)
      ->sort('field_start_date', 'DESC')
      ->execute();

    $projects = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      $stack_terms = $node->get('field_stack')->referencedEntities();
      $stack_labels = array_map(fn($t) => $t->label(), $stack_terms);

      $status_terms = $node->get('field_status')->referencedEntities();
      $status_label = !empty($status_terms) ? $status_terms[0]->label() : '';

      $demo_url_field = $node->get('field_demo_url');
      $repo_url_field = $node->get('field_repo_url');

      $projects[] = [
        'id'       => (int) $node->id(),
        'title'    => $node->label(),
        'summary'  => $node->get('field_summary')->value ?? '',
        'stack'    => $stack_labels,
        'status'   => $status_label,
        'demo_url' => !$demo_url_field->isEmpty() ? $demo_url_field->first()->getUrl()->toString() : NULL,
        'repo_url' => !$repo_url_field->isEmpty() ? $repo_url_field->first()->getUrl()->toString() : NULL,
        'path'     => $node->toUrl()->toString(),
      ];
    }

    $response = new ResourceResponse(['data' => $projects], 200);

    $cache_meta = new CacheableMetadata();
    $cache_meta->addCacheTags(['node_list:project']);
    $cache_meta->addCacheContexts(['url.query_args']);
    $response->addCacheableDependency($cache_meta);

    return $response;
  }

}
