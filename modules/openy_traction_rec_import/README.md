# Open Y Traction Rec PEF integration

The module allows you to synchronize data about classes and programs from the [Traction Rec CRM](https://www.tractionrec.com) to the Open Y PEF.
It uses Migrate API and provides just Drush commands without any UI.

Import process consists from the 2 parts:
1. `openy-tr:fetch-all` this command fetches required data from Traction Rec and saves it to JSON files. 
   1. Alias: `tr:fetch`

2. `openy-tr:import` the command migrates fetched JSON files to Open Y and creates sessions, classes, activities, categories and programs. 
   1. Alias: `tr:import`


You can run the commands manually for one-time import or add both to cron jobs.

Other available drush commands:
* `openy-tr:rollback` - Rollbacks all imported nodes. 
  * Alias: `tr:rollback`

* `openy-tr:reset-lock` - Resets import lock. 
  * Alias: `tr:reset-lock`

* `openy-tr:clean-up` - Removes imported JSON files from the filesystem. 
  * Alias: `tr:clean-up`




For correct work of the integration your Salesforce integration user should have access to the following data:

* Program
* Program Category
* Course
* Course Option
* Course Session
* Session
* Product and Discount
* Price Level
