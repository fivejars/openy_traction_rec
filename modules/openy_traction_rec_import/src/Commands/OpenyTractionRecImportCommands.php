<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
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
  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The importer service.
   */
  protected Importer $importer;

  /**
   * The OPENY sessions cleaner service.
   */
  protected Cleaner $cleaner;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Traction Rec fetcher service.
   */
  protected TractionRecFetcher $tractionRecFetcher;

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
   * @option update  In addition to processing unprocessed items from the
   *   source, update previously-imported items with the current data.
   *
   * @return bool
   *   Execution status.
   *
   * @throws \Exception
   */
  public function import(array $options): bool {
    if (!$this->importer->isEnabled()) {
      $this->logger()->notice($this->t(
        'The Traction Rec import is not enabled! Enable the
        "openy_traction_rec_import" module, then enable the syncer at @settings.',
        [
          '@settings' =>
          Url::fromRoute(
              'openy_traction_rec_import.settings',
              [],
              ['absolute' => TRUE])->toString(),
        ]
      ));
      return FALSE;
    }

    if (!$this->importer->acquireLock()) {
      $this->logger()->notice(
        'Can\'t run a new import, another import process is in progress.
        Try "openy-tr:reset-lock" if the process seems stuck.'
      );
      return FALSE;
    }

    if (!$this->importer->checkMigrationsStatus()) {
      $this->logger()->notice(
        'One or more migrations are still running or stuck. Run
        "drush migrate:status" to see the status of migrations and
        "drush migrate:reset migrationId" to reset the stuck migration.');
      return FALSE;
    }

    $this->output()->writeln('Starting Traction Rec migration.');

    $dirs = $this->importer->getJsonDirectoriesList();
    if (empty($dirs)) {
      $this->logger()->info('No Traction Rec data to import.');
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
  public function rollback(): void {
    try {
      $this->output()->writeln('Rolling back Traction Rec migrations...');
      $this->processManager->drush(
        $this->siteAliasManager->getSelf(),
        'migrate:rollback',
        [],
        ['group' => $this->importer::MIGRATE_GROUP])
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
  public function resetLock(): void {
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
  public function cleanUp(array $options): void {
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
  public function fetch(): void {
    if (!$this->tractionRecFetcher->isEnabled()) {
      $this->logger()->notice($this->t(
        'The Traction Rec fetcher is not enabled! Enable the fetcher at @settings',
        [
          '@settings' =>
          Url::fromRoute(
              'openy_traction_rec_import.settings',
              [],
              ['absolute' => TRUE])->toString(),
        ]));
      return;
    }

    $this->logger()->notice("Fetching data from Traction Rec.");
    $fetch = $this->tractionRecFetcher->fetch();

    if (!is_dir($fetch)) {
      $this->logger()->warning('Traction Rec data fetch failed. Check the logs for more info.');
    }
    else {
      $this->logger()->notice("Traction Rec data fetched to " . $fetch);
    }
  }

  /**
   * Run Traction Rec Total Available sync.
   *
   * @command openy-tr:quick-availability-sync
   * @aliases tr:qas
   */
  public function updateTotalAvailable() {
    if (!$this->tractionRecFetcher->isEnabled()) {
      $this->logger()->notice($this->t(
        'The Traction Rec fetcher is not enabled! Enable the fetcher at @settings',
        [
          '@settings' =>
            Url::fromRoute(
              'openy_traction_rec_import.settings',
              [],
              ['absolute' => TRUE])->toString(),
        ]));
      return FALSE;
    }

    $count = 0;
    $this->logger()->notice("Fetching data from Traction Rec.");
    $total_available_list = $this->tractionRecFetcher->fetchTotalAvailable();

    $migration_map = \Drupal::database()->select('migrate_map_tr_sessions_import', 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();

    $nodes_to_update = [];
    foreach ($total_available_list as $session_id => $total_available_item) {
      if (!empty($migration_map[$session_id])) {
        $nodes_to_update[$migration_map[$session_id]] = $total_available_item;
      }
    }

    // Process the nodes in batches.
    $chunk_size = 50;  // You can adjust this batch size as needed.
    $chunks = array_chunk($nodes_to_update, $chunk_size, TRUE);

    foreach ($chunks as $chunk) {
      // Get all node IDs in this batch.
      $nids = array_keys($chunk);
      // Load nodes in bulk.
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

      foreach ($nodes as $nid => $node) {
        // Retrieve the corresponding availability info.
        $total_available_item = $chunk[$nid];

        if ($node instanceof NodeInterface) {
          // Calculate the total capacity available.
          // If 'Unlimited_Capacity' is true, set to 100; otherwise, ensure it's at least 0.
          $total_capacity_available = $total_available_item['Unlimited_Capacity']
            ? 100
            : max((int) $total_available_item['Total_Capacity_Available'], 0);

          // Update the necessary fields.
          $node->set('field_availability', $total_capacity_available);
          $node->set('waitlist_unlimited_capacity', $total_available_item['Unlimited_Waitlist_Capacity']);
          $node->set('waitlist_capacity', $total_available_item['Waitlist_Total']);
          $node->save();
          $count++;
        }
      }
    }
    
    $this->logger()->notice($this->t('Total available data were synced for @count sessions', ['@count' => $count]));
  }

}
