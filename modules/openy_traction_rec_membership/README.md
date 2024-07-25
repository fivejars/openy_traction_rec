# YMCA Website Services Traction Rec Membership integration

The module allows you to synchronize memberships from the
[Traction Rec CRM](https://www.tractionrec.com) to the YMCA Website.

It uses Migrate API to import data fetched from Traction Rec and provides Drush commands and a configuration UI.

The import process consists of 2 `drush` commands:

1. `openy-tr-membership:fetch-all` this command fetches required data from Traction Rec and saves it to JSON files.
   - Alias: `tr-membership:fetch`

2. `openy-tr-membership:import` the command migrates fetched JSON files to YMCA Website and creates memberships.
   - Alias: `tr-membership:import`

You can run the commands manually for one-time import or add both to cron jobs.

Other available `drush` commands:
* `openy-tr-membership:rollback` - Rolls back all imported nodes.
  * Alias: `tr-membership:rollback`

* `openy-tr-membership:reset-lock` - Resets import lock.
  * Alias: `tr-membership:reset-lock`

* `openy-tr-membership:clean-up` - Removes imported JSON files from the filesystem.
  * Alias: `tr-membership:clean-up`


## Installation
For correct behavior module require `hook_entity_query_tag__TAG_alter()`.
Please use Drupal core version > 10.3.x or patch from issue [#3001496 - Add an alter hook to EntityQuery](https://www.drupal.org/node/3001496)
