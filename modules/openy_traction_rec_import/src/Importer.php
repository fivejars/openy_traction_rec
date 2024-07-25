<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\MigrateExecutable;

/**
 * Wrapper for Traction Rec import operations.
 */
class Importer implements TractionRecImporterInterface {

  use StringTranslationTrait;

  /**
   * The name used to identify the lock.
   */
  const LOCK_NAME = 'tr_import';

  /**
   * The name of the migrate group.
   */
  const MIGRATE_GROUP = 'tr_import';

  /**
   * The path to a directory with JSON files for import.
   */
  const SOURCE_DIRECTORY = 'private://traction_rec_import/json/';

  /**
   * The path to a directory where JSON files must be placed for import.
   */
  const TARGET_DIRECTORY = 'private://traction_rec_import/';

  /**
   * The path to a directory for processed JSON files.
   */
  const BACKUP_DIRECTORY = 'private://traction_rec_import/backup/';

  /**
   * The lock backend.
   */
  protected LockBackendInterface $lock;

  /**
   * Logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Import status.
   */
  protected bool $isEnabled = FALSE;

  /**
   * JSON backup status.
   */
  protected bool $isBackupEnabled = FALSE;

  /**
   * JSON backup limit.
   */
  protected int $backupLimit = 15;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Migration plugin manager service.
   */
  protected MigrationPluginManager $migrationPluginManager;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Importer constructor.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The Logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationPluginManager
   *   Migration Plugin Manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The filesystem service.
   */
  public function __construct(
    LockBackendInterface $lock,
    LoggerChannelInterface $logger,
    ConfigFactoryInterface $config_factory,
    MigrationPluginManager $migrationPluginManager,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system
  ) {
    $this->lock = $lock;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
    $this->migrationPluginManager = $migrationPluginManager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;

    $settings = $this->configFactory->get('openy_traction_rec_import.settings');
    $this->isEnabled = (bool) $settings->get('enabled');
    $this->isBackupEnabled = (bool) $settings->get('backup_json');
    $this->backupLimit = (int) $settings->get('backup_limit');
  }

  /**
   * {@inheritdoc}
   */
  public function directoryImport(string $dir, array $options = []): void {
    if (PHP_SAPI !== 'cli') {
      return;
    }

    try {
      // Results of each fetch are saved to a separated directory.
      $json_files = $this->fileSystem->scanDirectory($dir, '/\.json$/');
      if (empty($json_files)) {
        return;
      }

      // Usually we have several files for import:
      // sessions.json, classes.json, programs.json, program_categories.json.
      foreach ($json_files as $file) {
        $this->fileSystem->copy($file->uri, static::TARGET_DIRECTORY, FileSystemInterface::EXISTS_REPLACE);
      }

      $migrations = $this->getMigrations();
      foreach ($migrations as $migration) {
        if ($migration->getStatus() == MigrationInterface::STATUS_IDLE) {
          // Get an instance of MigrateExecutable.
          $migrate_executable = new MigrateExecutable($migration, new MigrateMessage(), $options);

          // Call the method to execute the migration.
          $migrate_executable->import();
        }
      }

      // Save JSON files only if backup of JSON files is enabled.
      if ($this->isBackupEnabled()) {
        $backup_directory = static::BACKUP_DIRECTORY;
        $this->fileSystem->prepareDirectory($backup_directory, FileSystemInterface::CREATE_DIRECTORY);
        $this->fileSystem->move($dir, $backup_directory);
      }
      else {
        $this->fileSystem->deleteRecursive($dir);
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkMigrationsStatus(): bool {
    try {
      $migrations = $this->getMigrations();
      foreach ($migrations as $migration_id => $migration) {
        if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
          $this->logger->error($this->t('Migration @migration has status @status.', [
            '@migration' => $migration_id,
            '@status' => $migration->getStatusLabel(),
          ]));
          return FALSE;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Impossible to get migrations statuses: ' . $e->getMessage());
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->isEnabled;
  }

  /**
   * {@inheritdoc}
   */
  public function isBackupEnabled(): bool {
    return $this->isBackupEnabled;
  }

  /**
   * Returns JSON backup limit setting.
   *
   * @return int
   *   The number of folders with JSON files to store.
   */
  public function getJsonBackupLimit(): int {
    return $this->backupLimit;
  }

  /**
   * {@inheritdoc}
   */
  public function acquireLock(): bool {
    return $this->lock->acquire(static::LOCK_NAME, 1200);
  }

  /**
   * {@inheritdoc}
   */
  public function releaseLock(): void {
    $this->lock->release(static::LOCK_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonDirectoriesList(): array {
    $dirs = [];
    $scan = scandir(static::SOURCE_DIRECTORY);

    foreach ($scan as $file) {
      $filename = static::SOURCE_DIRECTORY . "/$file";
      if (is_dir($filename) && $file != '.' && $file != '..') {
        $dirs[] = $filename;
      }
    }

    return $dirs;
  }

  /**
   * Get the migrations.
   *
   * @return array|MigrationInterface[]
   *   The built migration instances.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMigrations(): array {
    $migrations = $this->entityTypeManager
      ->getStorage('migration')
      ->getQuery('AND')
      ->condition('migration_group', static::MIGRATE_GROUP)
      ->addTag('openy_tr_import_migrations')
      ->accessCheck(FALSE)
      ->execute();

    return $this->migrationPluginManager->createInstances($migrations);
  }

}
