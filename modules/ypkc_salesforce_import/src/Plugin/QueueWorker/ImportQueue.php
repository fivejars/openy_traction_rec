<?php

namespace Drupal\ypkc_salesforce_import\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ypkc_salesforce_import\SalesforceImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue of salesforce imports.
 *
 * @QueueWorker(
 *   id = "ypkc_import",
 *   title = @Translation("YPKC import"),
 *   cron = {"time" = 120}
 * )
 */
class ImportQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The importer service.
   *
   * @var \Drupal\ypkc_salesforce_import\Importer
   */
  protected $salesforceImporter;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructors Salesforce ImportQueue plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SalesforceImporterInterface $salesforce_importer,
    LoggerChannelInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->salesforceImporter = $salesforce_importer;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ypkc_salesforce_import.importer'),
      $container->get('logger.channel.sf_import')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    switch ($data['type']) {
      case 'salesforce':
        $this->processSalesforceImport($data);
        break;

      case 'csv':
        $this->processCsvImport($data);
        break;
    }
  }

  /**
   * Processes salesforce import.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   */
  protected function processSalesforceImport($data): bool {
    if (!isset($data['directory'])) {
      return FALSE;
    }

    if (!$this->salesforceImporter->isEnabled()) {
      $this->logger->info('Salesforce import is not enabled!');
      return FALSE;
    }

    if (!$this->salesforceImporter->acquireLock()) {
      $this->logger->info('Can\'t run new import, another import process already in progress.');
      return FALSE;
    }

    if (!$this->salesforceImporter->checkMigrationsStatus()) {
      $this->logger->info('One or more migrations are still running or stuck.');
      return FALSE;
    }

    $this->salesforceImporter->directoryImport($data['directory']);
    return TRUE;
  }

  /**
   * Processes CSV import.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   */
  protected function processCsvImport($data) {
    // @TODO: Run CSV import.
  }

}
