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

    $form['backup_json'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Backup'),
      '#default_value' => $config->get('backup_json'),
      '#description' => $this->t('Save imported JSON files'),
    ];

    $form['backup_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Backup limit'),
      '#default_value' => $config->get('backup_limit'),
      '#options' => [
        5 => 5,
        10 => 10,
        15 => 15,
        20 => 20,
        25 => 25,
        35 => 25,
        40 => 40,
        45 => 45,
        50 => 50,
      ],
      '#description' => $this->t('The max number of folders with imported JSON files to store'),
      '#states' => [
        'visible' => [
          ':input[name="backup_json"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ypkc_salesforce_import.settings');
    $values = $form_state->getValues();
    $config->set('enabled', $values['enabled']);
    $config->set('backup_json', $values['backup_json']);
    $config->set('backup_limit', $values['backup_limit']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
