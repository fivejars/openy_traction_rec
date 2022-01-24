<?php

namespace Drupal\ypkc_sf_membership_builder;

use Drupal\taxonomy\TermInterface;

/**
 * Provides Membership Importer Interface.
 */
interface MembershipImporterInterface {

  /**
   * Entry point for TractionRec sync.
   */
  public function sync();

  /**
   * Calculates the hash for membership type taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   Membership type taxonomy term.
   *
   * @return string
   *   Calculated hash.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function calcTermHash(TermInterface $term): string;

}
