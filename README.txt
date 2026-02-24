=== Subscriber Manager Lite for Telegram ===
Contributors:      rektification, npagazani
Tags:              woocommerce, telegram, subscriptions
Requires at least: 6.0
Tested up to:      6.9.0
Stable tag:        1.7.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Automatically manage Telegram private channel and group subscribers via WooCommerce.

== Description ==

Automatically manage Telegram private channel and group subscribers via WooCommerce.

With Subscriber Manager Lite for Telegram you can sell access to your private Telegram channels and groups via a Simple product in
WooCommerce. Following a successful checkout, users will be provided with an automatically generated one-time use invite link for channel or group access.
The invite link is validated in the back-end, and the user is automatically granted access. No admin interaction is required.

[Website](https://wctlgm.com) | [Documentation](https://wctlgm.com/kb/)

== Features ==

* Grant access to a single private Telegram channel or group after successful WooCommerce checkout.
* You only need to enter your bot token and URL to get started.
* Semi-automatic channel or group ID retrieval.
* Telegram user ID's automatically retrieved and validated during activation or join request approval.
* Set channel or group access to any Simple product in WooCommerce.
* Choose between two different post-checkout flows (Activation via bot, or direct invite link generation).
* Allow or block external or manual Telgram invites.
* Secure webhook validation.
* Works with WooCommerce HPOS.

Note, in the Lite version members are not automatically removed from channels or groups. They can be removed manually from within Telegram.

== Pro version ==

* Control access to **unlimited** Telegram private channels or groups.
* Control access to multiple channels or groups per product.
* Use with both **Simple** and **Simple Subscription** products in WooCommerce.
* Connect to automation services such as Make.com, n8n, Paddly Connect, etc. to trigger external automations or notifications.
* Automatically removes members when their subscriptions expire.
* Members won't be removed if they have multiple subscriptions and at least one is still active.
* Works with WooCommerce Subscriptions and Flexible Subscriptions by WP Desk.
* Set access expiry for Simple products (automatically removes members on expiration).
* Set order cancellation access cut-off to match a refund policy (members can be optionally instantly removed if they cancel their subscription within the cut-off period).


== Installation ==

Use the standard WordPress plugins installation page and install or upload the plugin.

After plugin activation plugin settings are in **Settings > Telegram Subscriber Manager** in the wp-admin dashboard.
Individual product settings are in the **Product data** settings in the **Telegram Channels** tab (available for **Simple** and **Simple Subscription** products only).


== External services ==

This plugin connects to the Telegram API to manage access to private Telegram channels. It is used to grant or revoke access to these channels based on WooCommerce transactions. The plugin retrieves the user's Telegram ID and validates it during the activation process.
It also sets a webhook to handle communication between your WooCommerce store and Telegram. This service is provided by Telegram: [terms of use](https://telegram.org/tos), [privacy policy](https://telegram.org/privacy).


== Screenshots ==

1. Channel Settings - Lite version
2. Product Settings - Lite version
3. Channel Settings - Pro version
4. Product Settings - Pro version
5. After checkout Thank You page with Activation Code
6. Telegram activation bot activation process


== Changelog ==

= 1.7.0 =
* New: (Pro version) Add support for Variable products and Variable Subscription products.
* New: (Pro version) Telegram channel settings can now be configured per variation.
* Update: Optimize user removal with a single API call instead of two.

= 1.6.0 =
* New: (Pro version) Add support for Flexible Subscriptions by WP Desk as a subscription backend.

= 1.5.2 =
* Update: (Pro version) Add DB migration for old order meta keys.

= 1.5.1 =
* Fix: (Pro version) Bug causing users to be removed from channels during "pending_cancel" status.

= 1.5.0 =
* Update: Improve Set Webhook warning message.
* New: Add support documentation links in settings pages.
* New: Action hook for invite links generation.
* New: (Pro version) Webhook for connecting to automation services such as Make, N8N, Pabbly Connect, etc.
* Fix: (Pro version) remove invalid support tab link in settings page.

= 1.4.0 =
* New: Make the activation step optional.
* Update: Emails formatting and wording.
* Update: Additional logging throughout.
* Fix: Several text updates to remove specific references to "Channels" as the plugin now supports groups.
* Fix: Internal order search functions to improve validation.
* Fix: Add contextual bot allowed_updates depending on checkout flow to prevent overwhelming site with webhook calls which might occur in large or busy groups.
* Fix: Contextual switching of available product setting options when editing products.

= 1.3.0 =
* Improved private group compatibility. Now supports private groups and supergroups.
* Fixed issue where bots respond in groups with invalid command messages.
* Fixed issue with deep link activation not working.
* Extend translatable text.
* Add logger class and basic logging.

= 1.2.0 =
* Moved email templates to WooCommerce integration, making them customizeable in the WooCommerce settings.

= 1.1.1 =
* Fixed an issue where non-validated invites may not be declined.
* Add an option to allow external/manual channel invites (skips validation)
* Dependency updates

= 1.1.0 =
* Fixed an issue where activation codes may be displayed before payment is received.
* Update to the latest Freemius SDK

= 1.0.2 =
* .org Initial Release

= 1.0.0 =
* Initial Release
