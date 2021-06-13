<?php

namespace Drupal\ypkc_salesforce_import;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

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
   * Cleaner constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Logger factory.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_channel_factory) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_channel_factory->get('sessions_cleaner');
  }

  /**
   * Starts clean up.
   */
  public function cleanUp($limit = 5000) {
    $this->deleteOldTimeParagraphs($limit);
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
        return;
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
