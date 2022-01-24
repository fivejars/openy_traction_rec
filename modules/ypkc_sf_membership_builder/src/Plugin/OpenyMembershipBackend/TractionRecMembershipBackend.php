<?php

namespace Drupal\ypkc_sf_membership_builder\Plugin\OpenyMembershipBackend;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\openy_membership\OpenyMembershipBackendPluginBase;
use Drupal\ypkc_sf_membership_builder\MembershipImporter;
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
    ConfigFactoryInterface $config
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $logger);
    $this->logger = $logger;
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

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $term_storage->getQuery();
    $query->condition('vid', MembershipImporter::TAXONOMY_VID);
    $query->condition('field_branch', $branch);

    $result = $query->execute();
    $terms = $term_storage->loadMultiple($result);

    if (!$terms) {
      return [];
    }

    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\TermInterface $term */

      $included_ages = $term->get('field_included_age_groups')->getValue();
      $type_id = $term->get('field_traction_rec_id')->value;

      /** @var \Drupal\node\NodeInterface $branch */
      $branch = $term->get('field_branch')->entity;

      $membership = [
        'id' => $type_id,
        'title' => $term->get('field_displayed_title')->value,
        'field_description' => $term->getDescription(),
        'branch' => [
          'id' => $term->get('field_branch')->target_id,
          'title' => $branch->getTitle(),
        ],
        'included_age_groups' => array_column($included_ages, 'value'),
      ];

      $membership['variations'][] = [
        'id' => $type_id,
        'price' => $term->get('field_price_description')->value,
        'title' => $term->getName(),
      ];

      $data[$membership['id']] = $membership;
    }

    return $this->filterByParams($data, $params);
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
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return ['taxonomy_term_list'];
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

    $group_count = count($age_groups);

    // Only one age group is selected by user.
    // Only Single memberships(one age group is included) should be displayed.
    // Ex. Youth age group is selected by user. `Only Youth` is displayed.
    if ($group_count === 1) {
      foreach ($products as $product_key => $product) {
        if (count($product['included_age_groups']) !== 1 || reset($product['included_age_groups']) !== reset($age_groups)) {
          unset($products[$product_key]);
        }
      }

      return $products;
    }

    if ($group_count === 2) {
      // Family types shouldn't be displayed if youth is not selected.
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
