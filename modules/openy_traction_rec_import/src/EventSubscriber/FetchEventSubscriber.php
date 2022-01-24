<?php

namespace Drupal\openy_traction_rec_import\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\openy_traction_rec\Event\SalesforcePostFetchEvent;
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
   *   The queue factory.
   */
  public function __construct(QueueFactory $queue_factory) {
    $this->importQueue = $queue_factory->get('openy_trasnsaction_recimport');
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
   * @param \Drupal\openy_traction_rec\Event\SalesforcePostFetchEvent $event
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
