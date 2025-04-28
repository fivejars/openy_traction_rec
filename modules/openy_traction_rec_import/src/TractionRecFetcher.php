<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\openy_traction_rec\Event\TractionRecPostFetchEvent;
use Drupal\openy_traction_rec\TractionRecInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains related to fetching from Traction Rec functionality.
 */
class TractionRecFetcher {

  /**
   * Result json directory path.
   */
  protected string $storagePath = 'private://traction_rec_import/json/';

  /**
   * Traction Rec wrapper.
   */
  protected TractionRecInterface $tractionRec;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The event dispatcher used to notify subscribers.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The locations mapping helper.
   */
  protected LocationsMappingHelper $locationsMapping;

  /**
   * JSON Directory name.
   */
  protected string $directory;

  /**
   * Logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructors TractionRecFetcher.
   *
   * @param \Drupal\openy_traction_rec\TractionRecInterface $traction_rec
   *   The Traction Rec wrapper.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\openy_traction_rec_import\LocationsMappingHelper $locations_mapping
   *   The locations mapping helper.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The Logger channel.
   */
  public function __construct(
    TractionRecInterface $traction_rec,
    FileSystemInterface $file_system,
    EventDispatcherInterface $event_dispatcher,
    ConfigFactoryInterface $config_factory,
    LocationsMappingHelper $locations_mapping,
    LoggerChannelInterface $logger
  ) {
    $this->tractionRec = $traction_rec;
    $this->fileSystem = $file_system;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
    $this->locationsMapping = $locations_mapping;

    $this->fileSystem->prepareDirectory($this->storagePath, FileSystemInterface::CREATE_DIRECTORY);
    $this->directory = $this->storagePath . date('YmdHi') . '/';
    $this->logger = $logger;
  }

  /**
   * Fetch results (sessions and classes) from Traction Rec and save into file.
   */
  public function fetch(): string {
    $results = [];

    foreach ($this->getQueue() as $method) {
      try {
        if (!method_exists($this, $method)) {
          $message = "Something went wrong with fetching queue: Method '{$method}' does not exist.";
          $this->logger->error($message);
          throw new \BadMethodCallException($message);
        }

        $results[$method] = $this->{$method}();

        // Skip records data to reduce memory usage.
        unset($results[$method]['records']);
      }
      catch (\Exception $e) {
        $results[$method] = [
          'error' => $e->getMessage(),
        ];
      }
    }

    // Instantiate post fetch event.
    $event = new TractionRecPostFetchEvent($this->directory, $results);
    $this->eventDispatcher->dispatch($event, TractionRecPostFetchEvent::EVENT_NAME);

    return $this->directory;
  }

  /**
   * The queue of methods that should be run during fetch.
   */
  protected function getQueue(): array {
    return [
      'fetchProgramAndCategories',
      'fetchClasses',
      'fetchSessions',
      'fetchLocations',
    ];
  }

  /**
   * Fetches sessions data.
   *
   * @return array
   *   The array of fetched data.
   *
   * @throws \Drupal\openy_traction_rec\InvalidResponseException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  public function fetchSessions(): array {
    $result = $this->tractionRec->loadCourseOptions($this->locationsMapping->getMappedIds());

    if (empty($result['records'])) {
      return [];
    }

    $dumper = new JsonStreamDumper($this->buildFilename('sessions'));
    $dumper->pushMultiple($result['records']);

    if (!empty($result['nextRecordsUrl'])) {
      $this->paginationFetch($result['nextRecordsUrl'], $dumper);
    }

    $dumper->close();
    return $result;
  }

  /**
   * Fetches all pages of results recursively.
   *
   * @param string $nextUrl
   *   The URL of the next results page.
   * @param \Drupal\openy_traction_rec_import\JsonStreamDumper|NULL $dumper
   *   (Optional) Json dumper.
   *
   * @return array
   *   The array with fetched data.
   *
   * @throws \Drupal\openy_traction_rec\InvalidResponseException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  protected function paginationFetch(string $nextUrl, ?JsonStreamDumper $dumper = NULL): array {
    $result = $this->tractionRec->loadNextPage($nextUrl);

    if (empty($result['records'])) {
      return [];
    }

    if ($dumper !== NULL) {
      $dumper->pushMultiple($result['records']);
    }

    if (!empty($result['nextRecordsUrl'])) {
      $result['records'] = array_merge($result['records'], $this->paginationFetch($result['nextRecordsUrl'], $dumper));
    }

    return $result['records'];
  }

  /**
   * Pulls location object from Traction Rec.
   *
   * @return array
   *   The array of fetched locations.
   *
   * @throws \Drupal\openy_traction_rec\InvalidResponseException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  public function fetchLocations(): array {
    $result = $this->tractionRec->loadLocations();

    if (empty($result['records'])) {
      return [];
    }

    // Locations with an empty address are useless for import.
    array_filter($result['records'], function (array $location) {
      return empty($location['Address_City']);
    });
    $this->dumpToJson($result, $this->buildFilename('locations'));

    return $result;
  }

  /**
   * Fetches classes.
   *
   * @return array
   *   The array of fetched data.
   *
   * @throws \Drupal\openy_traction_rec\InvalidResponseException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  public function fetchClasses(): array {
    $result = $this->tractionRec->loadCourses();

    if (empty($result['records'])) {
      return [];
    }

    $dumper = new JsonStreamDumper($this->buildFilename('classes'));
    $dumper->pushMultiple($result['records']);

    if (!empty($result['nextRecordsUrl'])) {
      $this->paginationFetch($result['nextRecordsUrl'], $dumper);
    }

    $dumper->close();
    return $result;
  }

  /**
   * Fetches the program data.
   *
   * @return array
   *   The array of fetched data.
   *
   * @throws \Drupal\openy_traction_rec\InvalidResponseException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  public function fetchProgramAndCategories(): array {
    $result = $this->tractionRec->loadProgramCategoryTags();

    if (empty($result['records'])) {
      return [];
    }

    $programs = [];
    $categories = [];
    foreach ($result['records'] as $key => $category_tag) {
      // It's confusing, but in Open Y terms we have a reverse structure:
      // Traction Rec Program -> Open Y Program Sub Category.
      // Traction Rec Program Category -> Open Y Program.
      $programs[$category_tag['Program_Category']['Id']] = $category_tag['Program_Category'];

      $category = $category_tag['Program'];
      $category['Program'] = $category_tag['Program_Category'];

      // Only set the new category if its Id is unique. In TREC it is possible
      // for a Program to exist under multiple Categories, but we do not allow
      // that relationship. This may result in some data loss.
      // @todo we should figure out how to deal with this better.
      if (!in_array($category['Id'], array_column($categories, 'Id'))) {
        $categories[] = $category;
      }

      unset($result['records'][$key]);
    }

    $this->dumpToJson(array_values($programs), $this->buildFilename('programs'));
    $this->dumpToJson($categories, $this->buildFilename('program_categories'));
    return $result;
  }

  /**
   * Fetches total available.
   */
  public function fetchTotalAvailable(): array {
    $result_list = [];
    $result = $this->tractionRec->loadTotalAvailable($this->locationsMapping->getMappedIds());

    if (empty($result['records'])) {
      return [];
    }

    if (!empty($result['nextRecordsUrl'])) {
      $result['records'] = array_merge($result['records'], $this->paginationFetch($result['nextRecordsUrl']));
    }

    foreach ($result['records'] as $record) {
      $result_list[$record['Course_Option']['Id']] = $record['Course_Option'];
    }

    return $result_list;
  }

  /**
   * Provides the JSON directory path.
   *
   * @return string
   *   The directory to fetch JSON data.
   */
  public function getJsonDirectory(): string {
    return $this->directory;
  }

  /**
   * Checks the Fetcher status.
   *
   * @return bool
   *   TRUE if fetcher is enabled.
   */
  public function isEnabled(): bool {
    $settings = $this->configFactory->get('openy_traction_rec_import.settings');
    return (bool) $settings->get('fetch_status');
  }

  /**
   * Saves data into JSON file.
   *
   * @param array $data
   *   The array of data.
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The dumped file name.
   */
  protected function dumpToJson(array $data, string $filename): string {
    $file = fopen($filename, 'w');
    fwrite($file, json_encode($data, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
    fclose($file);

    return $filename;
  }

  /**
   * Builds a filename for JSON file.
   *
   * @param string $items_type
   *   Items type: `programs`, `classes` or `sessions`.
   *
   * @return string
   *   The filename string.
   */
  protected function buildFilename(string $items_type): string {
    $this->fileSystem->prepareDirectory($this->directory, FileSystemInterface::CREATE_DIRECTORY);
    return $this->directory . $items_type . '.json';
  }

}
