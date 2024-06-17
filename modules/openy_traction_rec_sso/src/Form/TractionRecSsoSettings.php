<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_sso\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Build settings form for TractionRec integration.
 */
class TractionRecSsoSettings extends ConfigFormBase {

  /**
   * X
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openy_traction_rec_sso_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['openy_traction_rec_sso.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('openy_traction_rec_sso.settings');

    $form['app_url'] = [
      '#title' => $this->t('Application base URL'),
      '#description' => $this->t('Community URL for which will be send requests (f.e. https://my.ymcade.org)'),
      '#type' => 'textfield',
      '#default_value' => $config->get('app_url'),
      '#required' => TRUE,
    ];

    $form['consumer_key'] = [
      '#title' => $this->t('Consumer Key'),
      '#description' => $this->t('Consumer Key, that can be found in Manage Connected App'),
      '#type' => 'textfield',
      '#default_value' => $config->get('consumer_key'),
      '#required' => TRUE,
    ];

    $form['consumer_secret'] = [
      '#title' => $this->t('Consumer Secret'),
      '#description' => $this->t('Consumer Secret, that can be found in Manage Connected App'),
      '#type' => 'textfield',
      '#default_value' => $config->get('consumer_secret'),
      '#required' => TRUE,
    ];

    $form['sso_user_authenticated'] = [
      '#title' => $this->t('Log in as an authenticated drupal user for SSO users'),
      '#description' => $this->t('Depend on this option users will be logged-in into drupal as "Authenticated user", or will left Anonymous user (to improve site performance).'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('sso_user_authenticated') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('openy_traction_rec_sso.settings');
    $config->set('app_url', $form_state->getValue('app_url'));
    $config->set('consumer_key', $form_state->getValue('consumer_key'));
    $config->set('consumer_secret', $form_state->getValue('consumer_secret'));
    $config->set('sso_user_authenticated', $form_state->getValue('sso_user_authenticated'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
