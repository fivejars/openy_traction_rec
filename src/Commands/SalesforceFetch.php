<?php

namespace Drupal\ypkc_salesforce\Commands;

use Drupal\ypkc_salesforce\SalesforceFetcher;
use Drush\Commands\DrushCommands;

/**
 * Salesforce fetch drush commands.
 */
class SalesforceFetch extends DrushCommands {

  /**
   * Salesforce fetcher service.
   *
   * @var \Drupal\ypkc_salesforce\SalesforceFetcher
   */
  protected $salesforceFetcher;

  /**
   * Constructs a new OpenyMvSyncCommands.
   *
   * @param \Drupal\ypkc_salesforce\SalesforceFetcher $sf_fetch
   *   Salesforce fetcher service.
   */
  public function __construct(SalesforceFetcher $sf_fetch) {
    parent::__construct();
    $this->salesforceFetcher = $sf_fetch;
  }

  /**
   * Run Salesforce fetcher.
   *
   * @command ypkc:sf-fetch-all
   * @aliases y-sf-fa
   */
  public function fetch() {
    $this->salesforceFetcher->fetch();
  }

}
