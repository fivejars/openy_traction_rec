<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Modified version of OpenYPefSchedule for iterator plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "tr_years_to_month"
 * )
 */
class YearsToMonths extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_numeric($value)) {
      return $value * 12;
    }

    return NULL;
  }

}
