<?php

namespace Drupal\openy_traction_rec_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\openy_traction_rec\Event\TractionRecPostFetchEvent;
use Drupal\openy_traction_rec\TractionRecInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains related to fetching from Traction Rec functionality.
 */
class TractionRecFetcher {

  /**
   * Result json directory path.
   *
   * @var string
   */
  protected $storagePath = 'private://traction_rec_import/json/';

  /**
   * Traction Rec wrapper.
   *
   * @var \Drupal\openy_traction_rec\TractionRecInterface
   */
  protected $tractionRec;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The event dispatcher used to notify subscribers.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * JSON Directory name.
   *
   * @var string
   */
  protected $directory;

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
   */
  public function __construct(
    TractionRecInterface $traction_rec,
    FileSystemInterface $file_system,
    EventDispatcherInterface $event_dispatcher,
    ConfigFactoryInterface $config_factory
  ) {
    $this->tractionRec = $traction_rec;
    $this->fileSystem = $file_system;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;

    $this->fileSystem->prepareDirectory($this->storagePath, FileSystemInterface::CREATE_DIRECTORY);
    $this->directory = $this->storagePath . '/' . date('YmdHi') . '/';
  }

  /**
   * Fetch results (sessions and classes) from Traction Rec and save into file.
   */
  public function fetch(): string {
    $this->fetchProgramAndCategories();
    $this->fetchClasses();
    $this->fetchSessions();

    // Instantiate our event.
    $event = new TractionRecPostFetchEvent($this->directory);
    // Get the event_dispatcher service and dispatch the event.
    $this->eventDispatcher->dispatch( $event, TractionRecPostFetchEvent::EVENT_NAME);
    return $this->directory;
  }

  /**
   * Fetches sessions data.
   *
   * @return array
   *   The array of fetched data.
   *
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function fetchSessions() {
    $result = $this->tractionRec->loadCourseOptions();

    if (empty($result['records'])) {
      return [];
    }

    $dumper = new JsonStreamDumper($this->buildFilename('sessions'));
    $dumper->pushMultiple($result['records']);

    if (isset($result['nextRecordsUrl']) && !empty($result['nextRecordsUrl'])) {
      $url = $result['nextRecordsUrl'];
      $this->paginationFetch($url, $dumper);
    }

    $dumper->close();
  }

  /**
   * Fetches all pages of results recursively.
   *
   * @param string $nextUrl
   *   The URL of the next results page.
   * @param \Drupal\openy_traction_rec_import\JsonStreamDumper $dumper
   *   Json dumper.
   *
   * @return array
   *   The array with fetched data.
   *
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function paginationFetch(string $nextUrl, JsonStreamDumper $dumper): array {
    $result = $this->tractionRec->loadNextPage($nextUrl);

    if (empty($result['records'])) {
      return [];
    }

    $dumper->pushMultiple($result['records']);

    if (isset($result['nextRecordsUrl']) && !empty($result['nextRecordsUrl'])) {
      $url = $result['nextRecordsUrl'];
      $this->paginationFetch($url, $dumper);
    }

    return $result['records'];
  }

  /**
   * Pulls location object from Traction Rec.
   *
   * @return array
   *   The array of fetched locations.
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
   */
  public function fetchClasses():void {
    $result = $this->tractionRec->loadCourses();

    if (empty($result['records'])) {
      return;
    }

    $this->dumpToJson($result['records'], $this->buildFilename('classes'));
  }

  /**
   * Fetches the program data.
   */
  public function fetchProgramAndCategories():void {
    $result = $this->tractionRec->loadProgramCategoryTags();

    if (empty($result['records'])) {
      return;
    }

    $programs = [];
    $categories = [];
    foreach ($result['records'] as $key => $category_tag) {
      // It's confusing, but in Open Y terms we have a reverse structure:
      // Traction Rec Program -> Open Y Program Sub Category.
      // Traction Rec Program Category -> Open Y Program.
      $programs[$category_tag['Program_Category']['Id']] = $category_tag['Program_Category'];

      $category = $category_tag['Program'];
      $category['Program'] = $category_tag['Program_Category']['Id'];

      // Only set the new category if its Id is unique. In TREC it is possible
      // for a Program to exist under multiple Categories, but we do not allow
      // that relationship. This may result in some data loss.
      // @TODO: we should figure out how to deal with this better.
      if (!in_array($category['Id'], array_column($categories, 'Id'))) {
        $categories[] = $category;
      }

      unset($result['records'][$key]);
    }

    $this->dumpToJson(array_values($programs), $this->buildFilename('programs'));
    $this->dumpToJson($categories, $this->buildFilename('program_categories'));
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
