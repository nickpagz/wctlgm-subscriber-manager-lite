=== Subscriber Manager Lite for Telegram ===
Contributors:      rektification, npagazani
Tags:              woocommerce, telegram, membership, invite link, sell access
Requires at least: 6.0
Tested up to:      6.9.0
Stable tag:        2.1.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Automatically manage access to private Telegram channels and groups through WooCommerce. Invite links, subscriber management, and more.

== Description ==

**Subscriber Manager Lite for Telegram** automatically manages access to your private Telegram channels and groups through WooCommerce. Whether you're selling a premium community, bundling a support channel with a course, or offering exclusive bonus content to customers — the plugin handles the access so you don't have to.

When a customer completes a purchase, the plugin generates a secure, one-time invite link available on the "Thank you" page, in the "My Account" area, and delivers it via email. The invite link is validated on the backend so only paying customers get access. No manual approvals, no spreadsheets, no chasing join requests.

**Why Subscriber Manager for Telegram?**

Managing access to a private Telegram channel or group manually is tedious and doesn't scale. This plugin automates the entire flow from WooCommerce checkout to Telegram channel access — whether the channel is the product itself or a companion to something else you sell.

* **Online courses and coaching** — give students or clients instant access to a private support group or community after purchase.
* **Membership communities** — sell access to a premium Telegram channel or group as a standalone product.
* **Digital products and events** — bundle exclusive Telegram channels or groups for updates, bonuses, or follow-up resources.
* **SaaS and subscriptions** — provide a customer support or beta testing channel or group alongside your software.

**How It Works**

1. Create a Telegram bot using BotFather and enter your bot token in the plugin settings.
2. Add your private Telegram channel or group ID.
3. Assign the channel to any WooCommerce Simple or Variable product.
4. A customer purchases the product and receives an invite link automatically.
5. The customer clicks the link, and the plugin validates and grants access — no admin interaction needed.

[Website](https://wctlgm.com) | [Documentation](https://wctlgm.com/kb/)

== Features ==

**Automate Telegram Channel and Group Access**

* **Connect Telegram to WooCommerce** — link a private channel or group to any WooCommerce product. Customers get access automatically after purchase — whether the channel is the product or a bonus.
* **Simple and Variable product support** — assign Telegram channel access at the product level for Simple products or per-variation for Variable products.
* **Automatic invite link generation** — secure, one-time invite links are generated and delivered to customers via email after checkout.
* **Two checkout flows** — choose between an Activation Code flow (customer activates via the Telegram bot) or a Direct Invite Link flow (invite link delivered immediately after purchase).
* **Access control** — invite links are validated on the backend. Only customers with a valid purchase can join. Optionally allow or block external or manually created Telegram invites.

**Manage Your Subscribers (New in v2.0)**

* **Subscriber dashboard** — view all Telegram subscribers, their channel memberships, and current statuses directly in wp-admin.
* **Live status sync** — verify a subscriber's live Telegram membership status with one click and automatically populate their profile details (name, username).
* **Admin actions** — remove, ban, or unban users, and revoke pending invite links directly from the subscriber detail modal.
* **Lifecycle tracking** — subscriber join, leave, and kick events are automatically tracked via Telegram webhook events.
* **Search and filter** — find subscribers by name, username, or Telegram ID, and filter by channel.

**Built for WordPress and WooCommerce**

* **WooCommerce HPOS compatible** — fully supports High-Performance Order Storage.
* **Customizable emails** — activation and invite link emails are integrated with WooCommerce and can be customized in WooCommerce email settings.
* **Secure webhook validation** — all communication between your store and Telegram is validated via a secret token.
* **Logging** — key events are logged via WooCommerce Logger for easy troubleshooting.
* **Semi-automatic channel ID retrieval** — retrieve your channel or group ID directly from the plugin settings page.

Note: in the Lite version, members are not automatically removed from channels or groups when orders are cancelled or refunded. They can be removed manually from the subscriber table or from within Telegram. The [Pro version](https://wctlgm.com) adds automatic member removal, multiple channels, subscription support, and more.

== Pro Version ==

Ready to scale your Telegram membership business? The Pro version is designed for creators and businesses that need more control and automation.

* **Unlimited channels and groups** — control access to as many private Telegram channels and groups as you need, and assign multiple channels per product.
* **Subscription support** — works with WooCommerce Subscriptions and Flexible Subscriptions by WP Desk. Members are automatically removed when subscriptions expire.
* **Smart member removal** — members with multiple active subscriptions won't lose access until all subscriptions expire.
* **Simple product access expiry** — set an expiration period for Simple products and automatically remove members when it expires.
* **Cancellation cut-off** — optionally set an order cancellation access cut-off period to match your refund policy and remove members instantly if they cancel within the cut-off window.
* **Automation webhooks** — connect to automation services such as Make.com, n8n, Pabbly Connect, and others to trigger external workflows and notifications.

[Learn more about the Pro version](https://wctlgm.com) | [Compare Lite and Pro](https://wctlgm.com)

== Installation ==

**Quick Start**

1. Install and activate the plugin from **Plugins > Add New** in your WordPress admin dashboard.
2. Go to **Settings > Telegram Subscriber Manager** to open the plugin settings page.
3. Create a Telegram bot using [BotFather](https://t.me/botfather) and copy the bot token.
4. Paste the bot token into the **Bot Token** field and click **Set Webhook** to establish the connection between your site and Telegram.
5. Add your private Telegram channel or group by clicking **Add Channel** and using the semi-automatic ID retrieval tool.
6. Go to **Products** and edit the WooCommerce product you want to sell access with.
7. In the **Product data** section, open the **Telegram Access** tab, enable Telegram access, and select your channel. For Variable products, configure Telegram access on each individual variation.
8. Publish the product and you're ready to sell access.

**Post-Checkout Flows**

The plugin supports two checkout flows. You can choose which flow to use in the plugin settings:

* **Direct Invite Link flow** — the invite link is generated immediately after checkout and delivered via the Thank You page and order email. No interaction with the Telegram bot is required. This is the simpler option if you don't need the additional verification step.
* **Activation Code flow (Legacy)** — after checkout, the customer receives an activation code via the WooCommerce Thank You page and order email. The customer sends the code to your Telegram bot (using the `/activate` command), and the bot validates the code and delivers the invite link. This flow adds an extra verification step by linking the customer's Telegram account to their order.

For detailed setup instructions, visit the [documentation](https://wctlgm.com/kb/).

== Frequently Asked Questions ==

= How do I sell access to a private Telegram channel with WooCommerce? =

Install this plugin, create a Telegram bot using BotFather, and enter the bot token in the plugin settings. Add your private channel or group, then assign it to a WooCommerce product in the product's Telegram Access tab. When a customer completes a purchase, they'll automatically receive a one-time invite link to join your channel.

= Do I need a Telegram bot? =

Yes. The plugin requires a Telegram bot to generate invite links, validate join requests, and communicate with your channel. Creating a bot is free and takes about two minutes using [BotFather](https://t.me/botfather). The bot must be added as an administrator to your private channel or group.

= Does this plugin work with WooCommerce Variable products? =

Yes. You can configure Telegram channel access per variation, so different product variations can grant access to different channels. The Telegram Access settings appear on each individual variation within the product editor.

= What is the difference between the Activation Code flow and the Direct Invite Link flow? =

The **Direct Invite Link flow** (recommended) generates and delivers the invite link immediately after checkout via email and the Thank You page. It's simpler and links the customer's Telegram account to their order upon joining the channel or group.

The **Activation Code flow** sends a code to the customer after checkout. The customer then sends this code to your Telegram bot, which validates it and delivers the invite link. This links the customer's Telegram account to their WooCommerce order, however increases the number of steps for joining.

= Can I see who has joined my Telegram channel? =

Yes. The subscriber table (new in v2.0) shows all tracked subscribers, their Telegram profile details, channel memberships, and current membership status. You can search by name, username, or Telegram ID, and filter by channel. Click on any subscriber to open a detail modal with their full history and linked orders.

= Can I remove or ban a subscriber from my Telegram channel? =

Yes. From the subscriber detail modal you can remove a user (they can rejoin with a new invite), ban a user (permanently blocks them from the channel), unban a previously banned user, or revoke a pending invite link. These actions are executed via the Telegram API directly from your WordPress dashboard.

= Are the invite links secure? =

Yes. Invite links are one-time use and are generated on demand via the Telegram Bot API. The plugin validates incoming webhook requests using a secret token. By default, join requests are validated against WooCommerce order data so only paying customers can join. You can optionally allow external invites if you want to permit manually created or shared invite links.

= What happens if a customer cancels their order or requests a refund? =

In the Lite version, members are not automatically removed when an order is cancelled or refunded. You can manually remove them from the subscriber table in wp-admin or directly within Telegram. The [Pro version](https://wctlgm.com) adds automatic member removal on subscription expiry and configurable cancellation cut-off periods.

= Does this plugin work with WooCommerce Subscriptions? =

The Lite version works with Simple and Variable products only. It does not integrate with WooCommerce Subscriptions or other subscription plugins. The [Pro version](https://wctlgm.com) supports WooCommerce Subscriptions and Flexible Subscriptions by WP Desk, including automatic member removal when subscriptions expire. Support for additional subscription plugins are on our roadmap.

= What is the difference between the Lite and Pro versions? =

The **Lite version** supports one Telegram channel or group, Simple and Variable products, manual subscriber management, and both checkout flows.

The **Pro version** adds unlimited channels and groups, multiple channels per product, WooCommerce Subscriptions support, automatic member removal on expiry, Simple product access expiry, order cancellation cut-off periods, and automation webhooks for connecting to services like Make.com and n8n. See the [full comparison](https://wctlgm.com).

== External services ==

This plugin connects to the Telegram API to manage access to private Telegram channels. It is used to grant or revoke access to these channels based on WooCommerce transactions. The plugin retrieves the user's Telegram ID and validates it during the activation process.
It also sets a webhook to handle communication between your WooCommerce store and Telegram. This service is provided by Telegram: [terms of use](https://telegram.org/tos), [privacy policy](https://telegram.org/privacy).


== Screenshots ==

1. Plugin settings — Bot token, webhook, and channel configuration
2. Product settings — Telegram Access tab for Simple products
3. Channel settings — Pro version with multiple channels
4. Product settings — Pro version with subscription options
5. Post-checkout Thank You page with activation code
6. Telegram bot activation and invite link delivery
7. Subscriber table — View all subscribers with search, filter, and status badges
8. Subscriber detail modal — Live Telegram status, linked orders, and admin actions


== Changelog ==

= 2.1.0 =
* New: (Pro version) Manual user management — set an expiry or associate an order for users who joined outside the normal flow (e.g. via a channel's primary link).
* New: (Pro version) Surface invite-generation failures in the Pending Invites table with a Retry action, instead of failing silently.
* New: (Pro version) Resend an existing invite link, or "Resend new" to revoke and regenerate a fresh link, directly from the subscriber tools.
* New: (Pro version) Optionally remove customers from Telegram after a subscription has stayed on-hold for a configurable number of days (grace period for payment retries).
* Update: (Pro version) getChatMember pre-flight prevents resending to users already in the channel and reconciles their status.
* Update: The activation-step toggle is now hidden for sites not already using it (feature being deprecated).
* Security: Reject webhook requests when no secret token is configured, and compare tokens in constant time.
* Security: Generate the webhook secret token with a cryptographically secure generator.
* Security: Add capability and nonce checks to the Set Webhook and channel-ID admin AJAX actions.
* Fix: Prevent a fatal error when an order references a product that was later deleted.
* Fix: Preserve the activation code if invite generation fails, so customers can retry instead of being left with no access.
* Fix: Internal robustness improvements to Telegram API error handling and subscriber-order linking.

= 2.0.0 =
* New: Subscriber table — view and manage all Telegram subscribers from the admin dashboard.
* New: Subscriber detail modal with live Telegram membership status, linked orders, and per-channel actions.
* New: Admin actions — remove, ban, unban users, or revoke pending invite links directly from the subscriber table.
* New: Sync Status — verify live Telegram membership for any subscriber with one click, and automatically populate user profile details (name, username).
* New: Channel filter and search in the subscriber table.
* New: Automatic tracking of subscriber lifecycle via Telegram webhook events (join, leave, kick).
* New: Data migration — existing subscribers from order meta are automatically imported on upgrade.

= 1.7.0 =
* New: Add support for Variable products with per-variation Telegram channel settings.
* New: (Pro version) Add support for Variable and Variable Subscription products.
* Update: (Pro version) Optimize user removal with a single API call instead of two.

= 1.6.0 =
* New: (Pro version) Add support for Flexible Subscriptions by WP Desk as a subscription backend.

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
