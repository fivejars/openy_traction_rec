<?php

namespace Drupal\ypkc_salesforce\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SalesforceSettings.
 *
 * Build settings form for Salesfors integration.
 */
class SalesforceSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ypkc_salesforce_auth_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ypkc_salesforce.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ypkc_salesforce.settings');

    $form['consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Salesforce consumer key'),
      '#description' => $this->t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $config->get('consumer_key'),
    ];

    $form['login_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Salesforce login user'),
      '#description' => $this->t('User account to issue token to'),
      '#required' => TRUE,
      '#default_value' => $config->get('login_user'),
    ];

    $form['login_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login URL'),
      '#default_value' => $config->get('login_url'),
      '#description' => $this->t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];

    $form['services_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Services base URL'),
      '#default_value' => $config->get('services_base_url'),
      '#description' => $this->t('Enter an URL, ex https://open-y-rec-dev-ed.my.salesforce.com/services/data/v49.0/'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ypkc_salesforce.settings');
    $config->set('consumer_key', $form_state->getValue('consumer_key'));
    $config->set('login_user', $form_state->getValue('login_user'));
    $config->set('login_url', $form_state->getValue('login_url'));
    $config->set('services_base_url', $form_state->getValue('services_base_url'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
