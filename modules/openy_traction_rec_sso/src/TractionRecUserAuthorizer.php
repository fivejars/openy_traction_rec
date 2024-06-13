<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_sso;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\User\UserStorageInterface;

/**
 * User Authorizer class.
 */
class TractionRecUserAuthorizer {

  /**
   * User entity storage.
   */
  protected UserStorageInterface $userStorage;

  /**
   * Password Generator.
   */
  protected PasswordGeneratorInterface $passGen;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Password\PasswordGeneratorInterface $passGen
   *   The password generator.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, PasswordGeneratorInterface $passGen, ModuleHandlerInterface $moduleHandler) {
    $this->userStorage = $entityTypeManager->getStorage('user');
    $this->passGen = $passGen;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Authorize user by email.
   */
  public function authorizeUser(string $email, array $user_data = []): void {
    if (empty($email)) {
      return;
    }

    // Create drupal user if it doesn't exist and login it.
    $users = $this->userStorage->loadByProperties(['mail' => $email]);
    $account = $users ? reset($users) : FALSE;

    // Create user object if it's not.
    if (!$account) {
      $account = $this->userStorage->create();
      $account->enforceIsNew();
      $account->setEmail($email);
      $account->setUsername($email);
      $account->setPassword($this->passGen->generate());
    }

    $altered_account = clone $account;

    if (!$altered_account->isActive()) {
      $altered_account->activate();
    }

    // @see hook_openy_traction_rec_sso_authorize_user_data_alter().
    $this->moduleHandler->alter('openy_traction_rec_sso_authorize_user_data', $altered_account, $user_data);

    // If account is new or some values were updated.
    if ($altered_account->isNew() || $altered_account->toArray() !== $account->toArray()) {
      $altered_account->save();
    }

    user_login_finalize($altered_account);
  }

}
