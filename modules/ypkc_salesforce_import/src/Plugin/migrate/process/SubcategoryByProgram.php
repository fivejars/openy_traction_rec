<?php

namespace Drupal\ypkc_salesforce_import\Plugin\migrate\process;

use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Location by title migrate plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "sf_category_by_program"
 * )
 */
class SubcategoryByProgram extends MigrationLookup implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LocationByTitle constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @param $migrate_lookup
   * @param null $migrate_stub
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, $migrate_lookup, $migrate_stub = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $migrate_lookup, $migrate_stub);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $plugin = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('migrate.lookup'),
      $container->get('migrate.stub')
    );

    $plugin->setEntityTypeManager($container->get('entity_type.manager'));

    return $plugin;
  }

  /**
   * Setter for the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    try {
      $storage = $this->entityTypeManager->getStorage('mapping');
      $query = $storage->getQuery();
      $query->condition('field_tr_program_id', $value);
      $query->condition('type', 'tr_program_category_tag');
      $result = $query->execute();

      if (!$result) {
        throw new MigrateSkipRowException("Can't find a category");
      }

      $mapping = reset($result);
      $mapping = $storage->load($mapping);

      if (!$mapping) {
        throw new MigrateSkipRowException("Can't find a category");
      }

      $program_category_id = $mapping->get('field_tr_program_category')->value;
      $destination_ids = parent::transform($program_category_id, $migrate_executable, $row, $destination_property);
      if (!$destination_ids) {
        throw new MigrateSkipRowException('Category not found!');
      }

      return $destination_ids;
    }
    catch (\Exception $e) {
      throw new MigrateSkipRowException($e->getMessage());
    }
  }

}
