=== Subscriber Manager Lite for Telegram ===
Contributors:      rektification, npagazani
Tags:              woocommerce, telegram, subscriptions
Requires at least: 6.0
Tested up to:      6.7.1
Stable tag:        1.0.2
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Automatically manage Telegram private channel subscribers via WooCommerce.

== Description ==

Automatically manage Telegram private channel subscribers via WooCommerce.

With Subscriber Manager Lite for Telegram you can sell access to your private Telegram channels via a Simple product in
WooCommerce. Following a successful checkout, users will be able to link to and submit an activation code in your chat bot.
This automatically generates an invite link for the user to click on. The invite link is validated in the back-end, and the user is automatically granted access.

== Features ==

* Grant access to a single private Telegram channel after successful WooCommerce checkout.
* You only need to enter your bot token and URL to get started.
* Semi-automatic channel ID retrieval.
* Telegram user ID's automatically retrieved and validated during activation.
* Set channel access to any Simple product in WooCommerce.
* Secure webhook validation.
* Works with WooCommerce HPOS

Note, in the Lite version channel members are not automatically removed. They can be removed manually from within Telegram.

== Pro version ==

* Control access to **unlimited** Telegram private channels.
* Control access to multiple channels per product. 
* Use with both Simple and Simple Subscription products in WooCommerce.
* Automatically removes channel members when their subscriptions expire.
* Members won't be removed from channels if they have multiple subscriptions and at least one is still active.
* Works best with WooCommerce Subscriptions. Other subscription plugins support coming soon.
* Set access expiry for Simple products (automatically removes members on expiration).
* Set order cancellation access cut-off to match a refund policy (members can be optionally instantly removed if they cancel their subscription within the cut-off period).


== Installation ==

Use the standard WordPress plugins installation page and install or upload the plugin.

After plugin activation plugin settings are in **Settings > Telegram Subscriber Manager** in the wp-admin dashboard.
Individual product settings are in the **Product data** settings in the **Telegram Channels** tab (available for Simple and Simple Subscription products only).


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

= 1.0.2 =
* .org Initial Release

= 1.0.0 =
* Initial Release
