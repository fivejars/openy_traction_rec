<?php

namespace Drupal\ypkc_salesforce\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Excludes non-traction rec entities from being indexes.
 *
 * @SearchApiProcessor(
 *   id = "ypkc_traction_rec_datasource",
 *   label = @Translation("Traction Rec Data Source"),
 *   description = @Translation("Exclude entities from being indexed, if they don't have Traction Rec data source."),
 *   stages = {
 *     "alter_items" = -50
 *   }
 * )
 */
class TractionRecDataSourceProcessor extends ProcessorPluginBase {

  /**
   * {@inheritDoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();

      if (!$object->hasField('field_data_source')) {
        continue;
      }

      // Non-traction rec sessions must not be used in Activity Finder.
      if ($object->get('field_data_source')->value != 'traction_rec') {
        unset($items[$item_id]);
      }
    }
  }

}
