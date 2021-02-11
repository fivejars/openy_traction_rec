<?php

namespace Drupal\ypkc_salesforce_import\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Location by mapping plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "sf_location_by_mapping"
 * )
 */
class LocationByMapping extends MigrationLookup implements ContainerFactoryPluginInterface {

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, $migrate_lookup, $migrate_stub = NULL, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $migrate_lookup, $migrate_stub);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('migrate.lookup'),
      $container->get('migrate.stub'),
      $container->get('entity_type.manager')
    );
  }

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $destination_ids = parent::transform($value, $migrate_executable, $row, $destination_property);
    if (!$destination_ids) {
      throw new MigrateSkipRowException('Location not found!');
    }

    $mapping_storage = $this->entityTypeManager->getStorage('mapping');
    $mapping = $mapping_storage->load($destination_ids);
    if (!$mapping || $mapping->get('field_location_node')->isEmpty()) {
      throw new MigrateSkipRowException('Location not found!');
    }

    $location_node = $mapping->get('field_location_node')->entity;
    return $location_node->id();
  }

}
