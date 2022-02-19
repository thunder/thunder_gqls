<?php

namespace Drupal\thunder_gqls\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverRegistryInterface;

/**
 * Adds the views extension.
 *
 * @SchemaExtension(
 *   id = "thunder_views",
 *   name = "Views extension",
 *   description = "Adds the views extension.",
 *   schema = "thunder"
 * )
 */
class ThunderViewsSchemaExtension extends ThunderSchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry) {
    parent::registerResolvers($registry);

    $this->addFieldResolverIfNotExists('Query', 'views',
      $this->builder->produce('views')
        ->map('view_id', $this->builder->fromArgument('view_id'))
        ->map('display_id', $this->builder->fromArgument('display_id'))
        ->map('offset', $this->builder->fromArgument('offset'))
        ->map('page_size', $this->builder->fromArgument('page_size'))
        ->map('page', $this->builder->fromArgument('page'))
    );
  }

}
