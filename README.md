# Open Y Traction Rec integration

Module provides Open Y integration with the [Traction Rec CRM](https://www.tractionrec.com)

JWT OAuth flow is used for the integration: [OAuth 2.0 JWT Bearer Flow for Server-to-Server Integration](https://help.salesforce.com/articleView?id=remoteaccess_oauth_jwt_flow.htm&type=5)

## Installation

```shell
composer require fivejars/openy_traction_rec
```

## Usage

The main module itself provides only API that helps fetch data from the TractionRec. More specific functionality is provided in sub-modules:

* `Open Y: Traction Rec PEF import` provides PEF migrations.
* `Open Y Traction Rec: Activity Finder` extends Open Y Activity Finder with the new fields and logic.

## Configuration

* Go to `/admin/config/system/keys` and create a new [https://www.drupal.org/project/key](https://www.drupal.org/project/key) to store private key used for JWT flow.
* Go to `/admin/openy/integrations/traction_rec/settings` connection settings form and use the keys & secrets provided by Traction Rec.

