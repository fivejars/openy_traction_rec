<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\openy_traction_rec\QueryBuilder\QueryBuilderInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Traction Rec HTTP client.
 */
class TractionRecClient implements TractionRecClientInterface {

  /**
   * The Traction Rec settings.
   */
  protected ImmutableConfig $tractionRecSettings;

  /**
   * The http client.
   */
  protected Client $http;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * Access token.
   */
  protected string $accessToken;

  /**
   * Logger for salesforce queries.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Traction Rec access token for SSO login through web.
   */
  protected string $webToken = '';

  /**
   * Key repository service.
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * Module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

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
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http, TimeInterface $time, LoggerChannelFactoryInterface $logger_channel_factory, KeyRepositoryInterface $keyRepository, ModuleHandlerInterface $module_handler) {
    $this->tractionRecSettings = $config_factory->get('openy_traction_rec.settings');
    $this->http = $http;
    $this->time = $time;
    $this->logger = $logger_channel_factory->get('traction_rec');
    $this->keyRepository = $keyRepository;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
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
      $this->logger->error($e->getMessage());
    }

    $token_request_body = $response->getBody()->getContents();

    $access_token = json_decode($token_request_body);
    // Return empty string if no token can be retrieved.
    // Error will be logged elsewhere.
    if (isset($access_token->error)) {
      return '';
    }

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
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  protected function generateAssertion(): string {
    $key_id = $this->tractionRecSettings->get('private_key');
    $key = $this->keyRepository->getKey($key_id)->getKeyValue();
    $token = $this->generateAssertionClaim();
    return JWT::encode($token, $key, 'RS256');
  }

  /**
   * {@inheritdoc}
   */
  public function executeQuery(QueryBuilderInterface $query): array {
    $access_token = $this->getAccessToken();
    if (!$access_token) {
      throw new InvalidTokenException('Invalid access token');
    }

    $query_url = $this->tractionRecSettings->get('services_base_url') . 'query/';
    try {
      $this->moduleHandler->alter('openy_traction_rec_api_query', $query);

      $response = $this->http->request('GET', $query_url, [
        'query' => [
          'q' => $query->build(),
        ],
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);

    }
    catch (RequestException $e) {
      $message = $e->getMessage();
      $this->logger->error($message);

      // Try to get shorter error message from response.
      $response = $e->getResponse();
      $contents = $response->getBody()->getContents();

      if (!empty($contents)) {
        $contents = json_decode($contents, TRUE);
        $message = $contents[0]['message'] ?? $message;
      }
      throw new InvalidResponseException($message);
    }

    $query_request_body = $response->getBody()->getContents();

    return json_decode($query_request_body, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function send(string $method, string $url, array $options = []): mixed {
    $access_token = $this->getAccessToken();
    if (!$access_token) {
      throw new InvalidTokenException('Invalid access token');
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

}
