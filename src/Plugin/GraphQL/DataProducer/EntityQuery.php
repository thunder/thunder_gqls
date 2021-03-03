<?php

namespace Drupal\thunder\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\thunder_schema\Wrappers\QueryConnection;
use GraphQL\Error\UserError;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds and executes Drupal entity query.
 *
 * @DataProducer(
 *   id = "entity_list_producer",
 *   name = @Translation("Load entities"),
 *   description = @Translation("Loads entities."),
 *   produces = @ContextDefinition("entities",
 *     label = @Translation("Entities")
 *   ),
 *   consumes = {
 *     "type" = @ContextDefinition("string",
 *       label = @Translation("Entity type")
 *     ),
 *     "limit" = @ContextDefinition("integer",
 *       label = @Translation("Limit"),
 *       required = FALSE
 *     ),
 *     "offset" = @ContextDefinition("integer",
 *       label = @Translation("Offset"),
 *       required = FALSE
 *     ),
 *     "conditions" = @ContextDefinition("any",
 *       label = @Translation("Conditions"),
 *       multiple = TRUE,
 *       required = FALSE
 *     ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Entity languages(s)"),
 *       multiple = TRUE,
 *       required = FALSE
 *     ),
 *     "bundles" = @ContextDefinition("any",
 *       label = @Translation("Entity bundle(s)"),
 *       multiple = TRUE,
 *       required = FALSE
 *     )
 *   }
 * )
 */
class EntityQuery extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum number of results.
   *
   * To prevent denial of service attacks with loading too many items.
   */
  const MAX_LIMIT = 100;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * EntityLoad constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param array $pluginDefinition
   *   The plugin definition array.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    array $pluginDefinition,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Resolves the entity query.
   *
   * @param string $type
   *   Entity type.
   * @param int $limit
   *   Maximum number of queried entities.
   * @param int|null $offset
   *   Offset to start with.
   * @param array|null $conditions
   *   List of conditions to filter the entities.
   * @param string|null $language
   *   Language of queried entities.
   * @param array|null $bundles
   *   List of bundles to be filtered.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $metadata
   *   The metadata object for caching.
   *
   * @return \Drupal\thunder_schema\Wrappers\QueryConnection
   *   The list of ids that match this query.
   *
   * @throws \GraphQL\Error\UserError
   *   No bundles defined for given entity type.
   */
  public function resolve(string $type, int $limit = 10, ?int $offset = NULL, ?array $conditions = NULL, ?string $language = NULL, ?array $bundles = NULL, RefinableCacheableDependencyInterface $metadata): array {

    $storage = $this->entityTypeManager->getStorage($type);
    $entityType = $storage->getEntityType();

    // Make sure that max limit is not crossed.
    if ($limit > static::MAX_LIMIT) {
      $limit = static::MAX_LIMIT;
    }

    // Make sure offset is zero or positive.
    if (!isset($offset) || $offset < 0) {
      $offset = 0;
    }

    $entity_type = $this->entityTypeManager->getStorage($type);
    $query = $entity_type->getQuery()
      ->range($offset, $limit);

    // Ensure that access checking is performed on the query.
    $query->accessCheck(TRUE);

    if (isset($bundles)) {
      $bundle_key = $entity_type->getEntityType()->getKey('bundle');
      if (!$bundle_key) {
        throw new UserError('No bundles defined for given entity type.');
      }
      $query->condition($bundle_key, $bundles, "IN");
    }
    if (isset($language)) {
      $query->condition('langcode', $language);
    }

    foreach ($conditions as $condition) {
      $operation = isset($condition['operator']) ? $condition['operator'] : NULL;
      $query->condition($condition['field'], $condition['value'], $operation);
    }

    $metadata->addCacheTags($entityType->getListCacheTags());
    $metadata->addCacheContexts($entityType->getListCacheContexts());

    return new QueryConnection($query);
    /*$ids = $query->execute();

    return $ids;*/
  }

}
