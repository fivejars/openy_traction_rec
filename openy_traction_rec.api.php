<?php

/**
 * @file
 * Contains hook examples for the TR module.
 */

declare(strict_types=1);

use Drupal\openy_traction_rec\QueryBuilder\QueryBuilderInterface;
use Drupal\openy_traction_rec\QueryBuilder\SelectQuery;

/**
 * Alter Traction Rec API query string before execution.
 *
 * @param \Drupal\openy_traction_rec\QueryBuilder\QueryBuilderInterface $query
 *   The query string.
 */
function hook_openy_traction_rec_api_query_alter(QueryBuilderInterface &$query): void {
  if ($query->getTable() !== 'TREX1__Course__c') {
    return;
  }

  if (!($query instanceof SelectQuery)) {
    return;
  }

  $query->removeCondition('TREX1__Course__c.TREX1__Available_Online__c');
}
