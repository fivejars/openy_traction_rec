<?php

namespace Drupal\ypkc_sf_membership_builder;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\ypkc_salesforce\TractionRecInterface;

/**
 * Provides TractionRec membership types import functionality.
 */
class MembershipImporter implements MembershipImporterInterface {

  /**
   * Taxonomy Vocabulary VID.
   */
  const TAXONOMY_VID = 'membership_type';

  /**
   * The TractionRec service.
   *
   * @var \Drupal\ypkc_salesforce\TractionRecInterface
   */
  protected $tractionRec;

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
   * The array of membership aliases.
   *
   * @var string[]
   */
  protected $membershipAliasesMap = [
    'Family 1' => 'Adult + 1 Youth',
    'Family 2' => 'Adult + Multiple Youth',
    'Family 3' => 'Young Adult + 1 Youth',
    'Family 4' => 'Young Adult + Multiple Youth',
    'Single Adult' => 'Adult',
    'Single Young Adult' => 'Young Adult',
  ];

  /**
   * The array of branch short names.
   *
   * @var array
   */
  protected $branchAliases = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Constructs MembershipImporter.
   *
   * @param \Drupal\ypkc_salesforce\TractionRecInterface $tractionRec
   *   The TractionRec service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   The logger channel.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(TractionRecInterface $tractionRec, EntityTypeManagerInterface $entityTypeManager, LoggerChannelInterface $loggerChannel) {
    $this->tractionRec = $tractionRec;
    $this->entityTypeManager = $entityTypeManager;
    $this->termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $this->logger = $loggerChannel;
  }

  /**
   * {@inheritdoc}
   */
  public function sync(): void {
    $memberships = $this->fetchMembershipTypes();
    if (!$memberships) {
      return;
    }

    // We have existing terms - need to update them and create new (if any).
    if ($terms = $this->loadExistingTerms()) {
      $this->update($memberships, $terms);
      return;
    }

    // We don't have any terms --> run Initial import.
    foreach ($memberships as $membership) {
      $this->createTerm($membership);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calcTermHash(TermInterface $term): string {
    if ($term->bundle() !== static::TAXONOMY_VID) {
      return '';
    }

    $string_to_hash = $term->getName();
    $string_to_hash .= $term->get('field_price_description')
      ->first()
      ->getString();

    return md5($string_to_hash);
  }

  /**
   * Calculate hash for TractionRec membership type.
   *
   * @param array $membership
   *   The array of TractionRec membership type data.
   *
   * @return string
   *   Generated hash.
   */
  protected function calcTractionRecHash(array $membership): string {
    $string_to_hash = $membership['title'];
    $string_to_hash .= $membership['price'];
    return md5($string_to_hash);
  }

  /**
   * Updates existing membership terms.
   *
   * @param array $memberships
   *   The array of TractionRec Membership types.
   * @param array $terms
   *   The array of existing terms.
   */
  protected function update(array $memberships, array $terms) : void {
    try {
      $existing_ids = array_keys($terms);
      $fetched_ids = array_keys($memberships);

      // Should we remove any term?
      $ids_to_remove = array_diff($existing_ids, $fetched_ids);
      if (!empty($ids_to_remove)) {
        $terms_to_remove = [];
        foreach ($ids_to_remove as $traction_rec_id) {
          $terms_to_remove[] = $terms[$traction_rec_id];
          unset($terms[$traction_rec_id]);
        }
        $this->termStorage->delete($terms_to_remove);
      }

      // Should we create new terms?
      $ids_to_create = array_diff($fetched_ids, $existing_ids);
      if (!empty($ids_to_create)) {
        foreach ($ids_to_create as $traction_rec_id) {
          $this->createTerm($memberships[$traction_rec_id]);
          unset($memberships[$traction_rec_id]);
        }
      }

      // Should we update any term?
      foreach ($terms as $term) {
        $traction_rec_id = $term->get('field_traction_rec_id')->value;
        $membership = $memberships[$traction_rec_id];
        if ($this->calcTermHash($term) != $this->calcTractionRecHash($membership)) {
          $this->updateTerm($term, $membership);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Creates taxonomy term based on TractionRec data.
   *
   * @param array $membership
   *   The array of TractionRec membership type data.
   */
  protected function createTerm(array $membership) {
    try {
      $term = $this->termStorage->create([
        'name' => $membership['title'],
        'vid' => static::TAXONOMY_VID,
        'description' => $membership['description'],
        'field_price_description' => $membership['price'],
        'field_branch' => $membership['branch']['nid'],
        'field_included_age_groups' => $membership['included_age_groups'],
        'field_traction_rec_id' => $membership['id'],
        'field_traction_rec_location_id' => $membership['branch']['id'],
        'field_displayed_title' => $membership['displayed_title'],
      ]);
      $term->save();
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Updates membership type terms with data from TractionRec.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The membership type taxonomy term.
   * @param array $membership
   *   TractionRec Membership Type.
   */
  protected function updateTerm(TermInterface $term, array $membership) {
    try {
      // We shouldn't update all fields - client manages some of them manually.
      $term->setName($membership['title']);
      $term->set('field_price_description', $membership['price']);
      $this->termStorage->save($term);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Loads existing Membership Type taxonomy terms.
   *
   * @return array
   *   The array of loaded terms.
   */
  protected function loadExistingTerms(): array {
    $terms = $this->termStorage->loadByProperties(['vid' => static::TAXONOMY_VID]);
    if (!$terms) {
      return [];
    }

    // TractionRec ID as a key is more convenient for us,
    // since we can easily map entities between TR and Open Y.
    foreach ($terms as $key => $term) {
      $tr_id = $term->get('field_traction_rec_id')->value;
      unset($terms[$key]);
      $terms[$tr_id] = $term;
    }

    return $terms;
  }

  /**
   * Fetches membership types from TractionRec.
   *
   * @return array
   *   The array of TractionRec membership types.
   */
  protected function fetchMembershipTypes(): array {
    $membership_types = [];

    try {
      $tr_membership_types = $this->tractionRec->loadMemberships();
      if (empty($tr_membership_types['records'])) {
        return $membership_types;
      }

      $this->excludeIrrelevantTypes($tr_membership_types);
      $branches = $this->buildBranchMapping();

      foreach ($tr_membership_types['records'] as $record) {
        $location_id = $record['Location']['Id'];
        if (!isset($branches[$location_id])) {
          continue;
        }

        $membership = [
          'id' => $record['Id'],
          'title' => $record['Name'],
          'displayed_title' => $this->generateDefaultHumanName($record['Name']),
          'description' => $record['Description'],
          'branch' => [
            'id' => $record['Location']['Id'],
            'title' => $record['Location']['Name'],
            'nid' => $branches[$location_id],
          ],
          'included_age_groups' => [],
        ];

        // TractionRec has 3 age Groups.
        for ($i = 1; $i <= 3; $i++) {
          if (is_null($record['Group_' . $i . '_Min_Age']) || is_null($record['Group_' . $i . '_Max_Age'])) {
            continue;
          }

          $min_max_ages = [
            (int) $record['Group_' . $i . '_Min_Age'],
            (int) $record['Group_' . $i . '_Max_Age'],
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

        $membership['price'] = $record['Product']['Price_Description'];
        $membership_types[$record['Id']] = $membership;
      }

      return $membership_types;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return $membership_types;
    }
  }

  /**
   * Loads TractionRec Branch mappings.
   *
   * @return array
   *   The array of loaded mappings.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildBranchMapping(): array {
    $storage = $this->entityTypeManager->getStorage('mapping');
    $mappings = $storage->loadByProperties(['type' => 'tractionrec_location']);
    if (!$mappings) {
      return [];
    }

    $branches = [];
    foreach ($mappings as $mapping) {
      $traction_rec_id = $mapping->get('field_traction_rec_id')->value;
      $branch_nid = $mapping->get('field_open_y_location')->target_id;
      $branches[$traction_rec_id] = $branch_nid;
    }

    return $branches;
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
   * Generates default human-readable name for the membership type.
   *
   * @param string $title
   *   TractionRec Membership type title.
   *
   * @return string
   *   Automatically generated human-readable name.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function generateDefaultHumanName(string $title): string {
    if (empty($this->branchAliases)) {
      $storage = $this->entityTypeManager->getStorage('mapping');
      $mappings = $storage->loadByProperties(['type' => 'tractionrec_location']);

      foreach ($mappings as $mapping) {
        $this->branchAliases[] = $mapping->get('field_traction_rec_alias')->value;
      }
    }

    foreach ($this->branchAliases as $short_branch_alias) {
      if (strpos($title, $short_branch_alias) !== FALSE) {
        // TractionRec membership types have branch prefixes.
        // Ex. TTFY - YOUTH ONLY, TCY - FAMILY 1.
        // The prefixes have to be removed.
        $title = str_replace($short_branch_alias . ' - ', '', $title);
      }
    }

    // Some of TractionRec membership names are not clear for users.
    // They have to be replaced with more clear custom names by mapping.
    foreach ($this->membershipAliasesMap as $traction_rec_name => $new_membership_name) {
      if (strpos($title, $traction_rec_name) !== FALSE) {
        $title = str_replace($traction_rec_name . ' - ', '', $new_membership_name);
      }
    }

    return $title;
  }

}
