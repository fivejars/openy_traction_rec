<?php

namespace Drupal\ypkc_salesforce;

use Drupal\Core\File\FileSystemInterface;

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
   * Traction Rec Client service.
   *
   * @var \Drupal\ypkc_salesforce\TractionRecClient
   */
  protected $tractionRecClient;

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
   * @param \Drupal\ypkc_salesforce\TractionRecClient $traction_rec_client
   *   The Traction Rec client service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   */
  public function __construct(TractionRecClient $traction_rec_client, FileSystemInterface $file_system) {
    $this->tractionRecClient = $traction_rec_client;
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
    $result = $this->tractionRecClient->executeQuery('SELECT
      TREX1__Course_Option__r.id,
      TREX1__Course_Option__r.name,
      TREX1__Course_Option__r.TREX1__Available_Online__c,
      TREX1__Course_Option__r.TREX1__Available__c,
      TREX1__Course_Option__r.TREX1__capacity__c,
      TREX1__Course_Option__r.TREX1__Start_Date__c,
      TREX1__Course_Option__r.TREX1__Start_Time__c,
      TREX1__Course_Option__r.TREX1__End_Date__c,
      TREX1__Course_Option__r.TREX1__End_Time__c,
      TREX1__Course_Option__r.TREX1__Day_of_Week__c,
      TREX1__Course_Option__r.TREX1__Instructor__c,
      TREX1__Course_Option__r.TREX1__Location__c,
      TREX1__Course_Option__r.TREX1__Location__r.id,
      TREX1__Course_Option__r.TREX1__Location__r.name,
      TREX1__Course_Option__r.TREX1__Age_Max__c,
      TREX1__Course_Option__r.TREX1__Age_Min__c,
      TREX1__Course_Option__r.TREX1__Register_Online_From_Date__c,
      TREX1__Course_Option__r.TREX1__Register_Online_From_Time__c,
      TREX1__Course_Option__r.TREX1__Register_Online_To_Date__c,
      TREX1__Course_Option__r.TREX1__Register_Online_To_Time__c,
      TREX1__Course_Option__r.TREX1__Registration_Total__c,
      TREX1__Course_Option__r.TREX1__Total_Capacity_Available__c,
      TREX1__Course_Option__r.TREX1__Type__c,
      TREX1__Course_Option__r.TREX1__Unlimited_Capacity__c,
      TREX1__Course_Session__r.TREX1__Course__r.name,
      TREX1__Course_Session__r.TREX1__Course__r.id,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Description__c,
      TREX1__Course_Option__r.TREX1__Product__c,
      TREX1__Course_Option__r.TREX1__Product__r.id,
      TREX1__Course_Option__r.TREX1__Product__r.name,
      TREX1__Course_Option__r.TREX1__Product__r.TREX1__Price_Description__c
    FROM TREX1__Course_Session_Option__c WHERE TREX1__Course_Option__r.TREX1__Available_Online__c = true');

    $result = $this->simplify($result);

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
  protected function paginationFetch(string $nextUrl, JsonStreamDumper $dumper) {
    $result = $this->tractionRecClient->send('GET', 'https://ymcapkc.my.salesforce.com' . $nextUrl);
    $result = $this->simplify($result);

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
   *
   * @throws \Drupal\ypkc_salesforce\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function fetchLocations(): array {
    $result = $this->tractionRecClient->executeQuery('SELECT
      TREX1__Location__c.id,
      TREX1__Location__c.name,
      TREX1__Location__c.TREX1__Address_City__c,
      TREX1__Location__c.TREX1__Address_Country__c,
      TREX1__Location__c.TREX1__Address_State__c,
      TREX1__Location__c.TREX1__Address_Street__c,
      TREX1__Location__c.TREX1__Address_Postal_Code__c
    FROM TREX1__Location__c');

    $result = $this->simplify($result);

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
   * @throws \Drupal\ypkc_salesforce\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function fetchClasses():void {
    $result = $this->tractionRecClient->executeQuery('SELECT
      TREX1__Course__c.id,
      TREX1__Course__c.name,
      TREX1__Course__c.TREX1__Description__c,
      TREX1__Course__c.TREX1__Rich_Description__c,
      TREX1__Course__c.TREX1__Program__r.id,
      TREX1__Course__c.TREX1__Program__r.name,
      TREX1__Course__c.TREX1__Available__c
    FROM TREX1__Course__c WHERE TREX1__Available_Online__c = true');

    $result = $this->simplify($result);

    if (empty($result['records'])) {
      return;
    }

    $this->dumpToJson($result['records'], $this->buildFilename('classes'));
  }

  /**
   * Fetches the program data.
   *
   * @throws \Drupal\ypkc_salesforce\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function fetchProgramAndCategories():void {
    $result = $this->tractionRecClient->executeQuery(
      'SELECT
      TREX1__Program_Category_Tag__c.id,
      TREX1__Program_Category_Tag__c.name,
      TREX1__Program_Category_Tag__c.TREX1__Program__r.id,
      TREX1__Program_Category_Tag__c.TREX1__Program__r.name,
      TREX1__Program_Category_Tag__c.TREX1__Program__r.TREX1__Available__c,
      TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.id,
      TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.name,
      TREX1__Program_Category_Tag__c.TREX1__Program_Category__r.TREX1__Available__c
    FROM TREX1__Program_Category_Tag__c WHERE TREX1__Program__r.TREX1__Available_Online__c = true AND TREX1__Program_Category__r.TREX1__Available_Online__c = true'
    );

    $result = $this->simplify($result);

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
   * Clean up TractionRec extra prefixes and suffixes for easier usage.
   *
   * @param array $array
   *   Response result from Salesfoce.
   *
   * @return array
   *   Results with cleaned keys.
   */
  private function simplify(array $array): array {
    $new_array = [];
    foreach ($array as $key => $value) {
      $new_key = str_replace(['TREX1__', '__c', '__r'], '', $key);
      if ($new_key === 'attributes') {
        continue;
      }
      if (is_array($value)) {
        $new_array[$new_key] = $this->simplify($value);
      }
      else {
        $new_array[$new_key] = $value;
      }
    }
    return $new_array;
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
