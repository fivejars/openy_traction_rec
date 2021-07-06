<?php

namespace Drupal\ypkc_salesforce_import\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ypkc_salesforce_import\Cleaner;
use Drupal\ypkc_salesforce_import\SalesforceImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue of salesforce imports.
 *
 * @QueueWorker(
 *   id = "ypkc_import",
 *   title = @Translation("YPKC import"),
 *   cron = {"time" = 300}
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
   * The YPKC cleaner service.
   *
   * @var \Drupal\ypkc_salesforce_import\Cleaner
   */
  protected $cleaner;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * ImportQueue constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ypkc_salesforce_import\SalesforceImporterInterface $salesforce_importer
   *   The Salesforce importer.
   * @param \Drupal\ypkc_salesforce_import\Cleaner $cleaner
   *   The YPKC Cleaner.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SalesforceImporterInterface $salesforce_importer,
    Cleaner $cleaner,
    LoggerChannelInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->salesforceImporter = $salesforce_importer;
    $this->logger = $logger;
    $this->cleaner = $cleaner;
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
      $container->get('ypkc_salesforce_import.cleaner'),
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

      case 'salesforce_sync':
        $this->processSalesforceImport($data, TRUE);
        break;

      case 'csv':
        $this->processCsvImport($data);
        break;

      case 'cleanup':
        $this->processCleanUp();
        break;
    }
  }

  /**
   * Processes salesforce import.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   * @param bool $sync
   *   Full sync flag.
   *
   * @return bool
   *   Action status.
   */
  protected function processSalesforceImport($data, bool $sync = FALSE): bool {
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

    $this->salesforceImporter->directoryImport($data['directory'], ['sync' => $sync]);
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
    // @todo Run CSV import.
  }

  /**
   * Processes clean up actions.
   */
  protected function processCleanUp() {
    $this->cleaner->cleanBackupFiles();
  }

}
