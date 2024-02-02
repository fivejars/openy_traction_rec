<?php

namespace Drupal\openy_traction_rec;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Traction Rec HTTP client.
 */
class TractionRecClient {

  /**
   * The Traction Rec settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $tractionRecSettings;

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
   * Traction Rec API credentials for SSO.
   *
   * ['credentials', 'redirect_uri']
   *
   * @var array
   */
  protected $tractionRecSsoSettings;

  /**
   * Logger for traction_rec queries.
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
   * Traction Rec access token for SSO login through web.
   *
   * @var string
   */
  protected $webToken = '';

  /**
   * Key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

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
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http, TimeInterface $time, LoggerChannelFactoryInterface $logger_channel_factory, RequestStack $request_stack, KeyRepositoryInterface $keyRepository) {
    $this->tractionRecSettings = $config_factory->get('openy_traction_rec.settings');
    $this->tractionRecSsoSettings = $config_factory->get('openy_traction_rec_sso.settings');
    $this->http = $http;
    $this->time = $time;
    $this->logger = $logger_channel_factory->get('traction_rec');
    $this->keyRepository = $keyRepository;
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
  public function getAccessToken(): string {
    if (!empty($this->accessToken)) {
      return $this->accessToken;
    }

    $token = $this->generateAssertion();
    $token_url = $this->tractionRecSettings->get('login_url') . '/services/oauth2/token';

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
      'iss' => $this->tractionRecSettings->get('consumer_key'),
      'sub' => $this->tractionRecSettings->get('login_user'),
      'aud' => $this->tractionRecSettings->get('login_url'),
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
    $key_id = $this->tractionRecSettings->get('private_key');
    $key = $this->keyRepository->getKey($key_id)->getKeyValue();
    $token = $this->generateAssertionClaim();
    return JWT::encode($token, $key, 'RS256');
  }

  /**
   * Make request to Traction Rec.
   *
   * @param string $query
   *   SOQL query.
   *
   * @return array
   *   Retrieved results from Traction Rec.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  public function executeQuery(string $query): array {
    $access_token = $this->getAccessToken();
    if (!$access_token) {
      throw new InvalidTokenException();
    }

    $query_url = $this->tractionRecSettings->get('services_base_url') . 'query/';
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
      $this->logger->error($e->getMessage());
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
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function send(string $method, string $url, array $options = []) {
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
   *   Access code from Traction Rec.
   *
   * @return string
   *   Access token.
   */
  private function generateToken(string $code): string {
    try {
      $response = $this->http->post($this->tractionRecSsoSettings->get('app_url') . '/services/oauth2/token',
        [
          'form_params' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->tractionRecSsoSettings->get('client_id'),
            'client_secret' => $this->tractionRecSsoSettings->get('client_secret'),
            'redirect_uri' => 'https://' . $this->request->getHost() . $this->tractionRecSsoSettings->get('redirect_uri'),
          ],
        ]);

      return json_decode($response->getBody())->access_token;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return '';
    }
  }

  /**
   * Get user data from Traction Rec.
   *
   * @return object|null
   *   User info from Traction Rec.
   */
  public function getUserData() {
    try {
      if (!$this->webToken) {
        return NULL;
      }

      $headers = ['Authorization' => 'Bearer ' . $this->webToken];
      $user_data = $this->http->post($this->tractionRecSsoSettings->get('app_url') . '/services/oauth2/userinfo',
        ['headers' => $headers]
      );

      return json_decode($user_data->getBody());
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return NULL;
    }
  }

  /**
   * Construct link for login in Traction Rec app.
   *
   * @return string
   *   Link to login form.
   */
  public function getLoginLink(): string {
    if (empty($this->tractionRecSsoSettings->get('app_url'))) {
      return '';
    }
    return $this->tractionRecSsoSettings->get('app_url') . '/services/oauth2/authorize?client_id='
      . $this->tractionRecSsoSettings->get('client_id')
      . '&redirect_uri=https://' . $this->request->getHost() . $this->tractionRecSsoSettings->get('redirect_uri')
    // @todo Fix problem with scopes here.
    // Should be 'api id' according to https://quip.com/UKZ6ABw4YynH
    // 'Community User Authentication Example with cURL targeting test' section.
      . '&response_type=code&state=&scope=api';
  }

  /**
   * Returns link for homepage(account) in Traction Rec app.
   *
   * @return string
   *   Link to account page.
   */
  public function getAccountLink(): string {
    return $this->tractionRecSsoSettings->get('app_url') ?? '';
  }

  /**
   * Check if token is generated.
   *
   * @return bool
   *   Does token is generated.
   */
  public function isWebTokenNotEmpty(): bool {
    return !empty($this->webToken);
  }

}
