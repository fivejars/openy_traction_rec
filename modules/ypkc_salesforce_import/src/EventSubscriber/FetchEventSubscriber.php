<?php

namespace Drupal\ypkc_salesforce_import\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\ypkc_salesforce\Event\SalesforcePostFetchEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to Salesforce fetcher events.
 */
class FetchEventSubscriber implements EventSubscriberInterface {

  /**
   * The Salesforce import queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $importQueue;

  /**
   * FetchEventSubscriber constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   */
  public function __construct(QueueFactory $queue_factory) {
    $this->importQueue = $queue_factory->get('ypkc_import');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SalesforcePostFetchEvent::EVENT_NAME => 'addImportToQueue',
    ];
  }

  /**
   * Adds salesforce import to queue.
   *
   * @param \Drupal\ypkc_salesforce\Event\SalesforcePostFetchEvent $event
   *   Salesforce post-fetch event.
   */
  public function addImportToQueue(SalesforcePostFetchEvent $event) {
    $data = [
      'type' => 'salesforce',
      'directory' => $event->getJsonDirectory(),
    ];

    $this->importQueue->createItem($data);
  }

}
