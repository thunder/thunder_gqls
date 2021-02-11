<?php

namespace Drupal\thunder_schema\Plugin\GraphQL\Schema;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\thunder_schema\Plugin\GraphQL\Traits\ContentElementInterfaceResolver;
use Drupal\thunder_schema\Plugin\GraphQL\Traits\ContentTypeInterfaceResolver;
use Drupal\thunder_schema\Wrappers\QueryConnection;
use GraphQL\Error\Error;

/**
 * @Schema(
 *   id = "thunder",
 *   name = "Thunder schema"
 * )
 */
class ThunderSchema extends SdlSchemaPluginBase {

  use ContentTypeInterfaceResolver;
  use ContentElementInterfaceResolver;

  /**
   * ResolverRegistryInterface.
   *
   * @var \Drupal\graphql\GraphQL\ResolverRegistryInterface
   */
  protected $registry;

  /**
   * ResolverBuilder.
   *
   * @var \Drupal\graphql\GraphQL\ResolverBuilder
   */
  protected $builder;

  /**
   * {@inheritdoc}
   */
  public function getResolverRegistry() {
    $this->builder = new ResolverBuilder();
    $this->registry = new ResolverRegistry();

    $this->addInterfaces();
    $this->addQueryFields();
    $this->addArticleFields();
    $this->addChannelFields();
    $this->addTagFields();
    $this->addImageFields();
    $this->addParagraphTextFields();
    $this->addParagraphImageFields();
    $this->addParagraphImageListFields();
    $this->addParagraphEmbedFields();

    $this->addConnectionFields('ArticleConnection');
    $this->addConnectionFields('TopicConnection');
    $this->addConnectionFields('ChannelConnection');

    $this->registry->addFieldResolver('Query', 'route', $this->builder->compose(
      $this->builder->produce('route_load')
        ->map('path', $this->builder->fromArgument('path')),
      $this->builder->produce('route_entity')
        ->map('url', $this->builder->fromParent())
    ));

    return $this->registry;
  }


  protected function addInterfaces () {
    $this->registry->addTypeResolver('ContentType', function ($value) {
      if ($value instanceof ContentEntityInterface) {
        return $this->mapBundleToSchemaName($value->bundle());
      }
      throw new Error('Could not resolve content type.');
    });

    $this->registry->addTypeResolver('ContentElement', function ($value) {
      $bundle = $value->bundle();

      if ($value instanceof ParagraphInterface) {
        if (in_array($bundle,['twitter','pinterest','instagram'])) {
          return 'ParagraphEmbed';
        }
        if (in_array($bundle,['gallery'])) {
          return 'ParagraphImageList';
        }
        return 'Paragraph' . $this->mapBundleToSchemaName($bundle);
      }

      if($value instanceof MediaInterface) {
        return $this->mapBundleToSchemaName($bundle);
      }

      throw new Error('Could not resolve element type.' . $bundle);
    });

  }

  /**
   * Takes the bundle name and returns the schema name.
   * @param $bundle_name
   *   The bundle name.
   *
   * return bundle_name
   *   Returns the mapped bundle name.
   */
  protected function mapBundleToSchemaName($bundle_name) {
    if (strpos($bundle_name, '_')) {
      preg_match_all("/\_\s*\w/", $bundle_name, $matches);
      foreach($matches[0] as $match){
        $bundle_name = str_replace($match, strtoupper($match), $bundle_name);
      }
      $bundle_name = str_replace('_', '', $bundle_name);
      return ucfirst($bundle_name);
    }
    return ucfirst($bundle_name);
  }


  protected function addChannelFields() {
    $this->addContentTypeInterfaceFields('Channel', $this->registry, $this->builder);

    $this->registry->addFieldResolver('Channel', 'content',
      $this->builder->produce('entity_reference_revisions')
        ->map('entity', $this->builder->fromParent())
        ->map('field', $this->builder->fromValue('field_paragraphs'))
    );
  }

  protected function addTagFields() {
    $this->addContentTypeInterfaceFields('Tag', $this->registry, $this->builder);
  }

  protected function addArticleFields() {
    $this->addContentTypeInterfaceFields('Article', $this->registry, $this->builder);

    $this->registry->addFieldResolver('Article', 'seoTitle',
      $this->builder->produce('property_path')
        ->map('type', $this->builder->fromValue('entity:node'))
        ->map('value', $this->builder->fromParent())
        ->map('path', $this->builder->fromValue('field_seo_title.value'))
    );

    $this->registry->addFieldResolver('Article', 'channel',
      $this->builder->produce('entity_reference')
        ->map('entity', $this->builder->fromParent())
        ->map('field', $this->builder->fromValue('field_channel'))
    );

    $this->registry->addFieldResolver('Article', 'channel',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:taxonomy_term'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_channel.entity')),
      )
    );


    $this->registry->addFieldResolver('Article', 'tags',
      $this->builder->produce('entity_reference')
        ->map('entity', $this->builder->fromParent())
        ->map('field', $this->builder->fromValue('field_tags'))
    );

    $this->registry->addFieldResolver('Article', 'content',
      $this->builder->produce('entity_reference_revisions')
        ->map('entity', $this->builder->fromParent())
        ->map('field', $this->builder->fromValue('field_paragraphs'))
    );
  }

  protected function addParagraphTextFields() {
    $this->addContentElementInterfaceFields('ParagraphText', $this->registry, $this->builder);

    $this->registry->addFieldResolver('ParagraphText', 'text',
      $this->builder->produce('property_path')
        ->map('type', $this->builder->fromValue('entity:paragraph'))
        ->map('value', $this->builder->fromParent())
        ->map('path', $this->builder->fromValue('field_text.processed'))
    );
  }

  protected function addParagraphEmbedFields() {
    $this->addContentElementInterfaceFields('ParagraphEmbed', $this->registry, $this->builder);

    $this->registry->addFieldResolver('ParagraphEmbed', 'url',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_media.entity')),
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:media'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_url.value'))
      )
    );
  }

  protected function addParagraphImageListFields() {
    $this->addContentElementInterfaceFields('ParagraphImageList', $this->registry, $this->builder);

    $this->registry->addFieldResolver('ParagraphImageList', 'images',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_media.entity')),
        $this->builder->produce('entity_reference')
          ->map('entity', $this->builder->fromParent())
          ->map('field', $this->builder->fromValue('field_media_images'))
      )
    );
  }


  protected function addImageFields() {
    $this->addContentElementInterfaceFields('Image', $this->registry, $this->builder);

    $this->registry->addFieldResolver('Image', 'copyright',
      $this->builder->produce('property_path')
        ->map('type', $this->builder->fromValue('entity:media'))
        ->map('value', $this->builder->fromParent())
        ->map('path', $this->builder->fromValue('field_copyright.value'))
    );

    $this->registry->addFieldResolver('Image', 'description',
      $this->builder->produce('property_path')
        ->map('type', $this->builder->fromValue('entity:media'))
        ->map('value', $this->builder->fromParent())
        ->map('path', $this->builder->fromValue('field_description.processed'))
    );

    $this->registry->addFieldResolver('Image', 'src',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:media'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('image_url')
          ->map('entity', $this->builder->fromParent())
      )
    );

    $this->registry->addFieldResolver('Image', 'width',
      $this->builder->produce('property_path')
        ->map('type', $this->builder->fromValue('entity:media'))
        ->map('value', $this->builder->fromParent())
        ->map('path', $this->builder->fromValue('field_image.width'))
    );

    $this->registry->addFieldResolver('Image', 'height',
      $this->builder->produce('property_path')
        ->map('type', $this->builder->fromValue('entity:media'))
        ->map('value', $this->builder->fromParent())
        ->map('path', $this->builder->fromValue('field_image.height'))

    );
  }

  protected function addParagraphImageFields() {
    $this->addContentElementInterfaceFields('ParagraphImage', $this->registry, $this->builder);

    $this->registry->addFieldResolver('ParagraphImage', 'label',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('entity_label')
          ->map('entity', $this->builder->fromParent())
      )
    );

    $this->registry->addFieldResolver('ParagraphImage', 'copyright',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:media'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_copyright.value'))
      )
    );

    $this->registry->addFieldResolver('ParagraphImage', 'description',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:media'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_description.processed'))
      )
    );

    $this->registry->addFieldResolver('ParagraphImage', 'src',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:media'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('image_url')
          ->map('entity', $this->builder->fromParent())
      )
    );

    $this->registry->addFieldResolver('ParagraphImage', 'width',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:media'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.width'))
      )
    );

    $this->registry->addFieldResolver('ParagraphImage', 'height',
      $this->builder->compose(
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:paragraph'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.entity')),
        $this->builder->produce('property_path')
          ->map('type', $this->builder->fromValue('entity:media'))
          ->map('value', $this->builder->fromParent())
          ->map('path', $this->builder->fromValue('field_image.height'))
      )
    );
  }

  protected function addQueryFields() {
    $this->registry->addFieldResolver('Query', 'article',
      $this->builder->produce('entity_load')
        ->map('type', $this->builder->fromValue('node'))
        ->map('bundles', $this->builder->fromValue(['article']))
        ->map('id', $this->builder->fromArgument('id'))
    );

    $this->registry->addFieldResolver('Query', 'all_article',
      $this->builder->produce('query_entities')
        ->map('type', $this->builder->fromArgument('type'))
        ->map('bundle', $this->builder->fromArgument('bundle'))
        ->map('offset', $this->builder->fromArgument('offset'))
        ->map('limit', $this->builder->fromArgument('limit'))
    );

    $this->registry->addFieldResolver('Query', 'channel',
      $this->builder->produce('entity_load')
        ->map('type', $this->builder->fromValue('taxonomy_term'))
        ->map('bundles', $this->builder->fromValue(['channel']))
        ->map('id', $this->builder->fromArgument('id'))
    );

    $this->registry->addFieldResolver('Query', 'all_channel',
      $this->builder->produce('query_entities')
        ->map('type', $this->builder->fromArgument('type'))
        ->map('bundle', $this->builder->fromArgument('bundle'))
        ->map('offset', $this->builder->fromArgument('offset'))
        ->map('limit', $this->builder->fromArgument('limit'))
    );

    $this->registry->addFieldResolver('Query', 'tag',
      $this->builder->produce('entity_load')
        ->map('type', $this->builder->fromValue('taxonomy_term'))
        ->map('bundles', $this->builder->fromValue(['tag']))
        ->map('id', $this->builder->fromArgument('id'))
    );

    $this->registry->addFieldResolver('Query', 'all_tag',
      $this->builder->produce('query_entities')
        ->map('type', $this->builder->fromArgument('type'))
        ->map('bundle', $this->builder->fromArgument('bundle'))
        ->map('offset', $this->builder->fromArgument('offset'))
        ->map('limit', $this->builder->fromArgument('limit'))
    );
  }

  /**
   * Function addConnectionFields - adds the connection fields.
   * @param string $type
   *   The connection type.
   */
  protected function addConnectionFields($type) {
    $this->registry->addFieldResolver($type, 'total',
      $this->builder->callback(function (QueryConnection $connection) {
        return $connection->total();
      })
    );

    $this->registry->addFieldResolver($type, 'items',
      $this->builder->callback(function (QueryConnection $connection) {
        return $connection->items();
      })
    );
  }

}
