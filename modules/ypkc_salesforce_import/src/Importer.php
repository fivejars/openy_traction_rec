<?php

namespace Drupal\ypkc_salesforce_import;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Wrapper for Salesforce import operations.
 */
class Importer {

  /**
   * The name used to identify the lock.
   */
  const LOCK_NAME = 'sf_import';

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Import status.
   *
   * @var bool
   */
  public $isEnabled;

  /**
   * Importer constructor.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The Logger channel.
   */
  public function __construct(LockBackendInterface $lock, LoggerChannelInterface $logger) {
    $this->lock = $lock;
    $this->logger = $logger;
    // @TODO: get the value from the module config.
    $this->isEnabled = TRUE;
  }

  /**
   * Executes Salesforce import.
   *
   * @return bool
   *   The operation status.
   */
  public function import(): bool {
    if (!$this->isEnabled) {
      $this->logger->warning('Please enable import if you want run migrations.');
      return FALSE;
    }

    if ($this->lock->acquire(static::LOCK_NAME, 1200)) {
      $this->logger->info('Can\'t start the import, import already in progress.');
      return FALSE;
    }

    // @TODO: Implement import logic.

    return FALSE;

  }

}
