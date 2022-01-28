<?php

namespace Drupal\openy_traction_rec;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;

/**
 * User Authorizer class.
 */
class TractionRecUserAuthorizer {

  /**
   * User entity storage.
   *
   * @var \Drupal\User\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Password Generator.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passGen;

  /**
   * TractionRecUserAuthorizer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Password\PasswordGeneratorInterface $passGen
   *   Password Generator.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $module_handler, PasswordGeneratorInterface $passGen) {
    $this->moduleHandler = $module_handler;
    $this->userStorage = $entityTypeManager->getStorage('user');
    $this->passGen = $passGen;
  }

  /**
   * {@inheritdoc}
   */
  public function authorizeUser($name, $email) {

    if (empty($name) || empty($email)) {
      return;
    }

    // Create drupal user if it doesn't exist and login it.
    $account = user_load_by_mail($email);
    if (!$account) {
      $user = $this->userStorage->create();
      $user->setPassword($this->passGen->generate());
      $user->enforceIsNew();
      $user->setEmail($email);
      $user->setUsername($email);
      $user->activate();

      // Temp solution: Virtual Y will be removed from the project soon.
      if ($this->moduleHandler->moduleExists('openy_gated_content')) {
        $user->addRole('virtual_y');
      }

      $result = $account = $user->save();
      if ($result) {
        $account = user_load_by_mail($email);
      }
    }
    else {
      // Activate user if it's not.
      if (!$account->isActive()) {
        $account->activate();
        $account->setPassword($this->passGen->generate());
        $account->save();
      }
    }

    user_login_finalize($account);
  }

}
