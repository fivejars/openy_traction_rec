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

}
