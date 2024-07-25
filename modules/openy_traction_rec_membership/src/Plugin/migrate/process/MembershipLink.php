<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_membership\Plugin\migrate\process;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Traction Rec membership link plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "tr_membership_link"
 * )
 */
class MembershipLink extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

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
    string $plugin_id,
    mixed $plugin_definition,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $membership = reset($value);
    $location = end($value);
    $config = $this->configFactory->get('openy_traction_rec.settings');
    $community_url = $config->get('community_url');
    $params = [
      't' => $membership,
      'l' => $location,
    ];

    $query = http_build_query($params);
    return $community_url . '/s/memberships?' . $query;
  }

}
