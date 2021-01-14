<?php

namespace Drupal\ypkc_salesforce;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Salesforce HTTP client.
 */
class TractionRecClient {

  /**
   * The Salesforce settings.
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
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Access token.
   *
   * @var string
   */
  protected $accessToken;

  /**
   * Client constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \GuzzleHttp\Client $http
   *   The http client.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http, TimeInterface $time) {
    $this->salesforceSettings = $config_factory->get('ypkc_salesforce.settings');
    $this->http = $http;
    $this->time = $time;
  }

  /**
   * Retrieves the access token.
   *
   * @return string
   *   Loaded access token.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAccessToken() {
    if (!empty($this->accessToken)) {
      return $this->accessToken;
    }

    $token = $this->generateAssertion();
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
    $this->accessToken = $access_token->access_token;
    return $this->accessToken;
  }

  /**
   * Returns a JSON encoded JWT Claim.
   *
   * @return array
   *   The claim array.
   */
  protected function generateAssertionClaim(): array {
    return [
      'iss' => $this->salesforceSettings->get('consumer_key'),
      'sub' => $this->salesforceSettings->get('login_user'),
      'aud' => $this->salesforceSettings->get('login_url'),
      'exp' => $this->time->getCurrentTime() + 60,
    ];
  }

  /**
   * Returns a JWT Assertion to authenticate.
   *
   * @return string
   *   JWT Assertion.
   */
  protected function generateAssertion(): string {
    $key = $this->salesforceSettings->get('private_key');
    $token = $this->generateAssertionClaim();
    return JWT::encode($token, $key, 'RS256');
  }

  /**
   * Make request to Salesforce.
   *
   * @param string $query
   *   SOQL query.
   *
   * @return array
   *   Retrieved results from Salesforce.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\ypkc_salesforce\InvalidTokenException
   */
  public function executeQuery($query) {
    $access_token = $this->getAccessToken();
    if (!$access_token) {
      throw new InvalidTokenException();
    }

    $query_url = $this->salesforceSettings->get('services_base_url') . 'query/';
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

    return json_decode($query_request_body, TRUE);
  }

}
