<?php

namespace Drupal\ypkc_salesforce\Plugin\OpenyMembershipBackend;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\openy_membership\OpenyMembershipBackendPluginBase;
use Drupal\ypkc_salesforce\TractionRecInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The TractionRec Membership Backend plugin.
 *
 * @OpenyMembershipBackend(
 *   id = "traction_rec",
 *   label = @Translation("TractionRec Backend"),
 * )
 */
class TractionRecMembershipBackend extends OpenyMembershipBackendPluginBase {

  use StringTranslationTrait;

  /**
   * The TractionRec Wrapper.
   *
   * @var \Drupal\ypkc_salesforce\TractionRecInterface
   */
  protected $tractionRec;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    TractionRecInterface $traction_rec,
    ConfigFactoryInterface $config
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $logger);
    $this->logger = $logger;
    $this->tractionRec = $traction_rec;
    $this->configFactory = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('openy_membership'),
      $container->get('ypkc_salesforce.traction_rec'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMembershipTypesByBranch(int $branch): array {
    $data = [];

    if (!$branch) {
      return [];
    }

    $result = $this->entityTypeManager->getStorage('mapping')
      ->loadByProperties([
        'type' => 'tractionrec_location',
        'field_open_y_location' => $branch,
      ]);

    $location_mapping = reset($result);

    $traction_id = $location_mapping->get('field_traction_rec_id')->value;

    $membership_types = $this->tractionRec->loadMemberships($traction_id);
    if (!$membership_types || empty($membership_types['records'])) {
      return [];
    }

    foreach ($membership_types['records'] as $type) {
      $membership = [
        'id' => $type['Id'],
        'title' => $type['Name'],
        'field_description' => $type['Name'],
        "branch" => [
          "id" => $branch,
          "title" => $type['Location']['Name'],
        ],
      ];
      $membership['variations'][] = [
        'id' => $type['Product']['Id'],
        'price' => $type['Product']['Price_Description'],
        'title' => $type['Product']['Name'],
      ];
      $data[$type['Id']] = $membership;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getBranches(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $branches = $storage->loadByProperties([
      'type' => 'branch',
      'status' => NodeInterface::PUBLISHED,
    ]);

    $result = [];
    foreach ($branches as $branch) {
      if ($branch->get('field_location_address')->isEmpty()) {
        continue;
      }

      $address = $branch->get('field_location_address')->first()->getValue();
      $address_string = $address['address_line1'];
      $address_string .= ' ' . $address['locality'];
      $address_string .= ', ' . $address['administrative_area'];
      $address_string .= ' ' . $address['postal_code'];

      $result[$branch->id()] = [
        'name' => $branch->label(),
        'address' => $address_string,
        'value' => $branch->id(),
      ];
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrl(string $membership): string {
    $config = $this->configFactory->get('ypkc_salesforce.settings');
    $community_url = $config->get('community_url');

    return $community_url . '/s/memberships?t=' . $membership;
  }

}
