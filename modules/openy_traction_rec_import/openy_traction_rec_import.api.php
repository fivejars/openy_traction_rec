<?php

/**
 * @file
 * Contains hook examples for the OpenY TractionRec import module.
 */

declare(strict_types = 1);

/**
 * Alter migrations list before import.
 *
 * @param \Drupal\migrate\Plugin\Migration[] $migrations
 *   The migrations list.
 * @param array $options
 *   The migrate import options.
 */
function hook_openy_traction_rec_import_migrations_list_alter(array &$migrations, array $options): void {
  if (!empty($options['sync'])) {
    return;
  }
  unset($migrations['some_migration_id_that_run_only_by_sync']);
}
