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
   * @var Importer
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
  public function import() {
    $this->importer->import();
  }

}
