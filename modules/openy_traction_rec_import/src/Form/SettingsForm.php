<?php

namespace Drupal\openy_traction_rec_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Traction Rec import settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['openy_traction_rec_import.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openy_traction_rec_import_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('openy_traction_rec_import.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable TractionRec Sync'),
      '#default_value' => $config->get('enabled'),
      '#description' => $this->t('Enable Traction Rec synchronization.'),
    ];

    $form['fetch_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable JSON fetch'),
      '#default_value' => $config->get('fetch_status'),
      '#description' => $this->t('Enables fetching of JSON files from Traction Rec'),
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

    $form['locations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Location mapping'),
      '#default_value' => implode(PHP_EOL, $config->get('locations') ?? []),
      '#description' => $this->t('
        A mapping of Salesforce Location IDs to Drupal Location IDs in the form <code>salesforce_id:drupal_node_id:comment</code>. One entry per line.
        <ul>
            <li>Salesforce IDs and names can be found in <code>private://traction_rec_import/json/{datestring}/locations.json</code> after running <code>drush tr:fetch</code>.</li>
            <li>Drupal IDs and names can be taken from the Content list or a temporary view.</li>
            <li>The comment can be anything to help identify the entry. It will be saved but only used for debugging purposes.</li>
        </ul>'),
      '#placeholder' => 'a2QDp000000irzcMAA:1234:Downtown YMCA',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('openy_traction_rec_import.settings');
    $values = $form_state->getValues();
    $config->set('enabled', $values['enabled']);
    $config->set('backup_json', $values['backup_json']);
    $config->set('backup_limit', $values['backup_limit']);
    $config->set('fetch_status', $values['fetch_status']);
    $config->set('locations', array_filter(preg_split('/\R/', $values['locations'])));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
