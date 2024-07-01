<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Sessions capacity plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "tr_availability"
 * )
 */
class Availability extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    try {
      if ($row->getSource()['unlimited_capacity']) {
        return 100;
      }

      // Traction Rec can allow classes to be overbooked, resulting in a value
      // for Total_Capacity_Available that's < 0. We don't want that.
      return max((int) $value, 0);
    }
    catch (\Exception $e) {
      throw new MigrateSkipRowException($e->getMessage());
    }
  }

}
