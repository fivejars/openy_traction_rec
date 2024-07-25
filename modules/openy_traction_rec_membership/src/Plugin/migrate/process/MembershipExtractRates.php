<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_membership\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Extract rates from Membership price description plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "tr_membership_extract_rates"
 * )
 */
class MembershipExtractRates extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    preg_match_all('/(Join Fee|Monthly Fee)\:[\s]*\$(\d+)/i', (string) $value, $matches);

    if (empty($matches[1])) {
      return [
        'join' => 0,
        'monthly' => 0,
      ];
    }

    $rates = [];
    foreach ($matches[1] as $key => $label) {
      $rates[$label] = $matches[2][$key] ?? 0;
    }

    return [
      'join' => ($rates['Join Fee'] ?? 0),
      'monthly' => ($rates['Monthly Fee'] ?? 0),
    ];
  }

}
