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

  protected $data = [];

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
  }

  /**
   * Fetch results (sessions and classes) from Salesforce and save into file.
   */
  public function fetch() {
    $this->fetchPrices();

    $result = $this->tractionRecClient->executeQuery('SELECT
      TREX1__Course_Option__r.id,
      TREX1__Course_Option__r.name,
      TREX1__Course_Option__r.TREX1__Available_Online__c,
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
      TREX1__Course_Option__r.TREX1__Product__c,
      TREX1__Course_Option__r.TREX1__Product__r.id,
      TREX1__Course_Option__r.TREX1__Product__r.name,
      TREX1__Course_Session__r.TREX1__Course__r.name,
      TREX1__Course_Session__r.TREX1__Course__r.id,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Description__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Rich_Description__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__r.id,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__r.name
    FROM TREX1__Course_Session_Option__c');

    $result = $this->simplify($result);

    if (empty($result['records'])) {
      return [];
    }

    $this->data = $result['records'];
    if (isset($result['nextRecordsUrl']) && !empty($result['nextRecordsUrl'])) {
      $url = $result['nextRecordsUrl'];
      $this->paginationFetch($url);
    }

    $this->saveResultsToJson();
  }

  protected function paginationFetch($nextUrl) {
    $result = $this->tractionRecClient->send('GET', 'https://ymcapkc.my.salesforce.com' . $nextUrl);
    $result = $this->simplify($result);

    if (empty($result['records'])) {
      return [];
    }

    $this->data = array_merge($this->data, $result['records']);

    if (isset($result['nextRecordsUrl']) && !empty($result['nextRecordsUrl'])) {
      $url = $result['nextRecordsUrl'];
      $this->paginationFetch($url);
    }
  }

  /**
   *  Pulls location object from Salesforce.
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
    array_filter($result['records'], function(array $location) {
      return empty($location['Address_City']);
    });
    $this->dumpToJson($result, $this->buildFilename('locations'));

    return $result;
  }

  protected function fetchPrices() {
    $result = $this->tractionRecClient->executeQuery('SELECT
      TREX1__Price_Level__c.id,
      TREX1__Price_Level__c.name,
      TREX1__Price_Level__c.TREX1__Product__c,
      TREX1__Price_Level__c.TREX1__Product__r.id,
      TREX1__Price_Level__c.TREX1__Product__r.name,
      TREX1__Price_Level__c.TREX1__Initial_Fee_Amount__c,
      TREX1__Price_Level__c.TREX1__Hourly_Rate__c,
      TREX1__Price_Level__c.TREX1__Deposit_Fee_Amount__c,
      TREX1__Price_Level__c.TREX1__Commission_Fixed_Amount__c,
      TREX1__Price_Level__c.TREX1__Booking_Price__c,
      TREX1__Price_Level__c.TREX1__Price_Type__c
    FROM TREX1__Price_Level__c');

    $result = $this->simplify($result);

    if (empty($result['records'])) {
      return [];
    }

    $this->dumpToJson($result, $this->buildFilename('price_levels'));

    return $result;
  }

  /**
   * Save retrieved results into json.
   *
   * @param array $data
   *   Data to save.
   */
  private function saveResultsToJson() {
    $parents = $this->pullProgramsAndClasses($this->data);
    $this->dumpToJson(array_values($parents['programs']), $this->buildFilename('programs'));
    $this->dumpToJson(array_values($parents['classes']), $this->buildFilename('classes'));
    $this->dumpToJson($this->data, $this->buildFilename('sessions'));
  }

  /**
   * Pulls programs and classes data separately.
   *
   * @param array $data
   *   The array of query results.
   *
   * @return array[]
   *   The array of programs and classes.
   */
  protected function pullProgramsAndClasses(array $data): array {
    if (!$data) {
      return ['classes' => [], 'programs' => []];
    }

    $programs = [];
    $classes = [];
    foreach ($data as $item) {
      if (!isset($item['Course_Session']) || !isset($item['Course_Session']['Course'])) {
        continue;
      }

      $classes[$item['Course_Session']['Course']['Id']] = $item['Course_Session']['Course'];
      $programs[$item['Course_Session']['Course']['Program']['Id']] = $item['Course_Session']['Course']['Program'];
    }

    return ['classes' => $classes, 'programs' => $programs];
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
    $dir_name = $this->storagePath . '/' . date('YmdHi') . '/';
    $this->fileSystem->prepareDirectory($dir_name, FileSystemInterface::CREATE_DIRECTORY);
    return $dir_name . $items_type . '.json';
  }

}
