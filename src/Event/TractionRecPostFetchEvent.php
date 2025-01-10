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
   * The array with fetching results.
   */
  protected array $results;

  /**
   * Constructors the event class.
   *
   * @param string $directory
   *   The directory with fetched JSON files.
   * @param array $results
   *   The array with fetching results.
   */
  public function __construct(string $directory, array $results = []) {
    $this->directory = $directory;
    $this->results = $results;
  }

  /**
   * Provides the directory with the fetched JSON files.
   *
   * @return string
   *   The directory with the fetched JSON files.
   */
  public function getJsonDirectory(): string {
    return $this->directory;
  }

  /**
   * Provides the results from the data fetching.
   *
   * @return array
   *   The fetch results.
   */
  public function getResults(): array {
    return $this->results;
  }

}
