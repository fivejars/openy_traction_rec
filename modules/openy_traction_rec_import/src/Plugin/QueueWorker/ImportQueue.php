<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\openy_traction_rec_import\Importer;
use Drupal\openy_traction_rec_import\TractionRecImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue of traction_rec imports.
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
   */
  protected Importer $tractionRecImporter;

  /**
   * Logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * ImportQueue constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\openy_traction_rec_import\TractionRecImporterInterface $traction_rec_importer
   *   The Traction Rec importer.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    TractionRecImporterInterface $traction_rec_importer,
    LoggerChannelInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tractionRecImporter = $traction_rec_importer;
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
      $container->get('logger.channel.tr_import')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $sync = $data['type'] === 'traction_rec_sync';
    $this->processTractionRecImport($data, $sync);
  }

  /**
   * Processes traction_rec import.
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
  protected function processTractionRecImport(mixed $data, bool $sync = FALSE): bool {
    if (!isset($data['directory'])) {
      return FALSE;
    }

    if (!$this->tractionRecImporter->isEnabled()) {
      $this->logger->info('Traction Rec import is not enabled!');
      return FALSE;
    }

    if (!$this->tractionRecImporter->acquireLock()) {
      $this->logger->info('Can\'t run new import, another import process already in progress.');
      return FALSE;
    }

    if (!$this->tractionRecImporter->checkMigrationsStatus()) {
      $this->logger->info('One or more migrations are still running or stuck.');
      return FALSE;
    }

    $this->tractionRecImporter->directoryImport($data['directory'], ['sync' => $sync]);
    return TRUE;
  }

}
