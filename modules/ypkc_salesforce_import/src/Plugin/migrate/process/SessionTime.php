<?php

namespace Drupal\ypkc_salesforce_import\Plugin\migrate\process;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modified version of OpenYPefSchedule for iterator plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "sf_session_time"
 * )
 */
class SessionTime extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * SessionTime constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function transform(
    $value,
    MigrateExecutableInterface $migrate_executable,
    Row $row,
    $destination_property
  ) {
    $value = $row->getSource();

    if (empty($value['start_date'])) {
      throw new MigrateSkipRowException('Datetime cannot be empty for session');
    }

    // Default time.
    if (empty($value['start_time'])) {
      $value['start_time'] = '07:00 AM';
    }

    try {
      $start_date = $this->convertDate(
        $value['start_date'] . ' ' . $value['start_time']
      );
      $end_date = $this->convertDate($value['end_date'] . ' ' . '11:59 pm');

      if (empty($value['days'])) {
        $value['days'] = $start_date->format('l');
      }

      $days = explode(';', $value['days']);
      $days = array_map('strtolower', $days);

      $paragraph = Paragraph::create(
        [
          'type' => 'session_time',
          'field_session_time_actual' => 1,
          'field_session_time_days' => $days,
          'field_session_time_date' => [
            'value' => $start_date->format('Y-m-d\TH:i:s'),
            'end_value' => $end_date->format('Y-m-d\TH:i:s'),
          ],
        ]
      );
      $paragraph->isNew();
      $paragraph->save();

      return [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    catch (\Exception $e) {
      throw new MigrateSkipRowException($e->getMessage());
    }
  }

  /**
   * Converts date to the DB format.
   *
   * @param string $datetime
   *   The date.
   *
   * @return mixed
   *   Formatted date string.
   */
  protected function convertDate(string $datetime) {
    $site_timezone = $this->configFactory->get('system.date')->get(
      'timezone.default'
    );

    return DateTimePlus::createFromFormat(
      'Y-m-d h:i a',
      $datetime,
      $site_timezone,
      ['validate_format' => FALSE]
    )
      ->setTimezone(new \DateTimeZone('UTC'));
  }

}
