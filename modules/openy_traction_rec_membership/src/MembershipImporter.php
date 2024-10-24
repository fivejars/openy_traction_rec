<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec_membership;

use Drupal\openy_traction_rec_import\Importer;

/**
 * Wrapper for Membership Traction Rec import operations.
 */
class MembershipImporter extends Importer {

  /**
   * {@inheritdoc}
   */
  const LOCK_NAME = 'tr_membership_import';

  /**
   * {@inheritdoc}
   */
  const SOURCE_DIRECTORY = 'private://traction_rec_membership_import/json/';

  /**
   * {@inheritdoc}
   */
  const TARGET_DIRECTORY = 'private://traction_rec_membership_import/';

  /**
   * {@inheritdoc}
   */
  const BACKUP_DIRECTORY = 'private://traction_rec_membership_import/backup/';

  /**
   * {@inheritdoc}
   */
  public function getMigrations(): array {
    return $this->migrationPluginManager->createInstances(['tr_memberships_import']);
  }

}
