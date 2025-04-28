<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;

/**
 * Convert data to field_online_registration_date session's field.
 *
 * @MigrateProcessPlugin(
 *   id = "tr_online_registration_time"
 * )
 */
class OnlineRegistrationTime extends SessionTime {

  /**
   * {@inheritdoc}
   */
  public function transform(
    $value,
    MigrateExecutableInterface $migrate_executable,
    Row $row,
    $destination_property
  ) {
    $value = $row->getSource();

    if (empty($value['online_registration_date_from']) || empty($value['online_registration_date_to'])) {
      throw new MigrateSkipRowException('Online registration dates cannot be empty for session');
    }

    // Default time.
    if (empty($value['online_registration_time_from'])) {
      $value['online_registration_time_from'] = '07:00 AM';
    }

    try {
      $start_date = $this->convertDate(
        $value['online_registration_date_from'] . ' ' . $value['online_registration_time_from']
      );

      $end_date = $value['online_registration_date_to'];
      $end_time = $value['online_registration_time_to'] ?? '11:59 pm';
      $end_date = $this->convertDate($end_date . ' ' . $end_time);

      return [
        'value' => $start_date->format('Y-m-d\TH:i:s'),
        'end_value' => $end_date->format('Y-m-d\TH:i:s'),
      ];
    }
    catch (\Exception $e) {
      throw new MigrateSkipRowException($e->getMessage());
    }
  }

}
