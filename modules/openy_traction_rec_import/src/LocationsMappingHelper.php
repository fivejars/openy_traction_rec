<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Contains related to mapping Traction Rec locations functionality.
 */
class LocationsMappingHelper {

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructors LocationsMappingHelper.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Build the location map.
   *
   * @return array
   *   The mapping array.
   */
  public function getMapping(): array {
    // We'll take each config entry: salesforce_id:drupal_id:comment and convert
    // it to [salesforce_id => ['drupal_id' => id, 'comment' => comment]] so
    // we can use the salesforce_id as the index.
    $locations_map_config = $this->configFactory->get('openy_traction_rec_import.settings')->get('locations') ?? [];
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
    return $locations_map;
  }

  /**
   * Return mapped TractionRec locations id's.
   *
   * @return arraystring
   *   The mapped TractionRec locations id's.
   */
  public function getMappedIds(): array {
    $mapping = $this->getMapping();
    return array_keys($mapping);
  }

  /**
   * Return mapped Drupal location node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The mapped Drupal location node.
   */
  public function getMappedNode(string $location_id): NodeInterface|null {
    $locations_map = $this->getMapping();
    $nid = $locations_map[$location_id]['drupal_id'] ?? NULL;

    if (!$nid) {
      return NULL;
    }

    /** @var ?\Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if (!$node) {
      throw new \Exception("Location node not found: {$locations_map[$location_id]['comment']}");
    }

    return $node;
  }

}
