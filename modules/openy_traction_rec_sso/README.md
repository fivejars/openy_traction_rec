# YMCA Website Services Traction Rec integration: SSO

The module creates additional options for using SSO login status to flexibly manage menu links by providing an additional field for visibility management.
It also provides additional endpoints to redirect to SSO login/logout points.
It is also recommended to create a separate Salesforce application for this functionality, because it does not require granting a lot of permissions.

## Configuration

### Create a separate Connected App for SSO in Salesforce

1. In **Salesforce** > **Setup** > **App Manager**, create a **New Connected App**.
   - Set a **Name** and **Email**.
     - The **Contact Email** is not used for authentication.
   - Check **Enable OAuth Settings**
     - Set the callback url as the base URL with suffix for OAuth (https://YOURSITE.DOMAIN/openy-gc-auth-traction-rec-sso/oauth_callback)
     - Ensure the app has the following **Selected OAuth Scopes**
       - Manage user data via APIs (api)
     - Uncheck all other options in the **OAuth** section.
   - **Save** the Connected App
2. Once the app is saved, you will need to get the **Consumer Details**:
   - In the "My Connected App" screen that appears once you save (or via **Setup** > **App Manager**), click **Manage Consumer Details**.
   - Save the **Consumer Key** and **Consumer Secret** for the next step.

Review all of these steps carefully. Missing any of them can result in an inability to query the API.

### Configure the connection in Drupal

1. Go to **Admin** > **YMCA Website Services** > **Integrations** > **Traction Rec** > **SSO settings** (`/admin/openy/integrations/traction-rec/sso`) to configure the keys & secrets provided by Traction Rec.
   - Add the **Consumer key** and **Consumer Secret** from **Manage Consumer Details** in Salesforce.
   - Set the **Application base URL** based on the publicly accessible registration links.
     - As usual it the same with **Community URL** from main module Auth settings
     - This may be something like `https://my-ymca.my.site.com`
     - The URL can be found in Salesforce under **Setup** > **Digital Experiences** > **All Sites**.

### Configure the menu links in Drupal

1. By default, the module creates several content links targeting SSO endpoints in the Account menu, so review them and customize them as you need.
2. Customize other menu links related to SSO logon status using the "Traction Rec SSO: Visibility restrictions" field (in any menu).
