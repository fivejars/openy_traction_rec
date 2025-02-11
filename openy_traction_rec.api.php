<?php

/**
 * @file
 * Contains hook examples for the TR module.
 */

declare(strict_types=1);

/**
 * Alter Traction Rec API query string before execution.
 *
 * @param string $query
 *   The query string.
 * @param string $context
 *   The custom context (by default method name).
 */
function hook_openy_traction_rec_api_query_alter(string &$query, string $context): void {
  if ($context !== 'loadLocations') {
    return;
  }

  $query .= ' WHERE TREX1__Available_Online__c = true';
}
