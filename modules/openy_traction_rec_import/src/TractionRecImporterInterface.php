<?php

namespace Drupal\openy_traction_rec_import;

/**
 * Traction Rec importer Interface.
 */
interface TractionRecImporterInterface {

  /**
   * Imports all fetched JSON files in the directory.
   *
   * @param string $dir
   *   The directory with fetched JSON data.
   * @param array $options
   *   The array of import options.
   */
  public function directoryImport(string $dir, array $options = []);

  /**
   * Check traction_rec migrations statuses.
   */
  public function checkMigrationsStatus(): bool;

  /**
   * Checks import status.
   *
   * @return bool
   *   TRUE if the traction_rec import is enabled.
   */
  public function isEnabled(): bool;

  /**
   * Checks Traction Rec JSON files backup status.
   *
   * @return bool
   *   TRUE if JSON files backup is enabled.
   */
  public function isBackupEnabled(): bool;

  /**
   * Acquires Traction Rec import lock.
   *
   * @return bool
   *   Lock status.
   */
  public function acquireLock(): bool;

  /**
   * Releases Traction Rec lock.
   */
  public function releaseLock();

  /**
   * Provides a list of directories with the fetched JSON files.
   *
   * @return array
   *   The array of directories paths.
   */
  public function getJsonDirectoriesList(): array;

}
