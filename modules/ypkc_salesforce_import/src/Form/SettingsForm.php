<?php

namespace Drupal\ypkc_salesforce_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Salesforce import settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ypkc_salesforce_import.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ypkc_salesforce_import_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ypkc_salesforce_import.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('enabled'),
      '#description' => $this->t('Enable Salesforce import'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ypkc_salesforce_import.settings');
    $values = $form_state->getValues();
    $config->set('enabled', $values['enabled'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
