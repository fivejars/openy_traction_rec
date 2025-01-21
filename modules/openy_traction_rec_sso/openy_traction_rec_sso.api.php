<?php

/**
 * @file
 * Contains hook examples for the TR SSO module.
 */

declare(strict_types=1);

use Drupal\user\UserInterface;

/**
 * Alter user entity before save during the authorization process.
 *
 * @param \Drupal\user\UserInterface $user
 *   The user.
 * @param array $user_data
 *   The user data from Traction Rec.
 */
function hook_openy_traction_rec_sso_authorize_user_data_alter(UserInterface &$user, array $user_data): void {
  $user->set('field_dummy', $user_data['dummy_data']);
}
