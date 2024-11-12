<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_sso\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\openy_traction_rec_sso\TractionRecSsoClientInterface;
use Drupal\openy_traction_rec_sso\TractionRecUserAuthorizer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class Traction Rec SSO Controller.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TractionRecSsoController extends ControllerBase {

  /**
   * Log out path in SalesForce.
   */
  const SF_LOGOUT_PATH = '/secur/logout.jsp';

  /**
   * TractionRec SSO Client service instance.
   */
  protected TractionRecSsoClientInterface $tractionRecSsoClient;

  /**
   * The YGS User Authorizer.
   */
  protected TractionRecUserAuthorizer $userAuthorizer;

  /**
   * The session.
   */
  protected Session $session;

  /**
   * {@inheritdoc}
   */
  public function __construct(TractionRecSsoClientInterface $client, TractionRecUserAuthorizer $userAuthorizer, Session $session) {
    $this->tractionRecSsoClient = $client;
    $this->userAuthorizer = $userAuthorizer;
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('openy_traction_rec.sso_client'),
      $container->get('openy_traction_rec.user_authorizer'),
      $container->get('session')
    );
  }

  /**
   * Callback for register/login user based on TractionRec response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns Redirect to the page where from user was logged in.
   */
  public function oauthCallback(Request $request): RedirectResponse {
    $response = new TrustedRedirectResponse('/');
    if ($this->tractionRecSsoClient->isWebTokenNotEmpty()) {
      try {
        $config = $this->config('openy_traction_rec_sso.settings');

        if ($config->get('sso_user_authenticated')) {
          $user_data = $this->tractionRecSsoClient->getUserData();
          if (!$user_data) {
            throw new AccessDeniedHttpException();
          }
          // Create drupal user if it doesn't exist and login it.
          $this->userAuthorizer->authorizeUser($user_data->email, (array) $user_data);
          $this->session->remove('check_logged_in');
        }

        $cookie_domain = '.' . $request->getHost();
        $response->headers->setCookie(
          Cookie::create(name: 'tr_sso_logged_in', value: '1', expire: strtotime('+30 day'), path: '/', domain: $cookie_domain, httpOnly: FALSE)
        );
        $redirect_url = $config->get('app_url');
        $response->setTrustedTargetUrl($redirect_url);
      }
      catch (\Exception $e) {
        $this->getLogger('traction_rec_sso')->error($this->t('TractionRec auth failed: @message', ['@message' => $e->getMessage()]));
        $this->messenger()->addError($this->t('Unable to authenticate user. Please contact website administrator.'));
      }
    }
    else {
      $this->getLogger('traction_rec_sso')->error($this->t('No OAuth config found. Please try again.'));
      $this->messenger()->addError($this->t('Unable to authenticate user. Please contact website administrator.'));
      throw new AccessDeniedHttpException();
    }
    return $response;
  }

  /**
   * Redirect to SSO login.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns Redirect to the previous page where from user logged out.
   */
  public function login(): RedirectResponse {
    return new TrustedRedirectResponse($this->tractionRecSsoClient->getLoginUrl());
  }

  /**
   * Redirect to SSO account.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns Redirect to the previous page where from user logged out.
   */
  public function account(): RedirectResponse {
    return new TrustedRedirectResponse($this->tractionRecSsoClient->getAccountUrl());
  }

  /**
   * Callback for SSO logout.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns Redirect to the previous page where from user logged out.
   */
  public function logout(Request $request): RedirectResponse {
    $config = $this->config('openy_traction_rec_sso.settings');

    if ($config->get('sso_user_authenticated')) {
      user_logout();
    }

    $app_url = $config->get('app_url');

    $response = new TrustedRedirectResponse($app_url . self::SF_LOGOUT_PATH);
    $cookie_domain = '.' . $request->getHost();
    $response->headers->setCookie(
      Cookie::create(name: 'tr_sso_logged_in', value: '', expire: strtotime('+30 day'), path: '/', domain: $cookie_domain, httpOnly: FALSE)
    );
    return $response;
  }

}
