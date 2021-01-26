<?php

namespace Drupal\ypkc_salesforce_import\Plugin\migrate\process;

use DateTimeZone;
use Drupal\Component\Datetime\DateTimePlus;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Modified version of OpenYPefSchedule for iterator plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "sf_session_time"
 * )
 */
class SessionTime extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = $row->getSource();

    if (empty($value['start_date']) || empty($value['start_time']) || empty($value['days'])) {
      return null;
    }

    $start_date = $this->convertDate($value['start_date'] . ' ' . $value['start_time']);
    $end_date = $this->convertDate($value['end_date'] . ' ' . '11:59 pm');

    $days = explode(';', $value['days']);
    $days = array_map('strtolower', $days);

    $paragraph = Paragraph::create([
      'type' => 'session_time',
      'field_session_time_actual' => 1,
      'field_session_time_days' => $days,
      'field_session_time_date' => [
        'value' => $start_date,
        'end_value' => $end_date,
      ],
    ]);
    $paragraph->isNew();
    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

  /**
   * Converts date to the DB format.
   *
   * @param string $datetime
   *   The date.
   *
   * @return mixed
   *   Formatted date string.
   */
  protected function convertDate(string $datetime) {
    $site_timezone = \Drupal::config('system.date')->get('timezone.default');

    return DateTimePlus::createFromFormat('Y-m-d h:i a', $datetime, $site_timezone)
      ->setTimezone(new DateTimeZone('UTC'))
      ->format('Y-m-d\TH:i:s');
  }

}
