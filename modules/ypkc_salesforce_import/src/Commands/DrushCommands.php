<?php

namespace Drupal\ypkc_salesforce_import\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\migrate_tools\Commands\MigrateToolsCommands;
use Drupal\ypkc_salesforce_import\Cleaner;
use Drupal\ypkc_salesforce_import\Importer;
use Drupal\ypkc_salesforce_import\SalesforceFetcher;
use Drush\Commands\DrushCommands as DrushCommandsBase;

/**
 * YPKC Salesforce import drush commands.
 */
class DrushCommands extends DrushCommandsBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The importer service.
   *
   * @var \Drupal\ypkc_salesforce_import\Importer
   */
  protected $importer;

  /**
   * The YPKC sessions cleaner service.
   *
   * @var \Drupal\ypkc_salesforce_import\Cleaner
   */
  protected $cleaner;

  /**
   * Migrate tool drush commands.
   *
   * @var \Drupal\migrate_tools\Commands\MigrateToolsCommands
   */
  protected $migrateToolsCommands;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The Salesforce import queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $importQueue;

  /**
   * Salesforce fetcher service.
   *
   * @var \Drupal\ypkc_salesforce_import\SalesforceFetcher
   */
  protected $salesforceFetcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * DrushCommands constructor.
   *
   * @param \Drupal\ypkc_salesforce_import\Importer $importer
   *   The Salesforce importer service.
   * @param \Drupal\ypkc_salesforce_import\Cleaner $cleaner
   *   YPKC sessions cleaner.
   * @param \Drupal\migrate_tools\Commands\MigrateToolsCommands $migrate_tools_drush
   *   Migrate Tools drush commands service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\ypkc_salesforce_import\SalesforceFetcher $sf_fetch
   *   The YPKC TractionRec Fetcher.
   */
  public function __construct(
    Importer $importer,
    Cleaner $cleaner,
    MigrateToolsCommands $migrate_tools_drush,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    QueueFactory $queue_factory,
    SalesforceFetcher $sf_fetch
  ) {
    parent::__construct();
    $this->importer = $importer;
    $this->cleaner = $cleaner;
    $this->migrateToolsCommands = $migrate_tools_drush;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->importQueue = $queue_factory->get('ypkc_import');
    $this->salesforceFetcher = $sf_fetch;
  }

  /**
   * Executes the Salesforce import.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command ypkc-sf:import
   * @aliases y-sf:import
   *
   * @option sync Sync source and destination. Delete destination records that
   *   do not exist in the source.
   *
   * @return bool
   *   Execution status.
   *
   * @throws \Exception
   */
  public function import(array $options): bool {
    if (!$this->importer->isEnabled()) {
      $this->logger()->notice(
        dt('Salesforce import is not enabled!')
      );
      return FALSE;
    }

    if (!$this->importer->acquireLock()) {
      $this->logger()->notice(
        dt('Can\'t run new import, another import process already in progress.')
      );
      return FALSE;
    }

    if (!$this->importer->checkMigrationsStatus()) {
      $this->logger()->notice(
        dt('One or more migrations are still running or stuck.')
      );
      return FALSE;
    }

    $this->output()->writeln('Starting Salesforce migration');

    $dirs = $this->importer->getJsonDirectoriesList();
    if (empty($dirs)) {
      $this->logger()->info(dt('Nothing to import.'));
      return FALSE;
    }

    foreach ($dirs as $dir) {
      $this->importer->directoryImport($dir, $options);
    }

    $this->importer->releaseLock();
    $this->output()->writeln('Salesforce migration done!');

    return TRUE;
  }

  /**
   * Executes the Salesforce rollback.
   *
   * @command ypkc-sf:rollback
   * @aliases y-sf:rollback
   */
  public function rollback() {
    try {
      $this->output()->writeln('Rollbacking Salesforce migrations...');
      $options = ['group' => Importer::MIGRATE_GROUP];
      $this->migrateToolsCommands->rollback('', $options);
      $this->output()->writeln('Rollback done!');
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }
  }

  /**
   * Remove all sessions from the website.
   *
   * @command ypkc-sf:session-flush
   * @aliases y-sf:session-flush
   */
  public function flushSessions() {
    $storage = $this->entityTypeManager->getStorage('node');

    $sessions = $storage->loadByProperties(['type' => 'session']);

    if ($sessions) {
      $storage->delete($sessions);
    }

  }

  /**
   * Resets the import lock.
   *
   * @command ypkc-sf:reset-lock
   */
  public function resetLock() {
    $this->output()->writeln('Reset import status...');
    $this->importer->releaseLock();
  }

  /**
   * Clean up actions.
   *
   * @param array $options
   *   The array of command options.
   *
   * @command ypkc-sf:clean-up
   *
   * @option limit
   *   Items to store per file. Default: 5000
   */
  public function cleanUp(array $options) {
    $this->output()->writeln('Starting clean up...');

    $limit = $options['limit'];

    $this->cleaner->cleanDatabase($limit);
    $this->cleaner->cleanBackupFiles();

    $this->output()->writeln('Clean up finished!');
  }

  /**
   * Run Salesforce fetcher.
   *
   * @command ypkc:sf-fetch-all
   * @aliases y-sf-fa
   */
  public function fetch() {
    if (!$this->salesforceFetcher->isEnabled()) {
      $this->logger()->notice(dt('Fetcher is disabled!'));
      return FALSE;
    }

    $this->salesforceFetcher->fetch();
  }

  /**
   * Clean up actions.
   *
   * @param array $options
   *   The array of command options.
   *
   * @command ypkc-sf:queue-clean-up
   */
  public function addCleanUpToQueue(array $options) {
    $data = [
      'type' => 'cleanup',
    ];

    $this->importQueue->createItem($data);
  }

  /**
   * Add full-sync action to the queue.
   *
   * @param array $options
   *   The array of command options.
   *
   * @command ypkc-sf:queue-import-sync
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function addSyncActionToQueue(array $options) {
    if (!$this->salesforceFetcher->isEnabled()) {
      $this->logger()->notice(dt('Fetcher is disabled!'));
      return FALSE;
    }

    try {
      $this->salesforceFetcher->fetchProgramAndCategories();
      $this->salesforceFetcher->fetchClasses();
      $this->salesforceFetcher->fetchSessions();

      $directory = $this->salesforceFetcher->getJsonDirectory();

      $data = [
        'type' => 'salesforce_sync',
        'directory' => $directory,
      ];

      $this->importQueue->createItem($data);
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }
  }

}
