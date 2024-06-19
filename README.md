# YMCA Website Services Traction Rec integration

This module provides YMCA Website Services integration with the [Traction Rec CRM](https://www.tractionrec.com).

- JWT OAuth flow is used for the integration: [OAuth 2.0 JWT Bearer Flow for Server-to-Server Integration](https://help.salesforce.com/articleView?id=remoteaccess_oauth_jwt_flow.htm&type=5)
- The [Drupal Key module](https://www.drupal.org/project/key) assists with key management for authentication.

## Installation

Require this module:
```shell
composer require ycloudyusa/openy_traction_rec
```

Then enable the necessary modules and submodules:
```shell
drush en openy_traction_rec openy_traction_rec_import openy_tr_activity_finder
```

## Usage

The main module itself provides only API that helps fetch data from TractionRec. More specific functionality is provided in submodules:

* `YMCA Website Services Traction Rec: PEF import` provides PEF migrations.
* `YMCA Website Services Traction Rec: Activity Finder` extends YMCA Website Services Activity Finder with the new fields and logic.

## Configuration

### Create a Connected App in Salesforce

1. Create a new private key and X509 certificate, customizing the `subj` options in the command to suit your organization. (See [the manual for openssl-req](https://www.openssl.org/docs/manmaster/man1/openssl-req.html) to understand the options here.)
    ```shell
    openssl req -x509 -noenc -sha256 -days 365 \
     -keyout traction_rec.key \
     -out traction_rec.crt \
     -subj "/C=US/ST=Illinois/L=Chicago/O=My YMCA/OU=Org/emailAddress=youremail@example.com"
    ```
   - The email address in the certificate does not need to match the email on the Connected App.
   - The certificate **must be renewed yearly** (or after the set number of `--days`). We recommend you set a reminder in order to prevent unwanted failures.
2. In **Salesforce** > **Setup** > **App Manager**, create a **New Connected App**.
   - Set a **Name** and **Email**.
     - The **Contact Email** is not used for authentication.
   - Check **Enable OAuth Settings**
     - Set the callback url as the base URL of your site
     - Check **Use digital signatures** and upload the X509 certificate (`.crt`) created above.
     - Ensure the app has the following **Selected OAuth Scopes**
       - Full access (full)
       - Manage user data via APIs (api)
       - Manage user data via Web browsers (web)
       - Perform requests at any time (refresh_token, offline_access)
     - Check these options:
       - **Require Proof Key for Code Exchange (PKCE) Extension for Supported Authorization Flows**
       - **Issue JSON Web Token (JWT)-based access tokens for named users**
     - Uncheck all other options in the **OAuth** section.
   - **Save** the Connected App
3. Once the app is saved, you will need to get the **Consumer Details**:
   - In the "My Connected App" screen that appears once you save (or via **Setup** > **App Manager**), click **Manage Consumer Details**.
   - Save the **Consumer Key** and **Consumer Secret** for the next step.
4. Create a **Profile** to assign permissions to your app:
   - You **must** do this **before** creating a user. Alternatively, existing users can be assigned a **Permision Set**. Instructions TBD.
   - **Setup** > **Users** > **Profiles** > **New**
   - When asked what **Existing Profile** to clone from, select **Standard Platform User**.
   - In the _very large_ configuration screen, click **Edit**, then:
     - Under **Connected App Access**, add access to the Connected App you created above.
     - Search for and enable the [System permissions listed below](#salesforce-permissions).
     - Under **Custom Object Permissions**, add **Read** access to the [Objects listed below](#salesforce-permissions).
   - Save those changes.
4. Create a new **User** with the new Profile:
   - **Setup** > **Users** > **New User**
     - **User License** - Salesforce Platform
     - **Email** - A working email that you will use to receive login verifications.
     - **Username** - This is _not_ your email and _must_ be unique across _all Salesforce Organizations_. **This is the name that will be used in the Drupal connection below.** If you enter a preexisting username, you will receive this error:
        > Error: Duplicate Username.
        > The username already exists in this or another Salesforce organization. Usernames must be unique across all Salesforce organizations. To resolve, use a different username (it doesn't need to match the user's email address).
     - Assign the User to the **Profile** you created above, or a **Permission Set** that has the [necessary permissions](#salesforce-permissions).
5. Confirm your **Connected App**, **Profile**, and **User** are connected:
   - Go to **Setup** > **Connected Apps** > **Manage Connected Apps** and choose your new app. Assign the **Profile** or **Permission Set** that contains your new user if it does not already show under the relevant section.
   - In the Connect App Detail, click **Edit Policies**:
     - Under **OAuth Policies** > **Permitted Users** choose **Admin approved users are pre-authorized**.
     - Check **Issue JSON Web Token (JWT)-based access tokens**.
     - **Save** the Connected App details.

Review all of these steps carefully. Missing any of them can result in an inability to query the API.

#### Salesforce permissions

The Salesforce integration role/permission set should have read access to all fields in the following objects:

* Course
* Course Option
* Course Session
* Course Session Option
* Location
* Product and Discount
* Program
* Program Category
* Program Category Tag

It should also have the following Systems Permissions:

* Apex REST Services
* View Restriction and Scoping Rules
* Update Consent Preferences Using REST API

### Configure the connection in Drupal

1. Go to **Admin** > **Configuration** > **System** > **Keys** (`/admin/config/system/keys`) and create a new key to store the private key created above.
   - **Add key**
   - Add a **Key name** and **Description**
   - Choose **Key Type**: "TractionRec JWT Private Key"
   - Choose the **Key provider** depending on your configuration. See [Managing Keys](https://www.drupal.org/project/key#:~:text=the%20encrypt%20module-,Managing%20keys,-Key%20provides%20an) for details.
   - Configure the chosen provider then **Save** the key.
2. Go to **Admin** > **YMCA Website Services** > **Integrations** > **Traction Rec** > **Traction Rec auth settings** (`/admin/openy/integrations/traction-rec/auth`) to configure the keys & secrets provided by Traction Rec.
   - Add the **Consumer key** and **Consumer Secret** from **Manage Consumer Details** in Salesforce.
   - Add the **User** connected to the Connected App.
     - This is the **Username** of the **User**, not the **Contact email**.
   - Enter a **Login URL**.
     - This will most likely be `https://login.salesforce.com`
   - Set the **Services base URL** and **REST API Base URL** as per their descriptions.
     - **Ensure the REST API Base URL responds to `curl -I` with a `200` response**. If you use a URL like `*.lightning.force.com` instead of `*.my.salesforce.com` it may result in a redirect, which will cause fetches to fail.
   - Set the **Community URL** based on the publicly accessible registration links.
     - This may be something like `https://my-ymca.my.site.com`
     - The URL can be found in Salesforce under **Setup** > **Digital Experiences** > **All Sites**.
   - Choose the key as configured above.

## Usage

See [modules/openy_traction_rec_import/README.md](modules/openy_traction_rec_import/README.md)

## Data Model

This module assumes a Traction Rec "standard" data model in its queries. Any deviations from this model will require overriding the queries in `src/TractionRec.php`.

This model contains a subset of the fields in Traction Rec that are relevant to our usage. All entities have more fields than listed.

Field types are taken from Salesforce's **Setup** > **Object Manager** > **{Entity}** > **Fields & Relationships**.

- Number field options are: `number(length_decimal places)`

```mermaid
erDiagram
  Program_Category__c {
    id Id
    text(80) Name
  }
  Program__c {
    id Id
    text(80) Name
    checkbox Available__c
    textArea(255) Description__c
  }
  Program_Category_Tag__c {
    id Id
    autoNumber Name
    lookup(Program) Program__c
    lookup(Program_Category) Program_Category_c
  }
  Course__c {
    id Id
    text(80) Name
    checkbox Available__c
    text(128) Code__c
    longTextArea(640) Description__c
    lookup(Program) Program__c
    richTextArea Rich_Description__c
  }
  Course_Session__c {
    id Id
    text(80) Name
    checkbox Available__C
    text(128) Code__c
    lookup(Course) Course__c
    longTextArea(640) Description__c
    number(18_0) Num_Option_Entitlements__c
    lookup(ProductAndDiscount) Product__C
    richTextArea Rich_Description__c
    sum Total_Option_Capacity__c
    formula(number) Total_Option_Capacity_Remaining__C
    sum Total_Option_Registrants__c
    count Total_Options_Available__c
  }
  Course_Option__c {
    id Id
    text(80) Name
    number(3_1) Age_Max__c
    number(3_1) Age_Min__c
    checkbox Available__c
    number(18_0) Capacity__c
    picklist(multiSelect) Day_of_Week__c
    date End_Date__c
    text(8) End_Time__c
    text(128) Instructor__c
    lookup(ProductAndDiscount) Product__c
    number(18_0) Registration_Total_c
    longTextArea(3500) Setup_Notes__c
    number(3_0) Setup_Time_Required___c
    date Start_Date__c
    text(8) Start_Time__c
    longTextArea(3500) Tear_Down_Notes__c
    number(3_0) Tear_Down_Time_Required__C
  }
  Course_Session_Option__c {
    id Id
    autoNumber Name
    lookup(CourseOption) Course_Option__c
    masterDetail(CourseSession) Course_Session__c
    checkbox Option_Available__c
    number(18_0) Option_Capacity__c
    number(18_0) Option_Registration_Total__c
  }
  Program_Category__c ||--|{ Program_Category_Tag__c : ""
  Program__c ||--|{ Program_Category_Tag__c : ""
  Program__c ||--|{ Course__c : ""
  Course__c ||--|{ Course_Session__c : ""
  Course_Session__c ||--|{ Course_Session_Option__c : ""
  Course_Option__c ||--|{ Course_Session_Option__c : ""
```
