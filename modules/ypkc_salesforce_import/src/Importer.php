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
  public $isEnabled;

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
    $this->isEnabled = $settings->get('enabled');
  }

  /**
   * Executes Salesforce import.
   *
   * @return bool
   *   The operation status.
   *
   * @throws \Exception
   */
  public function import(): bool {
    if (!$this->isEnabled) {
      $this->logger->warning('Please enable import if you want run migrations.');
      return FALSE;
    }

    if (!$this->checkMigrationsStatus()) {
      $this->logger->info('Can\'t start the import, import already in progress.');
      return FALSE;
    }

    if ($this->lock->acquire(self::LOCK_NAME, 1200)) {
      $this->logger->info('Can\'t start the import, import already in progress.');
      return FALSE;
    }

    // @TODO: Execute import.

    $this->lock->release(self::LOCK_NAME);
    return FALSE;
  }

  /**
   * Check migration status.
   */
  public function checkMigrationsStatus(): bool {
    try {
      $migrations = $this->entityTypeManager
        ->getStorage('migration')
        ->getQuery('AND')
        ->condition('migration_group', self::MIGRATE_GROUP)
        ->execute();

      $migrations = $this->migrationPluginManager->createInstances($migrations);
      foreach ($migrations as $migration_id => $migration) {
        // Allow only IDLE status.
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

}
