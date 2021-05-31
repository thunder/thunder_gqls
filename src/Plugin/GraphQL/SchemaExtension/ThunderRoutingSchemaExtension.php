<?php

namespace Drupal\thunder_gqls\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverRegistryInterface;

/**
 * The routing schema extension.
 *
 * @SchemaExtension(
 *   id = "thunder_routing",
 *   name = "Routing extension",
 *   description = "Adds routing of URLs.",
 *   schema = "thunder"
 * )
 */
class ThunderRoutingSchemaExtension extends ThunderSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry) {
    parent::registerResolvers($registry);

    if ($this->dataProducerManager->hasDefinition('access_unpublished_route_load')) {
      $this->addFieldResolverIfNotExists('Query', 'page', $this->builder->compose(
        $this->builder->produce('access_unpublished_route_load')
          ->map('path', $this->builder->fromArgument('path'))
          ->map('token', $this->builder->fromArgument('auHash')),
        $this->builder->produce('route_entity')
          ->map('url', $this->builder->fromParent())
      ));
    }
    else {
      $this->addFieldResolverIfNotExists('Query', 'page', $this->builder->compose(
        $this->builder->produce('route_load')
          ->map('path', $this->builder->fromArgument('path')),
        $this->builder->produce('route_entity')
          ->map('url', $this->builder->fromParent())
      ));
    }
  }

}
