<?php

namespace Drupal\ypkc_salesforce_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;

/**
 * Wrapper for Salesforce import operations.
 */
class Importer {

  use StringTranslationTrait;

  /**
   * The name used to identify the lock.
   */
  const LOCK_NAME = 'sf_import';

  /**
   * The name of the migrate group.
   */
  const MIGRATE_GROUP = 'sf_import';

  /**
   * The path to a directory with JSON files for import.
   */
  const SOURCE_DIRECTORY = 'private://salesforce_import/json/';

  /**
   * The path to a directory for processed JSON files.
   */
  const BACKUP_DIRECTORY = 'private://salesforce_import/backup/';

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Import status.
   *
   * @var bool
   */
  protected $isEnabled = FALSE;

  /**
   * JSON backup status.
   *
   * @var bool
   */
  protected $isBackupEnabled = FALSE;

  /**
   * JSON backup limit.
   *
   * @var int
   */
  protected $backupLimit = 15;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   */
  public function __construct(
    LockBackendInterface $lock,
    LoggerChannelInterface $logger,
    ConfigFactoryInterface $config_factory,
    MigrationPluginManager $migrationPluginManager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->lock = $lock;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
    $this->migrationPluginManager = $migrationPluginManager;
    $this->entityTypeManager = $entity_type_manager;

    $settings = $this->configFactory->get('ypkc_salesforce_import.settings');
    $this->isEnabled = (bool) $settings->get('enabled');
    $this->isBackupEnabled = (bool) $settings->get('backup_json');
    $this->backupLimit = (int) $settings->get('backup_limit');
  }

  /**
   * Check migration status.
   */
  public function checkMigrationsStatus(): bool {
    try {
      $migrations = $this->entityTypeManager
        ->getStorage('migration')
        ->getQuery('AND')
        ->condition('migration_group', static::MIGRATE_GROUP)
        ->execute();

      $migrations = $this->migrationPluginManager->createInstances($migrations);
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
   * Checks import status.
   *
   * @return bool
   *   TRUE if the import is enabled.
   */
  public function isEnabled():bool {
    return $this->isEnabled;
  }

  /**
   * Checks JSON files backup ststus.
   *
   * @return bool
   *   TRUE if JSON files backup is enabled.
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
   * Acquires Salesforce import lock.
   *
   * @return bool
   *   Lock status.
   */
  public function acquireLock(): bool {
    return $this->lock->acquire(static::LOCK_NAME, 1200);
  }

  /**
   * Releases Salesforce lock.
   */
  public function releaseLock() {
    $this->lock->release(static::LOCK_NAME);
  }

  /**
   * Provides a list of directories with the fetched JSON files.
   *
   * @return array
   *   The array of directories paths.
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

}
