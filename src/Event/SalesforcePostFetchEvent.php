<?php

namespace Drupal\openy_traction_rec\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event is invoked after Salesforce fetching and dumping JSON files.
 */
class SalesforcePostFetchEvent extends Event {

  /**
   * The event name.
   */
  const EVENT_NAME = 'openy_traction_rec.post_fetch';

  /**
   * The directory with fetched files.
   *
   * @var string
   */
  protected $directory;

  /**
   * Constructors the event class.
   *
   * @param string $directory
   *   The directory with fetched JSON files.
   */
  public function __construct(string $directory) {
    $this->directory = $directory;
  }

  /**
   * Provides the directory with the fetched JSON files.
   *
   * @return string
   *   The directory with the fetched JSON files
   */
  public function getJsonDirectory(): string {
    return $this->directory;
  }

}
