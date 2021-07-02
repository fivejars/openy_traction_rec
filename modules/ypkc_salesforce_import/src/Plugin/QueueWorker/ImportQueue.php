<?php

namespace Drupal\ypkc_salesforce_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
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
   * Constructors Salesforce ImportQueue plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ypkc_salesforce_import.importer')
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
  protected function processSalesforceImport($data) {
    if (!isset($data['directory'])) {
      return;
    }

    $importer = \Drupal::service('ypkc_salesforce_import.importer');
    $importer->directoryImport($data['directory']);
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
