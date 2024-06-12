<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec\Plugin\KeyType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Plugin\KeyTypeBase;

/**
 * Defines a TractionRec JWT private key type for authentication.
 *
 * @KeyType(
 *   id = "openy_tr_private_key",
 *   label = @Translation("TractionRec: JWT Private Key"),
 *   description = @Translation("A private key to be used for your JWT integration."),
 *   group = "authentication",
 *   key_value = {
 *     "plugin" = "textarea_field"
 *   }
 * )
 */
class AuthenticationKeyType extends KeyTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function generateKeyValue(array $configuration) {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function validateKeyValue(array $form, FormStateInterface $form_state, $key_value) {
    // Validation of the key value is optional.
  }

}
