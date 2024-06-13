<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_sso;

/**
 * TractionRec SSO Client interface.
 */
interface TractionRecSsoClientInterface {

  /**
   * Get user data from Traction Rec.
   *
   * @return object|null
   *   User info from Traction Rec.
   */
  public function getUserData(): ?object;

  /**
   * Construct link for login in Traction Rec app.
   *
   * @return string
   *   Url to login form.
   */
  public function getLoginUrl(): string;

  /**
   * Returns link for homepage(account) in Traction Rec app.
   *
   * @return string
   *   Link to account page.
   */
  public function getAccountUrl(): string;

  /**
   * Check if token is generated.
   *
   * @return bool
   *   Does token is generated.
   */
  public function isWebTokenNotEmpty(): bool;

}
