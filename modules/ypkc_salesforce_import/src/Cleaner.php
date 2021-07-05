<?php

namespace Drupal\ypkc_salesforce_import;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Drush;

/**
 * Clean up old sessions data.
 */
class Cleaner {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger for salesforce.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The importer service.
   *
   * @var \Drupal\ypkc_salesforce_import\Importer
   */
  protected $importer;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Cleaner constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Logger factory.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_channel_factory, SalesforceImporterInterface $importer, FileSystemInterface $file_system) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_channel_factory->get('sessions_cleaner');
    $this->importer = $importer;
    $this->fileSystem = $file_system;
  }

  /**
   * Starts database clean up.
   */
  public function cleanDatabase($limit = 5000) {
    $this->deleteOldTimeParagraphs($limit);
  }

  /**
   * Cleans JSON files from backup folder.
   */
  public function cleanBackupFiles() {
    if (PHP_SAPI !== 'cli') {
      return;
    }

    // We want to store only latest backups of JSON files.
    if ($this->importer->isBackupEnabled()) {
      $backup_dir = $this->fileSystem->realpath(Importer::BACKUP_DIRECTORY);
      $backup_limit = $this->importer->getJsonBackupLimit();
      $backup_limit++;

      $command = "cd $backup_dir";
      $command .= '&&';
      $command .= 'ls -t';
      $command .= '|';
      $command .= "tail -n +$backup_limit";
      $command .= '|';
      $command .= "xargs -d '\n' rm -rf";

      $process = Drush::shell($command);
      $process->run();
      if (!$process->isSuccessful()) {
        $this->logger->warning('Impossible to remove JSON files: ' . $process->getErrorOutput());
      }
    }
  }

  /**
   * Removes session_time paragraphs not associated with nodes.
   */
  protected function deleteOldTimeParagraphs($limit = 5000) {
    try {
      $count = 0;

      $query = $this->database->select('paragraphs_item_field_data', 'pifd');
      $query->addField('pifd', 'id');
      $query->condition('pifd.type', 'session_time');
      $query->leftJoin('node__field_session_time', 'nfst', 'nfst.field_session_time_target_id = pifd.id');
      $query->isNull('nfst.field_session_time_target_id');
      $query->range(0, $limit);
      $result = $query->execute()->fetchCol();

      if (empty($result)) {
        return FALSE;
      }

      $storage = $this->entityTypeManager->getStorage('paragraph');
      foreach (array_chunk($result, 50) as $chunk) {
        $storage->delete($storage->loadMultiple($chunk));
        $count += 50;
      }

      return $count;
    }
    catch (\Exception $e) {
      $this->logger->error('SF Clean up error: ' . $e->getMessage());
      return $count;
    }
  }

}
