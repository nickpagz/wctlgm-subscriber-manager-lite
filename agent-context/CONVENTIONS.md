# Coding Conventions & Patterns

> **Version:** 1.6.0 | **Last updated:** 2026-02-24

## Naming Conventions

| Item | Convention | Example |
|------|-----------|---------|
| Class files | `class-subscriber-manager-lite-wctlgm-{name}.php` | `class-subscriber-manager-lite-wctlgm-order-handler.php` |
| Class names | `Subscriber_Manager_Lite_WCTLGM_{Name}` | `Subscriber_Manager_Lite_WCTLGM_Order_Handler` |
| Namespace | `Subscriber_Manager_Lite_for_Telegram` | `namespace Subscriber_Manager_Lite_for_Telegram;` |
| Hooks/filters prefix | `wctlgm_` | `wctlgm_send_activation_email` |
| Text domain | `wctlgm-subscriber-manager-lite` | `__('text', 'wctlgm-subscriber-manager-lite')` |
| Meta key prefix | `_telegram_` or `_channel_invite_` | `_telegram_channel_ids`, `_channel_invite_-100123` |
| Option prefix | `wctlgm_` | `wctlgm_bot_token` |
| Constants prefix | `WCTLGM_SML_` | `WCTLGM_SML_PLUGIN_DIR` |
| JS script handle | `subscriber-manager-lite-js` | `wp_enqueue_script('subscriber-manager-lite-js', ...)` |
| Test files | `{ClassName}Test.php` (proposed) | `SubscriptionsHandlerTest.php` |

## Code Style

- **PHP:** WordPress coding standards — tabs for indentation, spaces inside parentheses, Yoda conditions
- **JS:** Vanilla jQuery, tab-indented, no build step or transpilation
- **No linting tools** configured (no PHPCS, no ESLint)
- **No autoloader** — classes manually required in dependency order via `require_once`
- **Uses PHP namespaces** — all classes are under `Subscriber_Manager_Lite_for_Telegram` (unlike the pro version which uses plain class names)
- **Security patterns:**
  - Input: `sanitize_text_field()`, `sanitize_url()`, `esc_url_raw()`
  - Output: `esc_html()`, `esc_url()`, `esc_attr()`
  - Nonces: `wp_verify_nonce()` for form submissions, `wp_create_nonce()` for AJAX
  - Capability checks: `current_user_can('manage_options')`

## Architectural Patterns

### No Factory Pattern

Unlike the pro version, the lite plugin uses a **single `Subscriptions_Handler` class** instantiated directly wherever needed. There is no interface, no factory, no handler hierarchy. This is intentional — the lite version only supports simple products.

### Instance-Based Handlers

`Subscriptions_Handler` and `Bot_Interaction_Handler` create their own `API_Handler` and `Logger` instances in their constructors. `Settings` also creates a `Logger` instance.

### Static + Instance Methods

| Class | Pattern |
|-------|---------|
| `Order_Handler` | Static `init()` + all static methods |
| `Email_Handler` | Static `init()` + static `add_email_classes()` |
| `Bot_Interaction_Handler` | Static `init()` for hook registration; instance methods for request processing |
| `Subscriptions_Handler` | Instance methods only (no static init) |
| `Settings` | Instance methods only (created via `new` in orchestrator) |
| `Logger` | Has static methods but is also instantiated as instance property in other classes |

### Action Scheduler

Deferred tasks use WooCommerce's Action Scheduler:

| Action | Delay | Trigger |
|--------|-------|---------|
| `wctlgm_send_invite_links_email` | 5 seconds | After invite links generated (direct flow) |
| `wctlgm_send_activation_email` | Immediate (`time()`) | After activation code processed |

### Single Channel Enforcement

The Settings class enforces a single channel:
- `sanitize_channels()` only processes `$input[0]`, returns array with one entry
- `custom_channels_input()` renders a single row without add/remove buttons
- Upsell message directs to `wctlgm_fs()->get_upgrade_url()` for multi-channel

## File Dependencies (Load Order)

```
1. Logger                    — loaded in main plugin file (before orchestrator)
2. Subscriber_Manager_Lite_WCTLGM  — loaded in main plugin file
3. Inside orchestrator load_dependencies():
   3a. Settings
   3b. Subscriptions_Handler
   3c. Order_Handler
   3d. API_Handler
   3e. Bot_Interaction_Handler
   3f. Endpoint_Handler
   3g. Email_Handler
4. Email classes (loaded lazily via woocommerce_email_classes filter):
   4a. Activation_Email
   4b. Invite_Links_Email
```

## Product Type Checking

In lite, product type checking is hardcoded to `'simple'`:

```php
$product->is_type( 'simple' )
```

This is correct for the lite version. The pro version uses `Factory::get_all_supported_product_types()` to support multiple product types. If/when lite adds support for new product types, this pattern must be updated throughout `Order_Handler` and `Settings`.

The product data tab uses CSS classes `show_if_simple` and `hide_if_subscription` to control visibility.

## Channel Invite Meta Keys

Invite links are stored using indexed meta keys:

```
_channel_invite_{channel_id} → invite link URL
```

Example: `_channel_invite_-1001234567890` → `https://t.me/+abc123`

This matches the pro version's convention.

## Branch & Release

- **Main branch:** `trunk`
- **Feature branches:** `feature/*` or descriptive names
- **Releases:** Created via GitHub releases, built by `.github/workflows/build-release.yml`
- **WordPress.org deployment:** Manual trigger via `.github/workflows/push-deploy.yml` (uses 10up deploy action)
- **Version bumps:** Update both `wctlgm-subscriber-manager-lite.php` (plugin header) and `README.txt` (Stable tag)
- **Distribution exclusions:** Controlled by `.distignore` (excludes `.github`, `agent-context`, tests, dev files)

## Freemius Integration

| Setting | Value |
|---------|-------|
| SDK ID | `16907` |
| Plugin slug | `wctlgm-subscriber-manager-lite` |
| Premium slug | `wctlgm-subscriber-manager` |
| `is_premium` | `false` |
| `has_premium_version` | `true` |
| Menu parent | `options-general.php` |
| Menu slug | `wctlgm-settings` |

**Upsell patterns:**
- `wctlgm_fs()->get_upgrade_url()` — used in settings page and product data panel
- Settings page single channel row shows "Need to add multiple channels or groups?"
- Product panel shows "Need to set access expiry? Automatic user removal? Works with subscriptions?"
