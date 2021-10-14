<?php

/**
 * @file
 * Documentation module hooks.
 */

/**
 * Alters membership types loaded from TractionRec.
 *
 * @param array $data
 *   The array of TractionRec membership types.
 * @param string $branch_id
 *   TractionRec location ID.
 */
function hook_traction_rec_branch_memberships_data(array &$data, string $branch_id) {
  // Alter data here.
}
