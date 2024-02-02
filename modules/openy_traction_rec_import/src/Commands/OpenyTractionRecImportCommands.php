<?php

namespace Drupal\openy_traction_rec_import\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\openy_traction_rec_import\Cleaner;
use Drupal\openy_traction_rec_import\Importer;
use Drupal\openy_traction_rec_import\TractionRecFetcher;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * OPENY Traction Rec import drush commands.
 */
class OpenyTractionRecImportCommands extends DrushCommands {

  use ProcessManagerAwareTrait;
  use SiteAliasManagerAwareTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The importer service.
   *
   * @var \Drupal\openy_traction_rec_import\Importer
   */
  protected $importer;

  /**
   * The OPENY sessions cleaner service.
   *
   * @var \Drupal\openy_traction_rec_import\Cleaner
   */
  protected $cleaner;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Traction Rec fetcher service.
   *
   * @var \Drupal\openy_traction_rec_import\TractionRecFetcher
   */
  protected $tractionRecFetcher;

  /**
   * DrushCommands constructor.
   *
   * @param \Drupal\openy_traction_rec_import\Importer $importer
   *   The Traction Rec importer service.
   * @param \Drupal\openy_traction_rec_import\Cleaner $cleaner
   *   OPENY sessions cleaner.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\openy_traction_rec_import\TractionRecFetcher $tr_fetch
   *   The OPENY TractionRec Fetcher.
   */
  public function __construct(
    Importer $importer,
    Cleaner $cleaner,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    TractionRecFetcher $tr_fetch
  ) {
    parent::__construct();
    $this->importer = $importer;
    $this->cleaner = $cleaner;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->tractionRecFetcher = $tr_fetch;

    $this->siteAliasManager = Drush::service('site.alias.manager');
    $this->processManager = Drush::processManager();
  }

  /**
   * Executes the Traction Rec import.
   *
   * @param array $options
   *   Additional options for the command.
   *
   * @command openy-tr:import
   * @aliases tr:import
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
      $this->logger()->notice('Traction Rec import is not enabled!');
      return FALSE;
    }

    if (!$this->importer->acquireLock()) {
      $this->logger()->notice('Can\'t run new import, another import process already in progress.');
      return FALSE;
    }

    if (!$this->importer->checkMigrationsStatus()) {
      $this->logger()->notice('One or more migrations are still running or stuck.');
      return FALSE;
    }

    $this->output()->writeln('Starting Traction Rec migration');

    $dirs = $this->importer->getJsonDirectoriesList();
    if (empty($dirs)) {
      $this->logger()->info('Nothing to import.');
      return FALSE;
    }

    foreach ($dirs as $dir) {
      $this->importer->directoryImport($dir, $options);
    }

    $this->importer->releaseLock();
    $this->output()->writeln('Traction Rec migration done!');

    return TRUE;
  }

  /**
   * Executes the Traction Rec rollback.
   *
   * @command openy-tr:rollback
   * @aliases tr:rollback
   */
  public function rollback() {
    try {
      $this->output()->writeln('Rollbacking Traction Rec migrations...');
      $this->processManager->drush(
        $this->siteAliasManager->getSelf(),
        'migrate:rollback',
        [],
        ['group' => Importer::MIGRATE_GROUP])
        ->run();
      $this->output()->writeln('Rollback done!');
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }
  }

  /**
   * Resets the import lock.
   *
   * @command openy-tr:reset-lock
   * @aliases tr:reset-lock
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
   * @command openy-tr:clean-up
   * @aliases tr:clean-up
   */
  public function cleanUp(array $options) {
    $this->output()->writeln('Starting clean up...');
    $this->cleaner->cleanBackupFiles();
    $this->output()->writeln('Clean up finished!');
  }

  /**
   * Run Traction Rec fetcher.
   *
   * @command openy-tr:fetch-all
   * @aliases tr:fetch
   */
  public function fetch() {
    if (!$this->tractionRecFetcher->isEnabled()) {
      $this->logger()->notice('Fetcher is disabled!');
      return FALSE;
    }

    $this->logger()->notice("Fetching data from Traction Rec.");
    $fetch = $this->tractionRecFetcher->fetch();

    if (!is_dir($fetch)) {
      $this->logger()->warning('Data fetch failed. Check the logs for more info.');
    }
    else {
      $this->logger()->notice("Data fetched to " . $fetch);
    }
  }

}
