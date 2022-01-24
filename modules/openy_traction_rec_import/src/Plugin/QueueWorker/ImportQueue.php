<?php

namespace Drupal\openy_traction_rec_import\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\openy_traction_rec_import\SalesforceImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue of salesforce imports.
 *
 * @QueueWorker(
 *   id = "openy_trasnsaction_recimport",
 *   title = @Translation("OPENY import"),
 *   cron = {"time" = 300}
 * )
 */
class ImportQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The importer service.
   *
   * @var \Drupal\openy_traction_rec_import\Importer
   */
  protected $salesforceImporter;

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
   * @param \Drupal\openy_traction_rec_import\SalesforceImporterInterface $salesforce_importer
   *   The Salesforce importer.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
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
      $container->get('openy_traction_rec_import.importer'),
      $container->get('logger.channel.sf_import')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $sync = $data['type'] === 'salesforce_sync';
    $this->processSalesforceImport($data, $sync);
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

}
