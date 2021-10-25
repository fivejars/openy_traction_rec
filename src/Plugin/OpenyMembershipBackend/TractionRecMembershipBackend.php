<?php

namespace Drupal\ypkc_salesforce\Plugin\OpenyMembershipBackend;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The map of Membership type ages. Provides info about max and min ages.
   *
   * @var \int[][]
   */
  protected $agesMap = [
    'youth' => [0, 17],
    'young_adult' => [18, 29],
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
    ConfigFactoryInterface $config,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $logger);
    $this->logger = $logger;
    $this->tractionRec = $traction_rec;
    $this->configFactory = $config;
    $this->moduleHandler = $module_handler;
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
      $container->get('config.factory'),
      $container->get('module_handler')
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
        'included_age_groups' => [],
      ];

      // TractionRec has 3 age Groups.
      for ($i = 1; $i <= 3; $i++) {
        if (is_null($type['Group_' . $i . '_Min_Age']) || is_null($type['Group_' . $i . '_Max_Age'])) {
          continue;
        }

        $min_max_ages = [
          (int) $type['Group_' . $i . '_Min_Age'],
          (int) $type['Group_' . $i . '_Max_Age'],
        ];

        $membership['ages'][] = $min_max_ages;
      }

      // Single membership types, don't have age settings in the TractionRec.
      // We should fill them with default values.
      if (empty($membership['ages'])) {
        if ($this->isPersonalMembership($membership['title'])) {
          $map_key = $this->getAgeMapKeyByMembershipName($membership['title']);

          if (!$map_key || !isset($this->agesMap[$map_key])) {
            continue;
          }
          $membership['ages'][] = $this->agesMap[$map_key];
        }
      }

      foreach ($membership['ages'] as $membership_min_max_ages) {
        foreach ($this->agesMap as $age_group => $age_group_ages) {
          if ($membership_min_max_ages === $age_group_ages) {
            $membership['included_age_groups'][] = $age_group;
          }
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

    $data = $this->filterByParams($data, $params);

    $this->moduleHandler->alter('traction_rec_branch_memberships_data', $data, $branch);
    return $data;
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
      $membership_type = strtolower($membership_type);
      $membership_type = str_replace(' ', '_', $membership_type);
      if (strpos($membership_type, $age) !== FALSE) {
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
    foreach ($membership_types['records'] as $key => $membership_type) {
      $membership_type = strtolower($membership_type['Name']);
      if (strpos($membership_type, 'add') !== FALSE) {
        unset($membership_types['records'][$key]);
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
    if (empty($params) || empty($params['age_groups'])) {
      return $products;
    }

    $age_groups = explode(',', $params['age_groups']);

    // Only one age group is selected by user.
    // Only Single memberships(one age group is included) should be displayed.
    // Ex. Youth age group is selected by user. `Only Youth` is displayed.
    if (count($age_groups) === 1) {
      foreach ($products as $product_key => $product) {
        if (count($product['included_age_groups']) !== 1 || reset($product['included_age_groups']) !== reset($age_groups)) {
          unset($products[$product_key]);
        }
      }

      return $products;
    }

    if (count($age_groups) === 2) {
      // Family types shouldn't be displayed in results if youth is not selected.
      $adult_groups = ['adult', 'young_adult'];
      if (array_intersect($age_groups, $adult_groups) && !in_array('youth', $age_groups)) {
        foreach ($products as $product_key => $product) {
          if (count($product['included_age_groups']) > 1) {
            unset($products[$product_key]);
            continue;
          }

          if (!in_array(reset($product['included_age_groups']), $age_groups)) {
            unset($products[$product_key]);
          }
        }
        return $products;
      }

      if (in_array('youth', $age_groups)) {
        foreach ($products as $product_key => $product) {
          if (array_diff($age_groups, $product['included_age_groups'])) {
            unset($products[$product_key]);
          }
        }
      }
    }

    // Selecting youth, young adult, and adult.
    // Only family results should be displayed.
    foreach ($products as $product_key => $product) {
      if (count($product['included_age_groups']) == 1) {
        unset($products[$product_key]);
      }
    }

    return $products;
  }

}
