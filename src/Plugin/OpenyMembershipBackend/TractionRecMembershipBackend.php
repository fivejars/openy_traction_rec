<?php

namespace Drupal\ypkc_salesforce\Plugin\OpenyMembershipBackend;

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
   * The map of Membership type ages. Provides info about max and min ages.
   *
   * @var \int[][]
   */
  protected $agesMap = [
    'youth' => [0, 17],
    'young adult' => [18, 29],
    'adult' => [30, 110],
  ];

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
  public function getMembershipTypesByBranch(int $branch, array $params = []): array {
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

    $this->excludeIrrelevantTypes($membership_types);

    foreach ($membership_types['records'] as $type) {
      $membership = [
        'id' => $type['Id'],
        'title' => $type['Name'],
        'field_description' => $type['Name'],
        'branch' => [
          'id' => $branch,
          'title' => $type['Location']['Name'],
        ],
      ];

      // TractionRec has 3 age Groups.
      for ($i = 1; $i <= 3; $i++) {
        if (empty($type['Group_' . $i . '_Max_Allowed']) || is_null($type['Group_' . $i . '_Min_Age'])) {
          continue;
        }

        $membership['ages'][] = [
          'name' => $type['Group_' . $i . '_Name'],
          'min' => (int) $type['Group_' . $i . '_Min_Age'],
          'max' => (int) $type['Group_' . $i . '_Max_Age'],
          'allowed' => (int) $type['Group_' . $i . '_Max_Allowed'],
        ];
      }

      // Single membership types, don't have age settings in the TractionRec.
      // We should fill them with default values.
      if (empty($membership['ages'])) {
        $age['allowed'] = 10;

        if ($this->isPersonalMembership($membership['title'])) {
          $map_key = $this->getAgeMapKeyByMembershipName($membership['title']);

          if (!$map_key || !isset($this->agesMap[$map_key])) {
            continue;
          }

          [$min, $max] = $this->agesMap[$map_key];
          $age['min'] = $min;
          $age['max'] = $max;
        }
        $membership['ages'][] = $age;
      }

      foreach ($membership['ages'] as $age_group) {
        if ($age_group['allowed']) {
          $membership['required_age_groups']++;
        }
      }

      // We need custom formatting for the price description.
      $price = str_replace('Mo', 'Month',
        preg_replace(
        '/^(\d+\s\w+\s\w+)\s(\d+\w+)/',
        '${1} | $${2}',
        $type['Product']['Price_Description']));

      $membership['variations'][] = [
        'id' => $type['Product']['Id'],
        'price' => $price,
        'title' => $type['Product']['Name'],
      ];

      $data[$type['Id']] = $membership;
    }

    return $this->filterByParams($data, $params);
  }

  /**
   * Gets the key of age map by membership type name.
   *
   * @param string $membership_type
   *   The name of membership type.
   *
   * @return string
   *   Needed key for the age mapping array.
   */
  protected function getAgeMapKeyByMembershipName(string $membership_type): string {
    foreach (array_keys($this->agesMap) as $age) {
      if (strpos(strtolower($membership_type), $age) !== FALSE) {
        if ($age == 'adult' && strpos(strtolower($membership_type), 'young') !== FALSE) {
          continue;
        }

        return $age;
      }
    }

    return '';
  }

  /**
   * Returns TRUE if provided membership type is single membership.
   *
   * @param string $membership_type
   *   The membership type name.
   *
   * @return bool
   *   TRUE if membership type for only 1 person.
   */
  protected function isPersonalMembership(string $membership_type): bool {
    $membership_type = strtolower($membership_type);
    $keywords = ['single', 'only'];

    foreach ($keywords as $keyword) {
      if (strpos($membership_type, $keyword) !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Excludes membership types that shouldn't be displayed in results.
   *
   * @param array $membership_types
   *   The array of TractionRec membership types.
   */
  protected function excludeIrrelevantTypes(array &$membership_types) {
    foreach ($membership_types as $key => $membership_type) {
      $membership_type = strtolower($membership_type);
      if (strpos($membership_type, 'add') !== FALSE) {
        unset($membership_types[$key]);
      }
    }
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

  /**
   * Filters products by additional parameters.
   *
   * @param array $products
   *   The array of membership type products.
   * @param array $params
   *   The array of additional params.
   *
   * @return array
   *   The filtered array.
   */
  protected function filterByParams(array $products, array $params): array {
    if (empty($params)) {
      return $products;
    }

    if (!empty($params['ages'])) {
      $ages = $params['ages'];

      $age_filters = [];
      $non_empty_filters = 0;
      foreach (explode(',', $ages) as $age_item) {
        [$range, $count] = explode('=', $age_item);
        [$min, $max] = explode('-', $range);

        $age_filters[] = [
          'min' => (int) $min,
          'max' => (int) $max,
          'count' => (int) $count,
        ];

        if ($count) {
          $non_empty_filters++;
        }
      }

      foreach ($products as $product_key => $product) {
        if ($non_empty_filters < $product['required_age_groups']) {
          unset($products[$product_key]);
          continue;
        }

        $valid_groups = 0;
        foreach ($age_filters as $filter_key => $age_filter) {
          if (!$age_filter['count']) {
            unset($age_filters[$filter_key]);
            continue;
          }

          foreach ($product['ages'] as $product_ages) {
            $is_valid_age = $product_ages['min'] >= $age_filter['min'] && $product_ages['max'] <= $age_filter['max'];
            $is_valid_count = $product_ages['allowed'] >= $age_filter['count'];
            if ($is_valid_age && $is_valid_count) {
              $valid_groups++;
            }
          }
        }

        if ($non_empty_filters !== $valid_groups) {
          unset($products[$product_key]);
        }
      }
    }

    return $products;
  }

}
