<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec\QueryBuilder;

/**
 * Traction Rec Query Builder Interface.
 */
interface QueryBuilderInterface {

  /**
   * Gets the table for the query.
   *
   * @return string
   *   The table name.
   */
  public function getTable(): string;

  /**
   * Sets the table for the query.
   *
   * @param string $table
   *   The table name.
   *
   * @return $this
   */
  public function setTable(string $table): QueryBuilderInterface;

  /**
   * Builds the SQL query string.
   *
   * @return string
   *   The constructed SQL query.
   */
  public function build(): string;

  /**
   * Adds a field to the query.
   *
   * @param string $field
   *   The field name.
   *
   * @return $this
   */
  public function addField(string $field): SelectQuery;

  /**
   * Adds a condition to the query.
   *
   * @param string $field
   *   The field name.
   * @param string $value
   *   The value to compare.
   * @param string $operator
   *   (Optional) The comparison operator. Defaults to '='.
   *
   * @return $this
   */
  public function addCondition(string $field, string $value, string $operator = '='): SelectQuery;

  /**
   * Removes a condition from the query.
   *
   * @param string $field
   *   The field name to remove conditions for.
   *
   * @return $this
   */
  public function removeCondition(string $field): SelectQuery;

  /**
   * Adds custom conditions in the query.
   *
   * Used to add complex conditions to the query. e.g. "OR" group:
   * $query->addCustomCondition('(TREX1__Course_Session__r.TREX1__Num_Option_Entitlements__c <= 1 OR TREX1__Course_Session__r.TREX1__Num_Option_Entitlements__c = null)');
   *
   * @param string $condition
   *   The custom condition to be added to the query.
   *
   * @return $this
   */
  public function addCustomCondition(string $condition): SelectQuery;
}
