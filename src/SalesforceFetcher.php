<?php

namespace Drupal\ypkc_salesforce;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Contains related to fetching from Salesforce functionality.
 */
class SalesforceFetcher {

  /**
   * Result json directory path.
   *
   * @var string
   */
  protected $storagePath = 'private://salesforce_import/';

  protected $tractionRecClient;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * SalesforceFetcher Constructor.
   *
   * @param \Drupal\ypkc_salesforce\TractionRecClient $traction_rec_client
   *   The Traction Rec client setvice.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   */
  public function __construct(TractionRecClient $traction_rec_client, FileSystemInterface $file_system) {
    $this->tractionRecClient = $traction_rec_client;
    $this->fileSystem = $file_system;
  }

  /**
   * Fetch results (sessions and classes) from Salesforce and save into file.
   */
  public function fetch() {
    $result = $this->tractionRecClient->executeQuery('SELECT
      TREX1__Available_Online__c,
      TREX1__Course_Option__r.id,
      TREX1__Course_Option__r.name,
      TREX1__Course_Option__r.TREX1__Code__c,
      TREX1__Course_Option__r.TREX1__Available_Online__c,
      TREX1__Course_Option__r.TREX1__capacity__c,
      TREX1__Course_Option__r.TREX1__Start_Date__c,
      TREX1__Course_Option__r.TREX1__Start_Time__c,
      TREX1__Course_Option__r.TREX1__End_Date__c,
      TREX1__Course_Option__r.TREX1__End_Time__c,
      TREX1__Course_Option__r.TREX1__Day_of_Week__c,
      TREX1__Course_Option__r.TREX1__Instructor__c,
      TREX1__Course_Option__r.TREX1__Location__c,
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
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Rich_Description__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__r.id,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__r.name
    FROM TREX1__Course_Session_Option__c');

    $result = $this->simplify($result);
    $this->saveResultsToJson($result);
  }

  /**
   * Save retrieved results into json.
   *
   * @param array $data
   *   Data to save.
   */
  private function saveResultsToJson(array $data) {
    $this->fileSystem->prepareDirectory($this->storagePath, FileSystemInterface::CREATE_DIRECTORY);

    $parents = $this->pullProgramsAndClasses($data);
    $this->dumpJson(array_values($parents['programs']), $this->storagePath . 'programs_' . time() . '.json');
    $this->dumpJson(array_values($parents['classes']), $this->storagePath . 'classes_' . time() . '.json');
    $this->dumpJson($data, $this->storagePath . 'sessions_' . time() . '.json');
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
    if (empty($data['records'])) {
      return [];
    }

    $programs = [];
    $classes = [];
    foreach ($data['records'] as $item) {
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
  protected function dumpJson(array $data, $filename) {
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

}
