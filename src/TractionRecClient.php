<?php

namespace Drupal\ypkc_salesforce;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * The Salesforce private RSA key.
   *
   * @var string
   */
  protected $salesforcePrivateRsa;

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
   * Salesforce API credentials for SSO.
   *
   * ['credentials', 'redirect_uri']
   *
   * @var array
   */
  protected $salesforceSsoSettings;

  /**
   * Logger for salesforce_sso.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Salesforce access token for SSO login through web.
   *
   * @var string
   */
  protected $webToken = '';

  /**
   * Client constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \GuzzleHttp\Client $http
   *   The http client.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http, TimeInterface $time, LoggerChannelFactoryInterface $logger_channel_factory, RequestStack $request_stack, KeyRepositoryInterface $key_repository) {
    $this->salesforceSettings = $config_factory->get('ypkc_salesforce.settings');
    $this->salesforceSsoSettings = $config_factory->get('ypkc_salesforce_sso.settings');
    $this->salesforcePrivateRsa = $key_repository->getKey('rsa_private_key')->getKeyValue();
    $this->http = $http;
    $this->time = $time;
    $this->logger = $logger_channel_factory->get('salesforce_sso');
    $this->request = $request_stack->getCurrentRequest();
    if ($code = $this->request->get('code')) {
      $this->webToken = $this->generateToken($code);
    }
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
    $key = $this->salesforcePrivateRsa;
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

  /**
   * Sends Traction Rec request.
   *
   * @param string $method
   *   Request method.
   * @param string $url
   *   The URL.
   * @param array $options
   *   The array of request options.
   *
   * @return array|mixed
   *   The array with a response data.
   *
   * @throws \Drupal\ypkc_salesforce\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function send($method, $url, array $options = []) {
    $access_token = $this->getAccessToken();
    if (!$access_token) {
      throw new InvalidTokenException();
    }

    try {
      $options['headers'] = [
        'Authorization' => 'Bearer ' . $access_token,
      ];
      $response = $this->http->request($method, $url, $options);
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
    }

    if (!$response) {
      return [];
    }

    $query_request_body = $response->getBody()->getContents();

    return json_decode($query_request_body, TRUE);
  }

  /**
   * Set access token based on user code after SSO redirect.
   *
   * @param string $code
   *   Access code from Salesforce.
   *
   * @return string
   *   Access token.
   */
  private function generateToken($code) {
    try {
      $response = $this->http->post($this->salesforceSsoSettings->get('app_url') . '/services/oauth2/token',
        [
          'form_params' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->salesforceSsoSettings->get('client_id'),
            'client_secret' => $this->salesforceSsoSettings->get('client_secret'),
            'redirect_uri' => 'https://' . $this->request->getHost() . $this->salesforceSsoSettings->get('redirect_uri'),
          ],
        ]);

      return json_decode($response->getBody())->access_token;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Get user data from Salesforce.
   *
   * @return object|null
   *   User info from Salesforce.
   */
  public function getUserData() {
    try {
      $headers = ['Authorization' => 'Bearer ' . $this->webToken];
      $user_data = $this->http->post($this->salesforceSsoSettings->get('app_url') . '/services/oauth2/userinfo',
        ['headers' => $headers]
      );

      return json_decode($user_data->getBody());
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Construct link for login in Salesforce app.
   *
   * @return string
   *   Link to login form.
   */
  public function getLoginLink() {
    return $this->salesforceSsoSettings->get('app_url') . '/services/oauth2/authorize?client_id='
      . $this->salesforceSsoSettings->get('client_id')
      . '&redirect_uri=https://' . $this->request->getHost() . $this->salesforceSsoSettings->get('redirect_uri')
    // @todo Fix problem with scopes here.
    // Should be 'api id' according to https://quip.com/UKZ6ABw4YynH
    // 'Community User Authentication Example with cURL targeting test' section.
      . '&response_type=code&state=&scope=api';
  }

  /**
   * Returns link for homepage(account) in Salesforce app.
   *
   * @return string
   *   Link to account page.
   */
  public function getAccountLink() {
    return $this->salesforceSsoSettings->get('app_url');
  }

  /**
   * Check if token is generated.
   *
   * @return bool
   *   Does token is generated.
   */
  public function isWebTokenNotEmpty() {
    return !empty($this->webToken);
  }

}
