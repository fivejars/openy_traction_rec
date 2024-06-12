<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drush\Drush;

/**
 * Clean up old sessions data.
 */
class Cleaner {

  /**
   * Active database connection.
   */
  protected Connection $database;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Logger for traction_rec.
   */
  protected LoggerChannelInterface $logger;

  /**
   * The importer service.
   */
  protected Importer $importer;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Cleaner constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Logger factory.
   * @param \Drupal\openy_traction_rec_import\TractionRecImporterInterface $importer
   *   The traction rec Importer.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_channel_factory, TractionRecImporterInterface $importer, FileSystemInterface $file_system) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_channel_factory->get('sessions_cleaner');
    $this->importer = $importer;
    $this->fileSystem = $file_system;
  }

  /**
   * Cleans JSON files from backup folder.
   */
  public function cleanBackupFiles(): void {
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

}
