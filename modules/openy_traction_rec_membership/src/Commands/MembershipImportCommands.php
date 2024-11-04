<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_membership\Commands;

use Drupal\openy_traction_rec_import\Commands\OpenyTractionRecImportCommands;

/**
 * Membership Traction Rec Membership import drush commands.
 */
class MembershipImportCommands extends OpenyTractionRecImportCommands {

  /**
   * Executes the Traction Rec Membership import.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command openy-tr-membership:import
   * @aliases tr-membership:import
   *
   * @option sync Sync source and destination. Delete destination records that
   *   do not exist in the source.
   *
   * @return bool
   *   Execution status.
   *
   * @throws \Exception
   */
  // phpcs:ignore
  public function import(array $options): bool {
    // Create additional command and run parent logic with new constructor.
    return parent::import($options);
  }

  /**
   * Executes the Traction Rec Membership rollback.
   *
   * @command openy-tr-membership:rollback
   * @aliases tr-membership:rollback
   */
  // phpcs:ignore
  public function rollback(): void {
    // Create additional command and run parent logic with new constructor.
    parent::rollback();
  }

  /**
   * Resets the Traction Rec Membership import lock.
   *
   * @command openy-tr-membership:reset-lock
   * @aliases tr-membership:reset-lock
   */
  // phpcs:ignore
  public function resetLock(): void {
    // Create additional command and run parent logic with new constructor.
    parent::resetLock();
  }

  /**
   * Run Traction Rec Membership clean up actions.
   *
   * @param array $options
   *   The array of command options.
   *
   * @command openy-tr-membership:clean-up
   * @aliases tr-membership:clean-up
   */
  // phpcs:ignore
  public function cleanUp(array $options): void {
    // Create additional command and run parent logic with new constructor.
    parent::cleanUp($options);
  }

  /**
   * Run Traction Rec Membership fetcher.
   *
   * @command openy-tr-membership:fetch-all
   * @aliases tr-membership:fetch
   */
  // phpcs:ignore
  public function fetch(): void {
    // Create additional command and run parent logic with new constructor.
    parent::fetch();
  }

}
