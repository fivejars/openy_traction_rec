<?php

namespace Drupal\ypkc_sf_membership_builder\Commands;

use Drupal\ypkc_sf_membership_builder\MembershipImporterInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MembershipBuilderCommands extends DrushCommands {

  /**
   * The membership importer service.
   *
   * @var \Drupal\ypkc_sf_membership_builder\MembershipImporterInterface
   */
  protected $membershipImporter;

  /**
   * Constructs new MembershipBuilderCommands instance.
   *
   * @param \Drupal\ypkc_sf_membership_builder\MembershipImporterInterface $membershipImporter
   *   The membership importer service.
   */
  public function __construct(MembershipImporterInterface $membershipImporter) {
    parent::__construct();
    $this->membershipImporter = $membershipImporter;
  }

  /**
   * Runs import of TractionRec Membership Types to Open Y.
   *
   * Import creates or remove membership types if they added/removed in TR.
   * Also, import updates membership prices.
   *
   * @usage ypkc_sf:membership_sync
   *   Usage description
   *
   * @command ypkc_sf:membership_sync
   * @aliases ysf-ms
   */
  public function membershipSync() {
    $this->membershipImporter->sync();
  }

}
