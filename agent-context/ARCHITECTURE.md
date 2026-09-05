# Architecture Overview

> **Version:** 2.0.0 | **Last updated:** 2026-03-02

## Directory Structure

```
wctlgm-subscriber-manager-lite/
├── wctlgm-subscriber-manager-lite.php          # Entry point, Freemius SDK, constants
├── composer.json                                # Freemius SDK dependency
├── composer.lock
├── CLAUDE.md                                    # AI agent project instructions
├── README.txt                                   # WordPress.org readme
├── .distignore                                  # Files excluded from distribution
├── includes/
│   ├── class-subscriber-manager-lite-wctlgm.php                       # Main orchestrator
│   ├── class-subscriber-manager-lite-wctlgm-logger.php                # Logging utility
│   ├── class-subscriber-manager-lite-wctlgm-settings.php              # Admin settings, product data, subscriber AJAX
│   ├── class-subscriber-manager-lite-wctlgm-api-handler.php           # Telegram API client
│   ├── class-subscriber-manager-lite-wctlgm-endpoint-handler.php      # REST endpoint
│   ├── class-subscriber-manager-lite-wctlgm-bot-interaction-handler.php # Bot commands, join requests, chat_member
│   ├── class-subscriber-manager-lite-wctlgm-order-handler.php         # Order status processing
│   ├── class-subscriber-manager-lite-wctlgm-subscriptions-handler.php # Core logic (activation, invites, validation)
│   ├── class-subscriber-manager-lite-wctlgm-email-handler.php         # Email class registration
│   ├── class-subscriber-manager-lite-wctlgm-database.php              # Custom DB tables & CRUD
│   ├── class-subscriber-manager-lite-wctlgm-users-list-table.php      # WP_List_Table for subscriber admin UI
│   └── emails/
│       ├── class-subscriber-manager-lite-wctlgm-activation-email.php  # Post-activation email
│       └── class-subscriber-manager-lite-wctlgm-invite-links-email.php # Direct invite email
├── templates/emails/
│   ├── telegram-channel-activation.php          # Activation email HTML template
│   ├── telegram-channel-invite-links.php        # Invite links email HTML template
│   └── plain/
│       ├── telegram-channel-activation.php      # Activation email plain text
│       └── telegram-channel-invite-links.php    # Invite links email plain text
├── assets/css/
│   └── wctlgm-admin-users.css                   # Subscriber table & modal styles
├── assets/js/
│   ├── wctlgm-subscriber-manager-lite.js        # Admin JS (jQuery, no build step)
│   └── wctlgm-admin-users.js                    # Subscriber table tab switching, modal, AJAX actions
├── .github/workflows/
│   ├── build-release.yml                        # GitHub release → ZIP asset
│   ├── push-deploy.yml                          # WordPress.org SVN deployment
│   └── push-deploy-dry-run.yml                  # Deployment dry run
└── .wordpress-org/                              # WordPress.org plugin assets (icons, banners)
```

## Plugin Initialization Sequence

### Step 1: Entry Point (`wctlgm-subscriber-manager-lite.php`)

1. Check `wctlgm_fs()` doesn't already exist (prevents conflict with pro plugin)
2. Initialize Freemius SDK (ID `16907`, slug `wctlgm-subscriber-manager-lite`)
3. Fire `wctlgm_fs_loaded` action
4. Define constants: `WCTLGM_SML_PLUGIN_BASE`, `WCTLGM_SML_PLUGIN_DIR`
5. Register activation hook (checks for WooCommerce and pro plugin conflict)
6. Register deactivation hook (no-op currently)
7. Declare HPOS compatibility via `before_woocommerce_init`
8. Register `admin_init` check for pro plugin (auto-deactivates lite if pro is active)
9. Require Logger and main orchestrator class files
10. Call `wctlgm_subscriber_manager_lite_start()` → instantiates orchestrator

### Step 2: Orchestrator (`Subscriber_Manager_Lite_WCTLGM`)

Constructor calls three methods:

1. **`load_dependencies()`** — requires all class files in order:
   - Settings
   - Subscriptions_Handler
   - Order_Handler
   - API_Handler
   - Bot_Interaction_Handler
   - Endpoint_Handler
   - Email_Handler
   - Database
   - Users_List_Table

2. **`define_admin_settings()`** — hooks `init_settings()` to `init` action (creates Settings instance)

3. **`initialize_handlers()`** — hooks handler initialization:
   - `Database::init()` — registers `admin_init` hook for table creation/migration
   - `Endpoint_Handler` → new instance at `plugins_loaded` priority 10
   - `Bot_Interaction_Handler::init()` → at `init`
   - `Order_Handler::init()` → at `plugins_loaded`
   - `Email_Handler::init()` → at `plugins_loaded`

## Core Classes

### Subscriber_Manager_Lite_WCTLGM

**File:** `includes/class-subscriber-manager-lite-wctlgm.php`
**Role:** Main orchestrator. Loads all dependencies and wires WordPress hooks. No business logic.

### Subscriber_Manager_Lite_WCTLGM_Settings

**File:** `includes/class-subscriber-manager-lite-wctlgm-settings.php`
**Role:** Admin settings page, product/variation data panels, subscriber table UI, AJAX handlers.

Key responsibilities:
- Settings page at **Settings > Telegram Subscriber Manager** (`add_options_page`, slug `wctlgm-settings`)
- **Two-tab layout:** Settings tab (form + support link) and Subscribers tab (list table)
- Registered settings: `wctlgm_bot_token`, `wctlgm_bot_url`, `wctlgm_allow_external_invites`, `wctlgm_require_activation_flow`, `wctlgm_channels`
- **Activation step is legacy/deprecated:** `wctlgm_require_activation_flow` stays a registered setting, but `register_settings()` only adds its settings *field* (`add_settings_field`) when the option is already enabled. New sites never see the "Require Activation Step" checkbox — it is hidden to discourage adoption of the flow being deprecated
- Product data tab "Telegram Access" with classes `show_if_simple`, `show_if_variable`, `hide_if_subscription`
- **Variable product support:** Tab panel shows "configure on variations" message for variable products (toggled by JS). Per-variation channel select rendered via `wctlgm_variation_telegram_fields()`. Variation data saved via `wctlgm_save_variation_telegram_data()` with dual nonce validation (AJAX `save-variations` + main form `woocommerce_save_data`).
- **Single channel enforcement:** `sanitize_channels()` only processes `$input[0]`, always returns single-entry array
- Upsell notices for multi-channel and pro features via `wctlgm_fs()->get_upgrade_url()`
- **Subscriber table:** `users_page()` renders `Users_List_Table` with search and channel filter
- **Subscriber detail modal:** HTML appended after `.wrap` div, populated via AJAX
- AJAX handlers: `wctlgm_set_webhook`, `check_and_set_channel_id`, `wctlgm_get_user_details`, `wctlgm_subscriber_action`, `wctlgm_sync_user_status`
- `ajax_get_user_details()` — fetches user info, orders, per-channel live Telegram status
- `ajax_subscriber_action()` — dispatches to `handle_remove_action()`, `handle_ban_action()`, or `handle_revoke_action()`
- `ajax_sync_user_status()` — calls `get_chat_member()` for each active/pending channel, updates DB status and user profile (name, username)
- Webhook warning admin notice when `wctlgm_webhook_clicked` is false
- Legacy migration from `wctlgm_force_activation_flow` to `wctlgm_require_activation_flow`
- Upgrade migration `maybe_reprompt_webhook_after_upgrade()` (hooked to `admin_init`): on the first admin request after an upgrade, compares the stored `wctlgm_version` option against the `WCTLGM_SML_VERSION` constant; when upgrading from below `WEBHOOK_REPROMPT_BELOW_VERSION` (the version that made the webhook secret mandatory) and a bot token is configured, deletes `wctlgm_webhook_clicked` so the webhook notice re-appears and the admin re-registers the webhook (resyncing the secret). Records `wctlgm_version` so it runs once; fresh installs (no bot token) are never prompted
- Activation flow change handler: resets `wctlgm_webhook_clicked` on toggle
- **Enqueue hook:** `settings_page_wctlgm-settings` (derived from `add_options_page` slug)

### Subscriber_Manager_Lite_WCTLGM_API_Handler

**File:** `includes/class-subscriber-manager-lite-wctlgm-api-handler.php`
**Role:** Telegram Bot API wrapper.

Methods:
- `handle_set_webhook_actions($url, $secret_token)` — sets webhook + bot commands
- `set_webhook($url, $secret_token)` — with `allowed_updates` based on activation flow
- `set_commands()` — registers `/start`, `/activate`, `/help`
- `send_message($chat_id, $message)`
- `generate_invite_link($chat_id)` — `creates_join_request: true`
- `approve_join_request($chat_id, $user_id)`
- `revoke_invite_link($chat_id, $invite_link)`
- `deny_join_request($chat_id, $user_id)`
- `get_chat_member_status($user_id, $channel_id)` — returns status string with 5-min transient cache
- `get_chat_member($user_id, $channel_id)` — returns full result (status + user object) without caching
- `remove_user_from_channel($user_id, $channel_id)` — calls `banChatMember` (permanent ban)
- `unban_user_from_channel($user_id, $channel_id)` — calls `unbanChatMember` (allows rejoin)
- `get_allowed_updates()` — conditionally includes `message` when activation flow is enabled; always includes `chat_member`

### Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler

**File:** `includes/class-subscriber-manager-lite-wctlgm-endpoint-handler.php`
**Role:** REST endpoint for Telegram webhooks.

- Route: `POST /wp-json/wctlgm/v1/telegram-bot/`
- Authentication: `X-Telegram-Bot-Api-Secret-Token` header matched against `wctlgm_secret_token`
- Creates `Bot_Interaction_Handler` instance and delegates `process_telegram_request()`
- Response format: `{method: "sendMessage", chat_id, text}` or `{status: "ok"}`

### Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler

**File:** `includes/class-subscriber-manager-lite-wctlgm-bot-interaction-handler.php`
**Role:** Processes bot commands, join requests, and chat member updates. Tracks subscriber lifecycle in DB.

- **Static `init()`:** Only registers `wctlgm_send_activation_email` Action Scheduler hook
- **`process_telegram_request($data)`:** Routes incoming Telegram updates:
  - `chat_join_request` → `process_join_request()`
  - `chat_member` → `process_chat_member_update()`
  - `edited_channel_post` / `edited_message` → channel ID capture for settings
  - `message` (private chat only) → command routing: `/start`, `/activate`, `/help`, default
- **`handle_start_command($args)`:** Welcome message, or auto-activate if payload matches code pattern
- **`handle_activation_command($code)`:** Creates `Subscriptions_Handler`, calls `process_activation_code()`, schedules activation email, creates/updates DB user record and links to order channels
- **`handle_help_command()`:** Returns help text with example
- **`process_join_request($data)`:** Creates `Subscriptions_Handler`, validates, approves+revokes or denies, creates/updates DB user and channel records
- **`process_chat_member_update($data)`:** Handles `chat_member` webhook events (user left, kicked, rejoined). Includes guards to preserve admin-set statuses (`removed`, `banned`)
- **`extract_user_data($from)`:** Extracts `telegram_username`, `first_name`, `last_name` from Telegram webhook `from` data
- **`send_activation_email($args)` (static):** Triggers `wctlgm_activation` WooCommerce email

### Subscriber_Manager_Lite_WCTLGM_Order_Handler

**File:** `includes/class-subscriber-manager-lite-wctlgm-order-handler.php`
**Role:** Reacts to WooCommerce order status changes.

- **`init()`:** Hooks `woocommerce_order_status_changed`, `woocommerce_email_order_details`, `woocommerce_order_details_before_order_table`, `wctlgm_send_invite_links_email`
- **`maybe_process_order($order_id, $old_status, $new_status)`:**
  - Guards: order exists, has telegram product, new status is processing/completed, skip processing→completed
  - Activation flow: generates 8-char activation code if not already present
  - Direct flow: generates invite links via `Subscriptions_Handler::get_channel_invites()`, stores meta, creates pending DB records, fires `wc_wctlgm_invite_links_generated`, schedules email (5s delay)
- **`get_telegram_meta_id($item)`:** Resolves the correct ID for Telegram meta lookups — returns variation ID if present, otherwise product ID
- **`order_has_telegram_product($order)`:** Checks for simple or variable products with non-empty `_telegram_channel_ids` (using variation-level meta for variable products)
- **Email injection:** Injects activation code into `customer_processing_order` and `customer_completed_order` emails
- **Order details display:** Shows activation code (or "Activated"), invite links, or pending message

### Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler

**File:** `includes/class-subscriber-manager-lite-wctlgm-subscriptions-handler.php`
**Role:** Core business logic for activation codes, join request validation, and invite generation.

**Important:** This is a single class — no factory, no interface, no inheritance (unlike the pro version's factory + interface pattern).

- **Instance-based:** Constructor creates its own `API_Handler` and `Logger` instances
- **`process_activation_code($code, $telegram_user_id)`:** Finds order by `_activation_code`, validates status, then **generates invites first**. Only when invite generation succeeds does it store `_telegram_user_id`, delete the activation code, store invite meta, and create pending DB records. If invite generation fails, the one-time code is left intact (returns the failure response) so the customer can retry rather than being left "activated" with no access
- **`is_join_request_valid($user_id, $invite_link, $chat_id, $allow_external_invites)`:**
  - Finds order by invite link + chat ID
  - Activation flow: checks `_telegram_user_id` matches requesting user
  - Direct flow: captures `_telegram_user_id` on first join, blocks overwrites
  - Falls through to `allow_external_invites` check
- **`get_channel_invites($order)`:** Iterates order items, resolves variation ID for meta lookups, generates invite links per channel, deduplicates with `get_existing_invite_for_channel()`
- **`find_order_by($meta_key, $meta_value)`:** Meta query via `wc_get_orders()`, returns null if >1 match
- **`find_order_by_invite_link($invite_link, $chat_id)`:** Uses indexed meta key `_channel_invite_{chat_id}`

### Subscriber_Manager_Lite_WCTLGM_Email_Handler

**File:** `includes/class-subscriber-manager-lite-wctlgm-email-handler.php`
**Role:** Registers custom WooCommerce email classes.

- Hooks into `woocommerce_email_classes` filter
- Lazily loads email class files and registers:
  - `wctlgm_activation` → `Activation_Email`
  - `wctlgm_invite_links` → `Invite_Links_Email`

### Subscriber_Manager_Lite_WCTLGM_Logger

**File:** `includes/class-subscriber-manager-lite-wctlgm-logger.php`
**Role:** Centralized logging wrapper around `WC_Logger`.

- Log source: `wctlgm-subscriber-manager-lite`
- Static methods: `debug()`, `info()`, `notice()`, `warning()`, `error()`, `critical()`, `alert()`, `emergency()`
- Helper methods: `log_api_request()`, `log_api_response()`
- **Note:** Logger has both static methods (called directly) and is also instantiated as instance property in handlers that call instance methods on it. Both work because the underlying `get_logger()` is static.

### Subscriber_Manager_Lite_WCTLGM_Database

**File:** `includes/class-subscriber-manager-lite-wctlgm-database.php`
**Role:** Custom database tables for subscriber tracking. All static methods.

- **`init()`:** Registers `admin_init` hook for `maybe_create_tables_and_migrate()`
- **Tables:** `{prefix}wctlgm_telegram_users` (user records), `{prefix}wctlgm_user_channels` (user-channel relationships with status tracking). Table names are shared with pro plugin for upgrade compatibility.
- **User CRUD:** `get_or_create_user()`, `update_user()`, `get_user()`
- **Channel CRUD:** `add_user_channel()`, `find_user_channel_record()`, `update_user_channel_status()`, `link_user_to_order_channels()`, `get_user_channel()`, `get_user_channels()`, `get_channels_by_order()`, `find_by_invite_link()`, `update_channel_record()`
- **List table queries:** `get_subscribers_for_table()`, `count_subscribers()` — aggregated per-user with channel status pairs, support search/filter/sort/pagination
- **Multi-access:** `get_other_active_channel_access()` — checks for other active records before removal
- **Migration:** `migrate_existing_data()` — scans orders with `_telegram_user_id` or `_channel_invite_*` meta, creates user + channel records. Runs once on first install.
- **Status values:** `pending`, `active`, `left`, `removed`, `banned`, `expired`
- **Schema version:** Tracked via `wctlgm_db_version` option; `wctlgm_needs_setup` transient triggers creation on activation

### Subscriber_Manager_Lite_WCTLGM_Users_List_Table

**File:** `includes/class-subscriber-manager-lite-wctlgm-users-list-table.php`
**Role:** WP_List_Table subclass for displaying subscribers in admin.

- 4 columns: Username (with View Details / Sync Status row actions), Name, Channels (status badges with priority-based dedup), Joined
- Channel filter dropdown via `extra_tablenav()`
- Search by username, name, Telegram user ID, or order ID
- Sortable by joined date
- Nonce-protected search/filter form (`wctlgm_subscriber_table`)

### Email Classes (`includes/emails/`)

- **`Activation_Email`** — Sent after `/activate` processing with invite links. Triggered via Action Scheduler (`wctlgm_send_activation_email`).
- **`Invite_Links_Email`** — Sent directly post-purchase (5s delay via Action Scheduler) when activation flow is disabled.

Templates: `templates/emails/` (HTML) and `templates/emails/plain/` (plain text).

## Lite vs Pro Limitations

| Feature | Lite | Pro |
|---------|------|-----|
| Channels per product | 1 | Unlimited |
| Product types | Simple, Variable | Simple, Variable, Subscription, Variable Subscription |
| Subscription plugin support | None | WooCommerce Subscriptions, Flexible Subscriptions |
| Subscriber table / admin actions | Yes (view, remove, ban, unban, revoke, sync) | Yes |
| Automatic member removal | No (manual via subscriber table) | Yes (on cancel/expire) |
| Access expiry for simple products | No | Yes (scheduled via Action Scheduler) |
| Cancel cut-off period | N/A | Yes (`_telegram_cut_off` meta) |
| Remove on cancel setting | N/A | Yes (`_telegram_remove_on_cancel` meta) |
| Handler architecture | Single class | Factory + Interface pattern |
| Webhook payload enrichment | No | Yes (for automation services) |
| Variable product variation fields | Yes (channel select only) | Yes (channel select, expiry, remove-on-cancel, cut-off) |
| Postmeta migrator | No | Yes |

## WordPress Options

| Option Key | Type | Description |
|------------|------|-------------|
| `wctlgm_bot_token` | string | Telegram bot API token (stored as password field) |
| `wctlgm_bot_url` | string | Telegram bot URL for deep links (e.g., `https://t.me/bot_username`) |
| `wctlgm_secret_token` | string | 32-char webhook secret token (auto-generated on Set Webhook) |
| `wctlgm_channels` | array | Single channel/group config: `[{name, id}]` |
| `wctlgm_require_activation_flow` | bool | Toggle activation step vs direct invites |
| `wctlgm_allow_external_invites` | bool | Allow non-order invite link validation |
| `wctlgm_webhook_clicked` | bool | Whether "Set Webhook" button has been clicked |
| `wctlgm_activation_flow_migrated` | bool | One-time migration flag for legacy `wctlgm_force_activation_flow` |
| `wctlgm_version` | string | Last-seen plugin version; set by `maybe_reprompt_webhook_after_upgrade()` to run the post-upgrade webhook re-prompt once |
| `wctlgm_db_version` | string | Database schema version (currently `1.0.0`) |

## Order/Product Meta Keys

### Product/Variation Meta

| Key | Type | Description |
|-----|------|-------------|
| `_telegram_channel_ids` | array | Channel/group IDs this product/variation grants access to (stored on product for simple, on variation for variable) |

**Not present in lite (pro-only):** `_telegram_channel_expiry`, `_telegram_remove_on_cancel`, `_telegram_cut_off`

### Order Meta

| Key | Type | Description |
|-----|------|-------------|
| `_activation_code` | string | 8-char alphanumeric code (deleted after activation) |
| `_telegram_user_id` | string | Linked Telegram user ID |
| `_channel_invite_{channel_id}` | string | Invite link for specific channel (indexed by channel ID) |

## Hooks & Filters

### Actions (fired by plugin)

| Hook | When | Parameters |
|------|------|------------|
| `wctlgm_fs_loaded` | After Freemius SDK initialized | — |
| `wc_wctlgm_invite_links_generated` | After invite links generated for an order | `$order_id`, `$channels` |
| `wctlgm_send_activation_email` | Scheduled: send post-activation email | `[$order_id, $channels]` |
| `wctlgm_send_invite_links_email` | Scheduled: send direct invite email (5s delay) | `[$order_id, $channels]` |

### Filters

| Filter | Purpose | Parameters |
|--------|---------|------------|
| `wctlgm_activation_info_output` | Customize activation info HTML in order details | `$output`, `$activation_code` |

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `WCTLGM_SML_PLUGIN_BASE` | `plugin_basename(__FILE__)` | Plugin basename for hooks |
| `WCTLGM_SML_PLUGIN_DIR` | `plugin_dir_path(__FILE__)` | Plugin directory path |

## Pro Plugin Conflict Detection

Two checks prevent lite and pro from running simultaneously:

1. **On activation:** `wctlgm_subscriber_manager_lite_activation()` checks `active_plugins` for `wctlgm-subscriber-manager/wctlgm-subscriber-manager.php`. If found, deactivates self and calls `wp_die()` with error message.

2. **On `admin_init`:** `wctlgm_subscriber_manager_lite_check_for_pro_plugin()` checks `active_plugins`. If pro is active, deactivates lite and displays admin warning notice.
