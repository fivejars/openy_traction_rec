<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_membership;

use Drupal\openy_traction_rec_import\TractionRecFetcher;

/**
 * Contains related to fetching from Membership Traction Rec functionality.
 */
class MembershipFetcher extends TractionRecFetcher {

  /**
   * Result json directory path.
   */
  protected string $storagePath = 'private://traction_rec_membership_import/json/';

  /**
   * Override the queue of methods that should be run during fetch.
   */
  protected function getQueue(): array {
    return [
      'fetchMemberships',
    ];
  }

  /**
   * Fetches memberships.
   */
  public function fetchMemberships(): array {
    $result = $this->tractionRec->loadMemberships();

    if (empty($result['records'])) {
      return [];
    }

    $this->stripExcludedMemberships($result);
    $this->dumpToJson($this->groupByCategory($result['records']), $this->buildFilename('memberships'));
    return $result;
  }

  /**
   * Remove excluded memberships from results.
   */
  private function stripExcludedMemberships(array &$result): void {
    $exclude = (array) $this->configFactory->get('openy_traction_rec_import.settings')
      ->get('memberships.exclude');

    if (empty($exclude)) {
      return;
    }

    $result['excluded'] = [];
    foreach ($result['records'] as $key => $record) {
      if (!in_array($record['Id'], $exclude)) {
        continue;
      }

      unset($result['records'][$key]);
      $result['totalSize'] += -1;
      $result['excluded'][] = $record['Id'];
    }

    $result['records'] = array_values($result['records']);
  }

  /**
   * Group memberships by category.
   */
  private function groupByCategory(array $records): array {
    $result = [];
    foreach ($records as $record) {
      $category_id = $record['Category']['Id'] ?? NULL;

      if (empty($category_id)) {
        continue;
      }

      if (empty($result[$category_id])) {
        $result[$category_id] = $record['Category'] + ['memberships' => []];
      }

      unset($record['Category']);
      $result[$category_id]['memberships'][] = $record;
    }
    return array_values($result);
  }

}
