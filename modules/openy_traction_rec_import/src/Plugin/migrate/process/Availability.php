<?php

namespace Drupal\openy_traction_rec_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Sessions capacity plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "sf_availability"
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

      return (int) $value;
    }
    catch (\Exception $e) {
      throw new MigrateSkipRowException($e->getMessage());
    }
  }

}
