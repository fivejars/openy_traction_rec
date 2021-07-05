<?php

namespace Drupal\ypkc_salesforce\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Salesforce integration.
 */
class SalesforceSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ypkc_salesforce_auth_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ypkc_salesforce.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ypkc_salesforce.settings');

    $form['consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Salesforce consumer key'),
      '#description' => $this->t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $config->get('consumer_key'),
    ];

    $form['consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Salesforce Consumer Secret'),
      '#description' => $this->t('Consumer secret of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $config->get('consumer_secret'),
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
      '#description' => $this->t('Enter an URL, ex https://open-y-rec-dev-ed.my.salesforce.com'),
      '#required' => TRUE,
    ];

    $form['community_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Community URL'),
      '#default_value' => $config->get('community_url'),
      '#description' => $this->t('Enter an URL, ex https://mycommunity.ymcapkc.org'),
      '#required' => TRUE,
    ];

    $form['private_key'] = [
      '#type' => 'textarea',
      '#rows' => 30,
      '#title' => $this->t('RSA Private key'),
      '#default_value' => $config->get('private_key'),
      '#description' => $this->t('Private key, generated on 3 step in https://developer.salesforce.com/docs/atlas.en-us.sfdx_dev.meta/sfdx_dev/sfdx_dev_auth_key_and_cert.htm'),
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
    $config->set('consumer_secret', $form_state->getValue('consumer_secret'));
    $config->set('login_user', $form_state->getValue('login_user'));
    $config->set('login_url', $form_state->getValue('login_url'));
    $config->set('private_key', $form_state->getValue('private_key'));
    $config->set('services_base_url', $form_state->getValue('services_base_url'));
    $config->set('community_url', $form_state->getValue('community_url'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
