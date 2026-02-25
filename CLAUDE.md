# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Agent Context

Detailed architecture docs, mermaid diagrams, testing guides, and coding conventions live in the **`agent-context/`** folder. Read these files before making significant changes:

- `agent-context/ARCHITECTURE.md` — Full project structure, class roles, options, meta keys, hooks
- `agent-context/CONVENTIONS.md` — Naming conventions, code style, patterns
- `agent-context/DATA-FLOW.md` — Mermaid diagrams for all major flows
- `agent-context/TESTING.md` — Test stack, coverage, writing patterns
- `agent-context/E2E-TESTING.md` — Semi-automated E2E testing playbook
- `agent-context/ROADMAP.md` — Development roadmap and current phase

When making changes that affect architecture, data flows, hooks, meta keys, or test infrastructure, update the relevant `agent-context/` files to keep them in sync.

## Project Overview

WordPress plugin (PHP) that integrates WooCommerce with Telegram to manage access to private Telegram channels/groups after purchase. This is the **lite** version — a pro version exists with additional features (multiple channels, subscription support, automation webhooks, member removal).

**Requirements:** WordPress 6.0+, WooCommerce, PHP with namespace support.

## Development Commands

```bash
# Install dependencies (Freemius SDK)
composer install

# Install production dependencies only (for builds)
composer install --no-dev --prefer-dist --optimize-autoloader
```

Test suite uses PHPUnit 9.6 with Brain\Monkey for unit tests and wp-phpunit for integration tests. No linter or Node.js build step configured. The only JS file (`assets/js/wctlgm-subscriber-manager-lite.js`) is plain jQuery — no transpilation needed.

```bash
# Run unit tests
composer test:unit

# Run integration tests (requires wp-env)
composer test:integration
```

## Architecture

**Namespace:** `Subscriber_Manager_Lite_for_Telegram`

**Entry point:** `wctlgm-subscriber-manager-lite.php` — initializes Freemius SDK, defines constants (`WCTLGM_SML_PLUGIN_BASE`, `WCTLGM_SML_PLUGIN_DIR`), registers activation/deactivation hooks, and instantiates the main class.

**Main class:** `Subscriber_Manager_Lite_WCTLGM` (`includes/class-subscriber-manager-lite-wctlgm.php`) — orchestrator that loads all dependencies and initializes handlers via WordPress hooks (`init`, `plugins_loaded`).

### Core Classes (all in `includes/`)

| Class | Role |
|-------|------|
| `Settings` | Admin settings page (Settings → Telegram Subscriber Manager), product/variation meta fields, AJAX handlers for webhook/channel setup |
| `API_Handler` | Telegram Bot API wrapper (`setWebhook`, `setCommands`, `getChatMember`, `approveChatJoinRequest`, etc.) |
| `Endpoint_Handler` | REST endpoint `POST /wp-json/wctlgm/v1/telegram-bot/` — validates webhook secret token, routes incoming Telegram updates |
| `Bot_Interaction_Handler` | Processes Telegram bot commands (`/start`, `/activate`, `/help`) and chat join requests |
| `Order_Handler` | Hooks into `woocommerce_order_status_changed` — generates activation codes or invite links depending on flow |
| `Subscriptions_Handler` | Core subscription logic — processes activation codes, validates join requests, generates channel invite links |
| `Email_Handler` | Registers custom WooCommerce email classes |
| `Logger` | Static logging utility wrapping `WC_Logger` |

### Email Classes (`includes/emails/`)
- `Activation_Email` — sent after bot activation with invite links
- `Invite_Links_Email` — sent directly post-purchase when activation flow is disabled

Templates live in `templates/emails/` (HTML) and `templates/emails/plain/` (plain text).

### Data Flow

1. Customer purchases simple or variable product → WooCommerce order created
2. `Order_Handler` intercepts order status change to processing/completed
3. For each order item, resolves the correct meta ID (variation ID for variable products, product ID for simple)
4. **If activation required:** generates 8-char activation code, stores as `_activation_code` order meta
5. **If activation disabled:** generates invite links immediately via Telegram API (using variation-level `_telegram_channel_ids`)
6. Customer receives email (activation code or direct invite links)
7. With activation flow: customer sends code to Telegram bot → `Bot_Interaction_Handler` validates → `Subscriptions_Handler` generates invite links → email sent

### Key WordPress Options

- `wctlgm_bot_token` — Telegram bot token
- `wctlgm_secret_token` — webhook validation secret (32 chars)
- `wctlgm_channels` — array of channel/group configurations
- `wctlgm_require_activation_flow` — toggle activation step
- `wctlgm_allow_external_invites` — allow invites without join request validation

### Key Order/Product Meta

- Order: `_activation_code`, `_telegram_user_id`, `_channel_invite_{channel_id}`
- Product/Variation: `_telegram_channel_ids` (array of channel IDs product/variation grants access to; stored per-variation for variable products)

## Conventions

- All class files follow the pattern `class-subscriber-manager-lite-wctlgm-{name}.php`
- Classes use the `Subscriber_Manager_Lite_for_Telegram` namespace
- Class names are prefixed with `Subscriber_Manager_Lite_WCTLGM_`
- WordPress coding standards: tabs for indentation, spaces inside parentheses in function calls
- Input sanitization uses `sanitize_text_field()`, `sanitize_url()`, `sanitize_text_field() === 'yes'` for checkboxes
- All admin actions check `current_user_can('manage_options')`
- Freemius SDK handles free/pro versioning — pro-only features are gated with `wctlgm_fs()->is_plan('pro')` or similar checks

## Deployment

- **GitHub Actions** builds a zip on release creation (`build-release.yml`)
- **WordPress.org deployment** via `push-deploy.yml` (manual trigger, uses 10up deploy action)
- `.distignore` controls what's excluded from distribution packages
