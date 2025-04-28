# YMCA Website Services Traction Rec PEF integration

The module allows you to synchronize classes and programs from the
[Traction Rec CRM](https://www.tractionrec.com) to the YMCA Website Services Program Event Framework (PEF).

It uses Migrate API to import data fetched from Traction Rec and provides Drush commands and a configuration UI.

The import process consists of 2 `drush` commands:

1. `openy-tr:fetch-all` this command fetches required data from Traction Rec and saves it to JSON files.
   - Alias: `tr:fetch`

2. `openy-tr:import` the command migrates fetched JSON files to YMCA Website Services and creates sessions, classes, activities, categories and programs.
   - Alias: `tr:import`

You can run the commands manually for one-time import or add both to cron jobs.

Other available `drush` commands:
* `openy-tr:rollback` - Rolls back all imported nodes.
  * Alias: `tr:rollback`

* `openy-tr:reset-lock` - Resets import lock.
  * Alias: `tr:reset-lock`

* `openy-tr:clean-up` - Removes imported JSON files from the filesystem.
  * Alias: `tr:clean-up`

* `openy-tr:quick-availability-sync` - Sync total availability data for sessions.
  * Alias: `tr:qas`
