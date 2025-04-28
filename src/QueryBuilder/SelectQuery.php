<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec\QueryBuilder;

/**
 * Traction Rec Select Query Builder.
 */
class SelectQuery implements QueryBuilderInterface {

  /**
   * The table to query.
   */
  protected string $table;

  /**
   * The fields to select.
   */
  protected array $fields = [];

  /**
   * The conditions for the query.
   */
  protected array $conditions = [];

  /**
   * Custom conditions for the query.
   */
  private array $customConditions = [];

  /**
   * {@inheritdoc}
   */
  public function getTable(): string {
    return $this->table;
  }

  /**
   * {@inheritdoc}
   */
  public function setTable(string $table): SelectQuery {
    $this->table = $table;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addField(string $field): SelectQuery {
    $this->fields[] = $field;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addCondition(string $field, string $value, string $operator = '='): SelectQuery {
    $this->conditions[] = [
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
    ];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeCondition(string $field): SelectQuery {
    $this->conditions = array_filter($this->conditions, function ($condition) use ($field) {
      return $condition['field'] !== $field;
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addCustomCondition(string $condition): SelectQuery {
    $this->customConditions[] = $condition;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): string {
    if (empty($this->table)) {
      throw new \RuntimeException('Table is not set.');
    }

    // Build the SELECT clause.
    $select = implode(', ', $this->fields);

    // Build the WHERE clause.
    $where = [];
    foreach ($this->conditions as $condition) {
      $where[] = "{$condition['field']} {$condition['operator']} {$condition['value']}";
    }
    $where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    foreach ($this->customConditions as $custom_condition) {
      $where .= 'AND ' . $custom_condition;
    }

    // Construct the full query.
    $query = "SELECT $select FROM {$this->table} $where";

    return $query;
  }

}
