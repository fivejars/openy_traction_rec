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

  /**
   * The settings of dropzonejs.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $salesforceSettings;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $http;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * SalesforceFetcher Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \GuzzleHttp\Client $http
   *   The http client.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http, FileSystemInterface $file_system) {
    $this->salesforceSettings = $config_factory->get('ypkc_salesforce.settings');
    $this->http = $http;
    $this->fileSystem = $file_system;
  }

  /**
   * Fetch results (sessions and classes) from Salesforce and save into file.
   */
  public function fetch() {
    $claim = [
      'iss' => $this->salesforceSettings->get('consumer_key'),
      'sub' => $this->salesforceSettings->get('login_user'),
      'aud' => $this->salesforceSettings->get('login_url'),
      'exp' => strval(time() + 60),
    ];

    $private_key = $this->salesforceSettings->get('private_key');
    $token = JWT::encode($claim, $private_key, 'RS256');
    $token_url = $this->salesforceSettings->get('login_url') . '/services/oauth2/token';

    $post_fields = [
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $token,
    ];

    try {
      $response = $this->http->request('POST', $token_url, [
        'form_params' => $post_fields,
      ]);
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
    }

    $token_request_body = $response->getBody()->getContents();

    $access_token = json_decode($token_request_body);
    $access_token = $access_token->access_token;

    $result = $this->makeQuery('SELECT
      TREX1__Available_Online__c,
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
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Description__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Rich_Description__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__c,
      TREX1__Course_Session__r.TREX1__Course__r.TREX1__Program__r.name
    FROM TREX1__Course_Session_Option__c', $access_token);

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

    $filename = $this->storagePath . 'results_' . time() . '.json';

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
  private function simplify(array $array) {
    $new_array = [];
    foreach ($array as $key => $value) {
      $new_key = str_replace(['TREX1__', '__c', '__r'], '', $key);
      if ($new_key === 'attributes') {
        continue;
      }
      elseif (is_array($value)) {
        $new_array[$new_key] = $this->simplify($value);
      }
      else {
        $new_array[$new_key] = $value;
      }
    }
    return $new_array;
  }

  /**
   * Make request to Salesforce.
   *
   * @param string $query
   *   SOQL query.
   * @param string $access_token
   *   Salesforce access token.
   *
   * @return array
   *   Retrieved results from Salesforce.
   */
  private function makeQuery($query, $access_token) {
    $query_url = 'https://open-y-rec-dev-ed.my.salesforce.com/services/data/v49.0/query/';
    try {
      $response = $this->http->request('GET', $query_url, [
        'query' => [
          'q' => $query,
        ],
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);

    }
    catch (RequestException $e) {
      $response = $e->getResponse();
    }

    $query_request_body = $response->getBody()->getContents();

    $data = json_decode($query_request_body, TRUE);
    return $this->simplify($data);
  }

}
