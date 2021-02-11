<?php

namespace Drupal\ypkc_salesforce_import\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate_tools\Commands\MigrateToolsCommands;
use Drupal\ypkc_salesforce_import\Importer;
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
   * @var Drupal\ypkc_salesforce_import\Importer
   */
  protected $importer;

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
   * DrushCommands constructor.
   *
   * @param \Drupal\ypkc_salesforce_import\Importer $importer
   *   The Salesforce importer service.
   * @param \Drupal\migrate_tools\Commands\MigrateToolsCommands $migrate_tools_drush
   *   Migrate Tools drush commands service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Importer $importer, MigrateToolsCommands $migrate_tools_drush, FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->importer = $importer;
    $this->migrateToolsCommands = $migrate_tools_drush;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Executes the Salesforce import.
   *
   * @command ypkc-sf:import
   * @aliases y-sf:import
   */
  public function import(): bool {
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
      $this->logger()->info(
        dt('Nothing to import.')
      );
      return FALSE;
    }

    foreach ($dirs as $dir) {
      // Results of each fetch are saved to separated directory.
      $json_files = $this->fileSystem->scanDirectory($dir, '/\.json$/');
      if (empty($json_files)) {
        continue;
      }

      // Usually we have 4 files for import:
      // sessions.json, classes.json, programs.json, locations.json.
      foreach ($json_files as $file) {
        $this->output()->writeln("Preparing $file->uri for import");
        $this->fileSystem->copy($file->uri, 'private://salesforce_import/', FileSystemInterface::EXISTS_REPLACE);
      }

      $this->migrateToolsCommands->import(
        '',
        ['group' => Importer::MIGRATE_GROUP, 'update' => TRUE]
      );

      $backup_dir = Importer::BACKUP_DIRECTORY;
      $this->fileSystem->prepareDirectory($backup_dir, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileSystem->move($dir, $backup_dir);
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
    $this->output()->writeln('Rollbacking Salesforce migrations...');
    $options = ['group' => Importer::MIGRATE_GROUP];
    $this->migrateToolsCommands->rollback('', $options);
    $this->output()->writeln('Rollback done!');
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

}
