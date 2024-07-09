<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\openy_traction_rec_import\LocationsMappingHelper;
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
   * The locations mapping helper.
   */
  protected LocationsMappingHelper $locationsMapping;

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
   * @param \Drupal\openy_traction_rec_import\LocationsMappingHelper $locations_mapping
   *   The locations mapping helper.
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LocationsMappingHelper $locations_mapping) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->locationsMapping = $locations_mapping;
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
      $container->get('openy_traction_rec_import.locations_mapping')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $location_name = $value[0] ?? '';
    $location_id = $value[1] ?? '';

    try {
      // First try to choose the location from the map...
      $node = $this->locationsMapping->getMappedNode($location_id);

      // If we can't find a location mapping, try matching by Title.
      if (!$node) {
        $node_storage = $this->entityTypeManager->getStorage('node');

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
