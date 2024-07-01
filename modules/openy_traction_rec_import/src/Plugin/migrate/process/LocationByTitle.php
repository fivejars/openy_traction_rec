<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import\Plugin\migrate\process;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Location by title migrate plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "tr_location_by_title"
 * )
 */
class LocationByTitle extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $location_name = $value[0] ?? '';
    $location_id = $value[1] ?? '';

    // Build the location map:
    // We'll take each config entry: salesforce_id:drupal_id:comment and convert
    // it to [salesforce_id => ['drupal_id' => id, 'comment' => comment]] so
    // we can use the salesforce_id as the index.
    $locations_map_config = $this->configFactory->get('openy_traction_rec_import.settings')->get('locations');
    $locations_map = [];
    foreach ($locations_map_config as $row) {
      $row = explode(':', $row, 3);
      // If there are less than two items then this is not a valid mapping.
      if (count($row) < 2) {
        continue;
      }
      // Construct an array of the mapping config, indexed by Salesforce ID.
      $locations_map[$row[0]] = [
        'drupal_id' => $row[1],
        'comment' => $row[2] ?? $row[0] . ' => ' . $row[1],
      ];
    }

    try {
      $node_storage = $this->entityTypeManager->getStorage('node');

      // First try to choose the location from the map...
      if (!empty($locations_map) && isset($locations_map[$location_id])) {
        $node = $node_storage->load($locations_map[$location_id]['drupal_id']);
        if (!$node) {
          throw new MigrateSkipRowException("Location node not found: {$locations_map[$location_id]['comment']}");
        }
      }
      // If we can't find a location mapping, try matching by Title.
      else {
        $locations = $node_storage->getQuery()
          ->condition('title', $location_name)
          ->condition('type', ['branch', 'camp'], 'IN')
          ->range(0, 1)
          ->accessCheck(FALSE)
          ->execute();

        if (empty($locations)) {
          throw new MigrateSkipRowException("Location node not found: $location_name");
        }

        // LoadByProperties can return more than one, so just take the first.
        $locations = $node_storage->loadMultiple($locations);
        $node = reset($locations);
      }

      return ['target_id' => $node->id()];
    }
    catch (\Exception $e) {
      throw new MigrateSkipRowException($e->getMessage());
    }
  }

}
