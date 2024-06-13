<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_sso;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Traction Rec SSO client.
 */
class TractionRecSsoClient implements TractionRecSsoClientInterface {

  /**
   * The Traction Rec settings.
   */
  protected ImmutableConfig $ssoSettings;

  /**
   * The http client.
   */
  protected Client $http;

  /**
   * Logger for salesforce queries.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Request stack.
   */
  protected ?Request $request;

  /**
   * Traction Rec SOO callback uri.
   */
  protected string $callbackUri;

  /**
   * Traction Rec access token for SSO login through web.
   */
  protected string $webToken = '';

  /**
   * Client constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \GuzzleHttp\Client $http
   *   The http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http, LoggerChannelFactoryInterface $logger_channel_factory, RequestStack $request_stack) {
    $this->ssoSettings = $config_factory->get('openy_traction_rec_sso.settings');
    $this->http = $http;
    $this->logger = $logger_channel_factory->get('traction_rec');
    $this->request = $request_stack->getCurrentRequest();
    $this->callbackUri = Url::fromRoute('openy_traction_rec_sso.oauth_callback')->setAbsolute()->toString();

    if ($code = $this->request->get('code')) {
      $this->webToken = $this->generateToken($code);
    }
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
      $response = $this->http->post($this->ssoSettings->get('app_url') . '/services/oauth2/token',
        [
          'form_params' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->ssoSettings->get('consumer_key'),
            'client_secret' => $this->ssoSettings->get('consumer_secret'),
            'redirect_uri' => $this->callbackUri,
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
      $user_data = $this->http->post($this->ssoSettings->get('app_url') . '/services/oauth2/userinfo',
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
  public function getLoginUrl(): string {
    return $this->ssoSettings->get('app_url') . '/services/oauth2/authorize?client_id='
      . $this->ssoSettings->get('consumer_key')
      . '&redirect_uri=' . $this->callbackUri
    // @todo Fix problem with scopes here.
    // Should be 'api id' according to https://quip.com/UKZ6ABw4YynH
    // 'Community User Authentication Example with cURL targeting test' section.
      . '&response_type=code&state=&scope=api';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountUrl(): string {
    return $this->ssoSettings->get('app_url') ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function isWebTokenNotEmpty(): bool {
    return !empty($this->webToken);
  }

}
