<?php

namespace Drupal\ypkc_salesforce;

use Drupal\Core\File\FileSystemInterface;
use TractionRecInterface;

/**
 * Contains related to fetching from Salesforce functionality.
 */
class SalesforceFetcher {

  /**
   * Result json directory path.
   *
   * @var string
   */
  protected $storagePath = 'private://salesforce_import/json/';

  /**
   * Traction Rec wrapper.
   *
   * @var \TractionRecInterface
   */
  protected $tractionRec;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * JSON Directory name.
   *
   * @var string
   */
  protected $directory;

  /**
   * SalesforceFetcher Constructor.
   *
   * @param \TractionRecInterface $traction_rec
   *   The Traction Rec wrapper.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   */
  public function __construct(TractionRecInterface $traction_rec, FileSystemInterface $file_system) {
    $this->tractionRec = $traction_rec;
    $this->fileSystem = $file_system;

    $this->fileSystem->prepareDirectory($this->storagePath, FileSystemInterface::CREATE_DIRECTORY);
    $this->directory = $this->storagePath . '/' . date('YmdHi') . '/';
  }

  /**
   * Fetch results (sessions and classes) from Salesforce and save into file.
   */
  public function fetch() {
    $this->fetchProgramAndCategories();
    $this->fetchClasses();
    $this->fetchSessions();
  }

  /**
   * Fetches sessions data.
   *
   * @return array
   *   The array of fetched data.
   *
   * @throws \Drupal\ypkc_salesforce\InvalidTokenException
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
   * @param \Drupal\ypkc_salesforce\JsonStreamDumper $dumper
   *   Json dumper.
   *
   * @return array
   *   The array with fetched data.
   *
   * @throws \Drupal\ypkc_salesforce\InvalidTokenException
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
   * Pulls location object from Salesforce.
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
      // It's confusing, but in Open Y terms we we have vice verse structure:
      // Traction Rec Program -> Open Y Program Sub Category.
      // Traction Rec Program Category -> Open Y Program.
      $programs[$category_tag['Program_Category']['Id']] = $category_tag['Program_Category'];

      $category = $category_tag['Program'];
      $category['Program'] = $category_tag['Program_Category']['Id'];
      $categories[] = $category;

      unset($result['records'][$key]);
    }

    $this->dumpToJson(array_values($programs), $this->buildFilename('programs'));
    $this->dumpToJson($categories, $this->buildFilename('program_categories'));
  }

  /**
   * Saves data into JSON file.
   *
   * @param array $data
   *   The array of data.
   * @param string $filename
   *   The filename.
   */
  protected function dumpToJson(array $data, string $filename) {
    $file = fopen($filename, 'w');
    fwrite($file, json_encode($data, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
    fclose($file);
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
