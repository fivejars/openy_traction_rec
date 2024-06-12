<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\key\KeyRepositoryInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * Traction Rec API credentials for SSO.
   */
  protected ImmutableConfig $tractionRecSsoSettings;

  /**
   * Logger for salesforce queries.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Request stack.
   */
  protected ?Request $request;

  /**
   * Traction Rec access token for SSO login through web.
   */
  protected string $webToken = '';

  /**
   * Key repository service.
   */
  protected KeyRepositoryInterface $keyRepository;

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
   * {@inheritdoc}
   */
  public function send(string $method, string $url, array $options = []): mixed {
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

      return json_decode((string) $response->getBody())->access_token;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUserData(): ?object {
    try {
      if (!$this->webToken) {
        return NULL;
      }

      $headers = ['Authorization' => 'Bearer ' . $this->webToken];
      $user_data = $this->http->post($this->tractionRecSsoSettings->get('app_url') . '/services/oauth2/userinfo',
        ['headers' => $headers]
      );

      return json_decode((string) $user_data->getBody());
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginLink(): string {
    return $this->tractionRecSsoSettings->get('app_url') . '/services/oauth2/authorize?client_id='
      . $this->tractionRecSsoSettings->get('client_id')
      . '&redirect_uri=https://' . $this->request->getHost() . $this->tractionRecSsoSettings->get('redirect_uri')
    // @todo Fix problem with scopes here.
    // Should be 'api id' according to https://quip.com/UKZ6ABw4YynH
    // 'Community User Authentication Example with cURL targeting test' section.
      . '&response_type=code&state=&scope=api';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountLink(): string {
    return $this->tractionRecSsoSettings->get('app_url') ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function isWebTokenNotEmpty(): bool {
    return !empty($this->webToken);
  }

}
