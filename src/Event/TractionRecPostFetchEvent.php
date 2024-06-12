<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event is invoked after Traction Rec fetching and dumping JSON files.
 */
class TractionRecPostFetchEvent extends Event {

  /**
   * The event name.
   */
  const EVENT_NAME = 'openy_traction_rec.post_fetch';

  /**
   * The directory with fetched files.
   */
  protected string $directory;

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
