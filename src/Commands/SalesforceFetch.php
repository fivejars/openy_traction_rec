<?php

namespace Drupal\ypkc_salesforce\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new OpenyMvSyncCommands.
   *
   * @param \Drupal\ypkc_salesforce\SalesforceFetcher $sf_fetch
   *   Salesforce fetcher service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(SalesforceFetcher $sf_fetch, ConfigFactoryInterface $config_factory) {
    parent::__construct();
    $this->salesforceFetcher = $sf_fetch;
    $this->configFactory = $config_factory;
  }

  /**
   * Run Salesforce fetcher.
   *
   * @command ypkc:sf-fetch-all
   * @aliases y-sf-fa
   */
  public function fetch() {
    $settings = $this->configFactory->get('ypkc_salesforce.settings');
    $is_enabled = $settings->get('fetch_status');

    if (!$is_enabled) {
      $this->logger()->notice(dt('Fetcher is disabled!'));
      return FALSE;
    }

    $this->salesforceFetcher->fetch();
  }

}
