<?php

namespace Drupal\openy_traction_rec_import\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\openy_traction_rec\Event\TractionRecPostFetchEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to Traction Rec fetcher events.
 */
class FetchEventSubscriber implements EventSubscriberInterface {

  /**
   * The Traction Rec import queue.
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
      TractionRecPostFetchEvent::EVENT_NAME => 'addImportToQueue',
    ];
  }

  /**
   * Adds traction_rec import to queue.
   *
   * @param \Drupal\openy_traction_rec\Event\TractionRecPostFetchEvent $event
   *   Traction Rec post-fetch event.
   */
  public function addImportToQueue(TractionRecPostFetchEvent $event) {
    $data = [
      'type' => 'traction_rec',
      'directory' => $event->getJsonDirectory(),
    ];

    $this->importQueue->createItem($data);
  }

}
