<?php

namespace Drupal\ypkc_salesforce_import\Commands;

use Drupal\ypkc_salesforce_import\Importer;
use Drush\Commands\DrushCommands as DrushCommandsBase;

/**
 * YPKC Salesforce import drush commands.
 */
class DrushCommands extends DrushCommandsBase {

  /**
   * The importer service.
   *
   * @var Drupal\ypkc_salesforce_import\Importer
   */
  protected $importer;

  /**
   * DrushCommands constructor.
   *
   * @param \Drupal\ypkc_salesforce_import\Importer $importer
   *   The Salesforce importer service.
   */
  public function __construct(Importer $importer) {
    parent::__construct();
    $this->importer = $importer;
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

    if ($this->importer->acquireLock()) {
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

    drush_print('Starting migrations...');
    $commands = \Drupal::service('migrate_tools.commands');
    $commands->import(
      '',
      ['group' => Importer::MIGRATE_GROUP, 'update' => TRUE]
    );

    $this->importer->releaseLock();
    drush_print('Migration done.');

    return TRUE;
  }

}
