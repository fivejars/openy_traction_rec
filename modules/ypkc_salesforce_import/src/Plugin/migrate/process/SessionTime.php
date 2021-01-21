<?php

namespace Drupal\ypkc_salesforce_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Modified version of OpenYPefSchedule for iterator plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "ypkc_session_time"
 * )
 */
class SessionTime extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = $row->getSource();
    sleep(0);


    // @TODO: Finish.
    $paragraph = Paragraph::create([
      'type' => 'session_time',
      'field_session_time_actual' => 1,
      'field_session_time_days' => $days,
      'field_session_time_date' => [
        'value' => $startDate,
        'end_value' => $endDate,
      ],
    ]);
    $paragraph->isNew();
    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

}
