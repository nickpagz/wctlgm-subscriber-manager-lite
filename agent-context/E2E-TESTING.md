# E2E Testing Playbook

> **Version:** 1.7.0 | **Last updated:** 2026-02-25 | **Last test run:** 2026-02-25

## Purpose

End-to-end testing on a live staging site. Validates webhook flows, Telegram API timing, email delivery, WooCommerce integration, and admin UI behavior for the lite plugin.

This playbook is simpler than the pro version's — no subscription rounds, no expiry, no automatic removal. Variable products are supported with per-variation channel settings.

Claude Code automates browser operations (Playwright), backend verification (WP-CLI via SSH), and Telegram webhook simulation (curl). Most Telegram interactions are automated via webhook simulation and Bot API calls.

### Automation Levels

Steps in each test are tagged with one of:

- `[AUTO]` — Fully automated by Claude (WP-CLI, curl)
- `[PLAYWRIGHT]` — Browser automation via Playwright MCP
- `[MANUAL]` — Requires user action in the Telegram app (rare)

## Environment

| Setting | Value |
|---------|-------|
| Site URL | `https://wctlgm-manager-test.mystagingwebsite.com/` |
| SSH alias | `wctlgm-test` (Pressable staging, uses `ssh.atomicsites.net` proxy) |
| Hosting | Pressable |
| WP path | `/srv/htdocs/` |
| Debug log | `/srv/htdocs/wp-content/debug.log` |
| Plugin logs | `/srv/htdocs/wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite-*.log` |
| Browser automation | Playwright MCP (headed mode, configured in `.mcp.json`) |
| Bot URL | `http://t.me/wctlgmBot` |
| Channels | "Testing 2" (`-1002483210080`), "Testing 1" (`-1002380644133`) |

## Tools

### Playwright MCP

Configured in `.mcp.json`. Used for checkout flows, admin panel navigation, order verification. Runs headed so the user can observe browser interactions.

### WP-CLI via SSH

All backend commands run through `ssh wctlgm-test "COMMAND"`. Suppress deprecation noise by piping through `grep -v "Deprecated:"` when needed.

## WP-CLI Reference Commands

```bash
# Suppress deprecation warnings from woo-order-test plugin
alias wpcli='ssh wctlgm-test'

# Options
wpcli "wp option get wctlgm_require_activation_flow"
wpcli "wp option update wctlgm_require_activation_flow 1"    # enable
wpcli "wp option update wctlgm_require_activation_flow ''"   # disable

# Order meta inspection
wpcli "wp eval '
\$order = wc_get_order(ORDER_ID);
echo \"Status: \" . \$order->get_status() . PHP_EOL;
echo \"Activation: \" . \$order->get_meta(\"_activation_code\", true) . PHP_EOL;
echo \"User ID: \" . \$order->get_meta(\"_telegram_user_id\", true) . PHP_EOL;
'"

# Check for invite link meta on an order
wpcli "wp eval '
\$order = wc_get_order(ORDER_ID);
\$meta = \$order->get_meta_data();
foreach (\$meta as \$m) {
    if (strpos(\$m->key, \"_channel_invite_\") === 0 || \$m->key === \"_activation_code\" || \$m->key === \"_telegram_user_id\") {
        echo \$m->key . \" = \" . print_r(\$m->value, true) . PHP_EOL;
    }
}
'"

# Log checking
wpcli "tail -30 /srv/htdocs/wp-content/debug.log"
wpcli "ls -t /srv/htdocs/wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite-*.log | head -1 | xargs tail -30"

# Cleanup
wpcli "wp eval 'wp_trash_post(ORDER_ID);'"
wpcli "wp cache flush && wp transient delete --all"
```

## Session Setup

Run once at the start of each E2E testing session. These values are used by the automation helper commands below.

```bash
# Retrieve tokens (suppress Pressable deprecation warnings)
BOT_TOKEN=$(ssh wctlgm-test "wp option get wctlgm_bot_token" 2>&1 | grep -v "Deprecated:")
SECRET_TOKEN=$(ssh wctlgm-test "wp option get wctlgm_secret_token" 2>&1 | grep -v "Deprecated:")

# Constants
TELEGRAM_USER_ID=6783520892
SITE_URL="https://wctlgm-manager-test.mystagingwebsite.com"
WEBHOOK_URL="${SITE_URL}/wp-json/wctlgm/v1/telegram-bot/"
CHANNEL_1="-1002483210080"   # Testing 2
CHANNEL_2="-1002380644133"   # Testing 1

# Verify
echo "BOT_TOKEN: ${BOT_TOKEN:0:5}..."
echo "SECRET_TOKEN: ${SECRET_TOKEN:0:5}..."
```

## Automation Helper Commands

Reusable `curl` templates for automating Telegram interactions. Replace placeholder values with actual values from each test.

### Remove User from Channel

```bash
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/unbanChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_ID}\", \"user_id\": ${TELEGRAM_USER_ID}}"
```

### Verify Channel Membership

```bash
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/getChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_ID}\", \"user_id\": ${TELEGRAM_USER_ID}}"
```

### Simulate Join Request Webhook

```bash
curl -s -X POST "${WEBHOOK_URL}" \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: ${SECRET_TOKEN}" \
  -d "{
    \"chat_join_request\": {
      \"chat\": {\"id\": \"${CHANNEL_ID}\", \"type\": \"supergroup\"},
      \"from\": {\"id\": ${TELEGRAM_USER_ID}, \"first_name\": \"TestUser\"},
      \"invite_link\": {\"invite_link\": \"${INVITE_LINK}\"}
    }
  }"
```

> **Expected log behavior:** The plugin will log Telegram API errors for `approveChatJoinRequest` and `revokeChatInviteLink` — this is expected because no real join request exists on Telegram's side. The plugin's internal state (`_telegram_user_id` on the order) is set correctly regardless.

### Simulate /start Activation Deep Link

The lite plugin generates deep links as `t.me/wctlgmBot?start=CODE` (plain code, no prefix). Telegram sends `/start CODE` to the bot. The bot handler accepts both plain codes and `activate_` prefixed codes via regex `(?:activate[._])?([A-Za-z0-9_-]{4,64})`.

```bash
curl -s -X POST "${WEBHOOK_URL}" \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: ${SECRET_TOKEN}" \
  -d "{
    \"message\": {
      \"chat\": {\"id\": \"${TELEGRAM_USER_ID}\", \"type\": \"private\"},
      \"from\": {\"id\": ${TELEGRAM_USER_ID}, \"first_name\": \"TestUser\"},
      \"text\": \"/start ${ACTIVATION_CODE}\",
      \"entities\": [{\"type\": \"bot_command\", \"offset\": 0, \"length\": 6}]
    }
  }"
```

> **Note:** The pro plugin uses `activate_` prefixed deep links (`?start=activate_CODE`). The lite plugin uses plain codes (`?start=CODE`). Both formats work with the lite bot handler, but simulations should match the actual deep link format.

## Pre-Session Checklist

| # | Check | Command | Expected |
|---|-------|---------|----------|
| 1 | SSH works | `ssh wctlgm-test "wp --version"` | WP-CLI output |
| 2 | Bot token | `wp option get wctlgm_bot_token` | Non-empty |
| 3 | Bot URL | `wp option get wctlgm_bot_url` | `http://t.me/wctlgmBot` |
| 4 | Secret token | `wp option get wctlgm_secret_token` | Non-empty |
| 5 | Channels | `wp option get wctlgm_channels --format=json` | At least 1 channel |
| 6 | Lite plugin active | `wp plugin is-active wctlgm-subscriber-manager-lite` | Exit 0 |
| 7 | Pro plugin inactive | `wp plugin is-active wctlgm-subscriber-manager` | Exit 1 or not installed |
| 8 | WooCommerce active | `wp plugin is-active woocommerce` | Exit 0 |
| 9 | Test gateway | `wp plugin is-active woo-order-test` | Exit 0 |
| 10 | Products exist | Query for products with `_telegram_channel_ids` meta | At least 1 per needed type |
| 11 | Debug log clean | `tail -20 debug.log` | No fatal errors |

## Testing Matrix

| Round | Focus | Tests | Description |
|-------|-------|-------|-------------|
| 0 | Admin UI | 15 | Settings page, product panel (simple + variable), subscription type guards, order details, emails |
| 1 | Subscriber Flows | 5 | Direct invite and activation flow for simple and variable products |

## Admin URLs

| Page | URL Path |
|------|----------|
| Settings | `/wp-admin/options-general.php?page=wctlgm-settings` |
| Edit Product | `/wp-admin/post.php?post=PRODUCT_ID&action=edit` |
| Edit Order | `/wp-admin/admin.php?page=wc-orders&action=edit&id=ORDER_ID` |
| WooCommerce Emails | `/wp-admin/admin.php?page=wc-settings&tab=email` |

## Round 0: Admin UI Validation

### Test 0.1: Settings Page — Fields and Layout

**Navigate** `[PLAYWRIGHT]` to Settings > Telegram Subscriber Manager (`/wp-admin/options-general.php?page=wctlgm-settings`).

**Verify all fields present:**

| Field | Type | ID / Selector | Expected State |
|-------|------|--------------|----------------|
| Telegram Bot Token | password input | `#wctlgm_bot_token` | Populated (non-empty) |
| Telegram Bot URL | text input | `#wctlgm_bot_url` | Shows `http://t.me/wctlgmBot` |
| Allow External Invites | checkbox | `#wctlgm_allow_external_invites` | Present |
| Require Activation Step | checkbox | `#wctlgm_require_activation_flow` | Present |
| Channel row | table row | `#wctlgm_channels_table` | Single row with "Testing 2" name and ID |
| Set Webhook button | button | `#wctlgm_set_webhook_button` | Present, enabled (token exists) |
| Upsell text | text | — | "Need to add multiple channels or groups?" |
| Submit (Save) | submit button | `#submit` | Present |

### Test 0.2: Settings Page — Channel ID Fetch [PLAYWRIGHT + MANUAL]

**Step 1** `[PLAYWRIGHT]` — Click "Get ID" button.
- **Expected:** Message: "Please post a message in your Telegram channel or group and then edit it"

**Step 2** `[MANUAL]` — Edit a message in the Telegram channel.

**Step 3** `[PLAYWRIGHT]` — Click "Get ID" again.
- **Expected:** Channel ID field is populated.

### Test 0.3: Settings Page — Set Webhook

**Step 1** `[PLAYWRIGHT]` — Click "Set Webhook" button.
- **Expected:** Button text changes during AJAX call. Page reloads on completion.

**Step 2 — Verify webhook** `[AUTO]`
```bash
ssh wctlgm-test "wp option get wctlgm_webhook_clicked" 2>&1 | grep -v "Deprecated:"
```
- **Expected:** `1` (truthy)

### Test 0.4: Settings Page — Webhook Warning Notice

**Step 1 — Reset webhook flag** `[AUTO]`
```bash
ssh wctlgm-test "wp option update wctlgm_webhook_clicked ''"
```

**Step 2** `[PLAYWRIGHT]` — Navigate to any admin page (e.g., Dashboard).
- **Expected:** Warning notice visible: "Action Required: Please click the Set Webhook button..."
- **Expected:** Notice contains link to the settings page.

**Step 3 — Restore** `[AUTO]`
```bash
ssh wctlgm-test "wp option update wctlgm_webhook_clicked 1"
```

### Test 0.5: Product Data Panel — Tab Visibility

`[PLAYWRIGHT]` Edit a **simple** product (ID 61).

1. **Expected:** "Telegram Access" tab visible in product data panel.
2. Change product type dropdown to **Grouped product**.
   - **Expected:** "Telegram Access" tab disappears.
3. Change product type dropdown to **External/Affiliate product**.
   - **Expected:** "Telegram Access" tab disappears.
4. Change product type back to **Simple product**.
   - **Expected:** "Telegram Access" tab reappears.
5. Change product type to **Variable product**.
   - **Expected:** "Telegram Access" tab visible. Standard fields (`.wctlgm-standard-fields`) hidden, variable message (`.wctlgm-variable-message`) shown.

**If WooCommerce Subscriptions active:**
6. Change to **Simple subscription** → tab hides.
7. Change to **Variable subscription** → tab hides.

**If Flexible Subscriptions active:**
8. Change to **Flexible Subscription** → tab hides.
9. Change to **Flexible Variable Subscription** → tab hides.

### Test 0.6: Product Data Panel — Field Content

`[PLAYWRIGHT]` Navigate to edit simple product #61, click the "Telegram Access" tab.

| Field | Type | Selector | Expected |
|-------|------|----------|----------|
| Telegram Channels | multi-select | `#telegram_channel_ids` | "Testing 2" selected |
| Upsell text | text | — | "Need to set access expiry? Automatic user removal?" |
| Help link | link | — | "Need Help?" documentation link present |

### Test 0.7: Product Data Panel — Save Meta

`[PLAYWRIGHT]` Edit product #61, change the channel selection, click "Update".

**Verify** `[AUTO]`:
```bash
ssh wctlgm-test "wp post meta get 61 _telegram_channel_ids --format=json" 2>&1 | grep -v "Deprecated:"
```
- **Expected:** Updated channel ID saved correctly.

### Test 0.7a: Variable Product — Panel Message

`[PLAYWRIGHT]` Edit variable product #340 ("Test Product - Variable").

1. Click the "Telegram Access" tab.
   - **Expected:** Standard fields container (`.wctlgm-standard-fields`) is hidden.
   - **Expected:** Variable message (`.wctlgm-variable-message`) is shown.
2. Change product type to **Simple product**.
   - **Expected:** Standard fields shown, variable message hidden.

### Test 0.7b: Variable Product — Variation Channel Select

`[PLAYWRIGHT]` Edit variable product #340, expand a variation panel.

**Verify variation-level Telegram fields present:**

| Field | Type | Selector Pattern | Expected |
|-------|------|-----------------|----------|
| Telegram Channels | multi-select | `.wctlgm-variation-channel-select` | Present, shows configured channels |
| Pro upsell | link | — | "Need to set access expiry?" link present |

Select a channel, save the product.

**Verify** `[AUTO]`:
```bash
ssh wctlgm-test "wp eval '
\$variation_id = VARIATION_ID;
\$channel_ids = get_post_meta(\$variation_id, \"_telegram_channel_ids\", true);
echo \"Channel IDs: \" . print_r(\$channel_ids, true) . PHP_EOL;
'" 2>&1 | grep -v "Deprecated:"
```
- **Expected:** `_telegram_channel_ids` saved on the variation post, not the parent.

### Test 0.7c: Variable Product — New Variation

`[PLAYWRIGHT]` Add a new variation to variable product #340.

1. Verify Telegram channel select appears on the new variation.
2. Verify Select2 is initialized on the new select element.

### Test 0.7d: Variable Product — Variation Fields Hidden for Subscription Types

This test activates subscription plugins via WP-CLI to verify the Telegram Access tab and variation fields are hidden for subscription product types. Both WooCommerce Subscriptions and Flexible Subscriptions are installed (inactive) on the staging site.

> **Important:** WCS and FSub cannot be active simultaneously — FSub includes a WCS compatibility shim that causes class name collisions. Always deactivate one before activating the other.

**Sub-test 0.7d-i: WooCommerce Subscriptions**

**Step 1 — Activate WCS** `[AUTO]`
```bash
ssh wctlgm-test "wp plugin activate woocommerce-subscriptions" 2>&1 | grep -v "Deprecated:"
```

**Step 2** `[PLAYWRIGHT]` — Edit variable product #340. Reload the page after plugin activation.

1. Click the Variations tab and expand a variation → verify "Telegram Access" section with channel select is visible.
2. Switch product type to "Variable subscription" → verify:
   a. "Telegram Access" tab is hidden
   b. Telegram channel select fields inside each variation are hidden
3. Switch back to "Variable product" → verify:
   a. "Telegram Access" tab reappears
   b. Telegram channel select fields inside variations reappear after variations reload

**Step 3 — Deactivate WCS** `[AUTO]`
```bash
ssh wctlgm-test "wp plugin deactivate woocommerce-subscriptions" 2>&1 | grep -v "Deprecated:"
```

**Sub-test 0.7d-ii: Flexible Subscriptions**

**Step 1 — Activate FSub** `[AUTO]`
```bash
ssh wctlgm-test "wp plugin activate flexible-subscriptions" 2>&1 | grep -v "Deprecated:"
```

**Step 2** `[PLAYWRIGHT]` — Edit variable product #340. Reload the page after plugin activation.

1. Click the Variations tab and expand a variation → verify "Telegram Access" section with channel select is visible.
2. Switch product type to "Flexible Variable Subscription" → verify:
   a. "Telegram Access" tab is hidden
   b. Telegram channel select fields inside each variation are hidden
3. Switch back to "Variable product" → verify:
   a. "Telegram Access" tab reappears
   b. Telegram channel select fields inside variations reappear after variations reload

**Step 3 — Deactivate FSub** `[AUTO]`
```bash
ssh wctlgm-test "wp plugin deactivate flexible-subscriptions" 2>&1 | grep -v "Deprecated:"
```

### Test 0.8: Order Details — Direct Invite Display

> **Note:** The lite plugin does NOT have an admin order metabox. Invite links and activation codes are only displayed on the frontend via the `woocommerce_order_details_before_order_table` hook (order confirmation page and My Account > View Order). This is by design — the pro plugin adds the admin metabox. Tests 0.8 and 0.9 only verify frontend display.

**Step 1** `[AUTO]` — Ensure activation flow is disabled:
```bash
ssh wctlgm-test "wp option update wctlgm_require_activation_flow ''"
```

**Step 2** `[PLAYWRIGHT]` — Place an order via checkout for simple product #61 (with test payment gateway).

**Step 3** `[PLAYWRIGHT]` — Navigate to My Account > Orders > View the order (frontend).
- **Expected:** "Telegram Access" section visible with invite link(s) and "Join" link.

**Step 4** `[PLAYWRIGHT]` — Navigate to admin order edit page.
- **Expected:** No Telegram metabox in admin (lite plugin only shows data on frontend).

### Test 0.9: Order Details — Activation Flow Display

**Step 1** `[AUTO]`:
```bash
ssh wctlgm-test "wp option update wctlgm_require_activation_flow 1"
```

**Step 2** `[PLAYWRIGHT]` — Place an order via checkout.

**Step 3** `[PLAYWRIGHT]` — Navigate to My Account > Orders > View the order (frontend).
- **Expected:** "Telegram Activation Code" heading visible.
- **Expected:** 8-character activation code displayed.
- **Expected:** Bot deep link displayed (contains `t.me/wctlgmBot?start=`).

**Step 4** `[PLAYWRIGHT]` — Navigate to admin order edit page.
- **Expected:** No Telegram metabox in admin (lite plugin only shows data on frontend).

### Test 0.10: Order Details — Pending Order Display

`[PLAYWRIGHT]` View a pending/on-hold order on the frontend (My Account > Orders).

- **With activation flow enabled:** "Telegram access activation details will be emailed and available in your dashboard when payment is received."
- **With activation flow disabled:** "Invite links will be emailed and available in your dashboard after payment processing is complete."

### Test 0.11: WooCommerce Email Settings

`[PLAYWRIGHT]` Navigate to WooCommerce > Settings > Emails.

- **Expected:** "Telegram Channel Activation" email listed.
- **Expected:** "Telegram Invite Links" email listed.

Click into each email to verify it has:
- Enable/disable toggle
- Subject and heading fields

### Round 0 Cleanup

```bash
# Restore activation flow to disabled
ssh wctlgm-test "wp option update wctlgm_require_activation_flow ''"
# Restore webhook flag
ssh wctlgm-test "wp option update wctlgm_webhook_clicked 1"
# Trash any test orders created during Round 0
ssh wctlgm-test "wp eval 'wp_trash_post(ORDER_ID);'"
# Clear caches
ssh wctlgm-test "wp cache flush && wp transient delete --all"
```

## Round 1: Subscriber Flows

### Test 1.1: Direct Invite Flow — Simple Product

**Step 1 — Configure** `[AUTO]`
```bash
ssh wctlgm-test "wp option update wctlgm_require_activation_flow ''"
```

**Step 2 — Checkout** `[PLAYWRIGHT]`
1. Navigate to shop page
2. Add simple product #61 to cart
3. Go to checkout, fill billing details, select test payment method
4. Place order
5. Record the order ID from the thank-you page

**Step 3 — Verify order meta** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
\$meta = \$order->get_meta_data();
\$found_invite = false;
foreach (\$meta as \$m) {
    if (strpos(\$m->key, \"_channel_invite_\") === 0) {
        echo \"INVITE: \" . \$m->key . \" = \" . \$m->value . PHP_EOL;
        \$found_invite = true;
    }
}
echo \$found_invite ? \"PASS: Invite link(s) found\" : \"FAIL: No invite links\";
echo PHP_EOL;
echo \"Activation code: \" . (\$order->get_meta(\"_activation_code\", true) ?: \"(none)\") . PHP_EOL;
'"
```
- **Expected:** `_channel_invite_*` meta exists with valid `https://t.me/+` link, no `_activation_code`

**Step 4 — Check logs** `[AUTO]`
```bash
ssh wctlgm-test "tail -20 /srv/htdocs/wp-content/debug.log" 2>&1 | grep -v "Deprecated:"
ssh wctlgm-test "ls -t /srv/htdocs/wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite-*.log | head -1 | xargs tail -20"
```

**Step 5 — Verify My Account order view** `[PLAYWRIGHT]`
Navigate to My Account > Orders > View Order (frontend: `/my-account/view-order/ORDER_ID/`).
- **Expected:** "Telegram Access" section visible with invite link(s) and "Join" link.

**Step 6 — Simulate join request** `[AUTO]`
```bash
curl -s -X POST "${WEBHOOK_URL}" \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: ${SECRET_TOKEN}" \
  -d "{
    \"chat_join_request\": {
      \"chat\": {\"id\": \"${CHANNEL_1}\", \"type\": \"supergroup\"},
      \"from\": {\"id\": ${TELEGRAM_USER_ID}, \"first_name\": \"TestUser\"},
      \"invite_link\": {\"invite_link\": \"INVITE_LINK\"}
    }
  }"
```

**Step 7 — Verify join (order meta)** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
echo \"Telegram user ID: \" . (\$order->get_meta(\"_telegram_user_id\", true) ?: \"NOT SET\") . PHP_EOL;
'"
```
- **Expected:** `_telegram_user_id` is set to `6783520892`.

**Step 8 — Verify channel membership (Telegram API)** `[AUTO]`
```bash
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/getChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_1}\", \"user_id\": ${TELEGRAM_USER_ID}}"
```
- **With simulated join:** `status` will be `"left"` — the `approveChatJoinRequest` API call fails because no real pending join request exists on Telegram's side. Plugin internal state (`_telegram_user_id`) is still set correctly.
- **With real Telegram join:** `status` should be `"member"`. Use the "Open Invite Link via macOS" helper for optional real join testing.

### Test 1.2: Activation Flow — Simple Product

**Step 1 — Configure** `[AUTO]`
```bash
ssh wctlgm-test "wp option update wctlgm_require_activation_flow 1"
```

**Step 2 — Re-set webhook** `[PLAYWRIGHT]`
Navigate to settings page, click "Set Webhook".

**Step 3 — Checkout** `[PLAYWRIGHT]`
Same as Test 1.1: add simple product #61, checkout, place order, record order ID.

**Step 4 — Verify activation code** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
\$code = \$order->get_meta(\"_activation_code\", true);
echo \"Activation code: \" . (\$code ?: \"FAIL: No code\") . PHP_EOL;
echo (strlen(\$code) === 8 ? \"PASS: 8-char code\" : \"FAIL: Wrong length\") . PHP_EOL;
\$has_invite = false;
foreach (\$order->get_meta_data() as \$m) {
    if (strpos(\$m->key, \"_channel_invite_\") === 0) \$has_invite = true;
}
echo (\$has_invite ? \"FAIL: Invite link exists (should not yet)\" : \"PASS: No invite links yet\") . PHP_EOL;
'"
```

**Step 5 — Verify admin + frontend order pages** `[PLAYWRIGHT]`
Admin and My Account order pages should show activation code and bot deep link.

**Step 6 — Simulate activation deep link** `[AUTO]`
```bash
# Replace ACTIVATION_CODE with the code from Step 4
curl -s -X POST "${WEBHOOK_URL}" \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: ${SECRET_TOKEN}" \
  -d "{
    \"message\": {
      \"chat\": {\"id\": \"${TELEGRAM_USER_ID}\", \"type\": \"private\"},
      \"from\": {\"id\": ${TELEGRAM_USER_ID}, \"first_name\": \"TestUser\"},
      \"text\": \"/start ACTIVATION_CODE\",
      \"entities\": [{\"type\": \"bot_command\", \"offset\": 0, \"length\": 6}]
    }
  }"
```
- **Expected response:** JSON with `"text"` containing "Activation successful!" and invite link(s).

**Step 7 — Verify activation (order meta)** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
echo \"Activation code: \" . (\$order->get_meta(\"_activation_code\", true) ?: \"(deleted - PASS)\") . PHP_EOL;
echo \"Telegram user ID: \" . (\$order->get_meta(\"_telegram_user_id\", true) ?: \"NOT SET\") . PHP_EOL;
\$has_invite = false;
foreach (\$order->get_meta_data() as \$m) {
    if (strpos(\$m->key, \"_channel_invite_\") === 0) {
        echo \"INVITE: \" . \$m->key . \" = \" . \$m->value . PHP_EOL;
        \$has_invite = true;
    }
}
echo (\$has_invite ? \"PASS: Invite links generated\" : \"FAIL: No invite links\") . PHP_EOL;
'"
```
- **Expected:** `_activation_code` deleted, `_telegram_user_id` set, invite links generated.

**Step 8 — Verify channel membership (Telegram API)** `[AUTO]`
```bash
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/getChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_1}\", \"user_id\": ${TELEGRAM_USER_ID}}"
```
- **Expected:** See Test 1.1 Step 8 for simulated vs real join behavior.

**Step 9 — Verify My Account order view** `[PLAYWRIGHT]`
Navigate to `/my-account/view-order/ORDER_ID/`.
- **Expected (before activation):** "Telegram Activation Code" heading with code and bot deep link.
- **Expected (after activation):** "Telegram Activation Code" heading with "Activated" status.

### Test 1.3: External Invites — Allow/Deny

**Step 1** `[AUTO]` — Enable external invites:
```bash
ssh wctlgm-test "wp option update wctlgm_allow_external_invites 1"
```

**Step 2** `[AUTO]` — Simulate join request with unrecognized invite link:
```bash
curl -s -X POST "${WEBHOOK_URL}" \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: ${SECRET_TOKEN}" \
  -d "{
    \"chat_join_request\": {
      \"chat\": {\"id\": \"${CHANNEL_1}\", \"type\": \"supergroup\"},
      \"from\": {\"id\": ${TELEGRAM_USER_ID}, \"first_name\": \"TestUser\"},
      \"invite_link\": {\"invite_link\": \"https://t.me/+FakeInviteLink123\"}
    }
  }"
```
- **Expected:** Approved. Check plugin logs for external invite approval.

**Step 3** `[AUTO]` — Disable external invites:
```bash
ssh wctlgm-test "wp option delete wctlgm_allow_external_invites"
```

**Step 4** `[AUTO]` — Simulate same join request again.
- **Expected:** Denied. Check plugin logs for denial.

**Step 5** — Restore:
```bash
ssh wctlgm-test "wp option delete wctlgm_allow_external_invites"
```

### Test 1.4: Direct Invite Flow — Variable Product

**Step 1 — Configure** `[AUTO]`
```bash
ssh wctlgm-test "wp option update wctlgm_require_activation_flow ''"
```

**Step 2 — Checkout** `[PLAYWRIGHT]`
1. Navigate to the variable product page (ID 340, "Test Product - Variable")
2. Select variation "Small" (#341, has Testing 2 channel)
3. Add to cart, checkout, place order
4. Record the order ID

**Step 3 — Verify order meta** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
\$meta = \$order->get_meta_data();
\$found_invite = false;
foreach (\$meta as \$m) {
    if (strpos(\$m->key, \"_channel_invite_\") === 0) {
        echo \"INVITE: \" . \$m->key . \" = \" . \$m->value . PHP_EOL;
        \$found_invite = true;
    }
}
echo \$found_invite ? \"PASS: Invite link(s) found\" : \"FAIL: No invite links\";
echo PHP_EOL;
foreach (\$order->get_items() as \$item) {
    echo \"Product ID: \" . \$item->get_product_id() . \" | Variation ID: \" . \$item->get_variation_id() . PHP_EOL;
}
'"
```
- **Expected:** `_channel_invite_*` meta exists. Order item shows non-zero `variation_id`.

**Step 4 — Verify My Account order view** `[PLAYWRIGHT]`
Navigate to `/my-account/view-order/ORDER_ID/`.
- **Expected:** "Telegram Access" section visible with invite link(s).

**Step 5 — Simulate join request** `[AUTO]`
```bash
# Replace INVITE_LINK and CHANNEL_ID with actual values from order meta
curl -s -X POST "${WEBHOOK_URL}" \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: ${SECRET_TOKEN}" \
  -d "{
    \"chat_join_request\": {
      \"chat\": {\"id\": \"CHANNEL_ID\", \"type\": \"supergroup\"},
      \"from\": {\"id\": ${TELEGRAM_USER_ID}, \"first_name\": \"TestUser\"},
      \"invite_link\": {\"invite_link\": \"INVITE_LINK\"}
    }
  }"
```

**Step 6 — Verify join (order meta)** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
echo \"Telegram user ID: \" . (\$order->get_meta(\"_telegram_user_id\", true) ?: \"NOT SET\") . PHP_EOL;
'"
```
- **Expected:** `_telegram_user_id` set.

**Step 7 — Verify channel membership (Telegram API)** `[AUTO]`
```bash
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/getChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"CHANNEL_ID\", \"user_id\": ${TELEGRAM_USER_ID}}"
```
- **Expected:** See Test 1.1 Step 8 for simulated vs real join behavior.

### Test 1.5: Activation Flow — Variable Product

**Step 1 — Configure** `[AUTO]`
```bash
ssh wctlgm-test "wp option update wctlgm_require_activation_flow 1"
```

**Step 2 — Re-set webhook** `[AUTO/PLAYWRIGHT]`
Navigate to settings page, click "Set Webhook". Or verify webhook is already set via Telegram API.

**Step 3 — Checkout** `[PLAYWRIGHT]`
Add variable product #340 variation "Small" (#341), checkout, place order.

**Step 4 — Verify activation code** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
\$code = \$order->get_meta(\"_activation_code\", true);
echo \"Activation code: \" . (\$code ?: \"FAIL: No code\") . PHP_EOL;
echo (strlen(\$code) === 8 ? \"PASS: 8-char code\" : \"FAIL: Wrong length\") . PHP_EOL;
'"
```
- **Expected:** 8-character activation code, no invite links yet.

**Step 5 — Simulate activation deep link** `[AUTO]`
```bash
# Replace ACTIVATION_CODE with the code from Step 4
curl -s -X POST "${WEBHOOK_URL}" \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: ${SECRET_TOKEN}" \
  -d "{
    \"message\": {
      \"chat\": {\"id\": \"${TELEGRAM_USER_ID}\", \"type\": \"private\"},
      \"from\": {\"id\": ${TELEGRAM_USER_ID}, \"first_name\": \"TestUser\"},
      \"text\": \"/start ACTIVATION_CODE\",
      \"entities\": [{\"type\": \"bot_command\", \"offset\": 0, \"length\": 6}]
    }
  }"
```
- **Expected response:** JSON with "Activation successful!" and invite link for Testing 2 (variation #341's channel).

**Step 6 — Verify activation (order meta)** `[AUTO]`
```bash
ssh wctlgm-test "wp eval '
\$order = wc_get_order(ORDER_ID);
echo \"Activation code: \" . (\$order->get_meta(\"_activation_code\", true) ?: \"(deleted - PASS)\") . PHP_EOL;
echo \"Telegram user ID: \" . (\$order->get_meta(\"_telegram_user_id\", true) ?: \"NOT SET\") . PHP_EOL;
\$has_invite = false;
foreach (\$order->get_meta_data() as \$m) {
    if (strpos(\$m->key, \"_channel_invite_\") === 0) {
        echo \"INVITE: \" . \$m->key . \" = \" . \$m->value . PHP_EOL;
        \$has_invite = true;
    }
}
echo (\$has_invite ? \"PASS: Invite links generated\" : \"FAIL: No invite links\") . PHP_EOL;
'"
```
- **Expected:** `_activation_code` deleted, `_telegram_user_id` set, invite link(s) generated using variation-level channel IDs.

**Step 7 — Verify channel membership (Telegram API)** `[AUTO]`
```bash
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/getChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_1}\", \"user_id\": ${TELEGRAM_USER_ID}}"
```
- **Expected:** See Test 1.1 Step 8 for simulated vs real join behavior.

**Step 8 — Verify My Account order view** `[PLAYWRIGHT]`
Navigate to `/my-account/view-order/ORDER_ID/`.
- **Expected:** "Telegram Activation Code" heading with "Activated" status.

### Round 1 Cleanup

> **Note:** `unbanChatMember` is not a function in the lite plugin code, but it's available as a Telegram Bot API endpoint. We call it directly via curl to clean up test channel membership after testing. Without the `only_if_banned` parameter, it removes the user from the channel AND allows them to rejoin later via invite link.

```bash
# Remove user from all test channels (safe to call even if user isn't a member)
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/unbanChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_1}\", \"user_id\": ${TELEGRAM_USER_ID}}"
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/unbanChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_2}\", \"user_id\": ${TELEGRAM_USER_ID}}"

# Verify removal (optional — confirms cleanup worked)
curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/getChatMember" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CHANNEL_1}\", \"user_id\": ${TELEGRAM_USER_ID}}"
# Expected: status "left"

# Trash test orders (replace with actual order IDs from this session)
ssh wctlgm-test "wp db query \"UPDATE wp_wc_orders SET status='wc-trash' WHERE id IN (ORDER_ID_1, ORDER_ID_2, ORDER_ID_3, ORDER_ID_4)\"" 2>&1 | grep -v "Deprecated:"

# Restore activation flow to disabled
ssh wctlgm-test "wp option update wctlgm_require_activation_flow ''"
# Restore external invites
ssh wctlgm-test "wp option update wctlgm_allow_external_invites 1"
# Clear caches
ssh wctlgm-test "wp cache flush && wp transient delete --all" 2>&1 | grep -v "Deprecated:"
```

## Error Monitoring Protocol

After **every significant action** (checkout, status change, bot interaction):

```bash
# Check for PHP Fatal/Warning/Notice
ssh wctlgm-test "tail -10 /srv/htdocs/wp-content/debug.log" 2>&1 | grep -v "Deprecated:" | grep -iE "fatal|warning|notice|error" || echo "CLEAN"

# Check plugin log for errors
ssh wctlgm-test "ls -t /srv/htdocs/wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite-*.log | head -1 | xargs tail -10" 2>&1 | grep -v "Deprecated:" | grep -iE "error|fail|exception" || echo "CLEAN"
```

**If errors found:** STOP, report to user, do not continue until resolved.

## What Remains Manual

| Scenario | Why | When needed |
|----------|-----|-------------|
| One real Telegram join per session | Validates Telegram's webhook delivery end-to-end | Optional — can be skipped if webhook was recently verified |
| Email visual verification | Confirming email templates render correctly | Optional |
| Channel ID fetch | Requires editing a message in Telegram app | Only for Test 0.2 |

## Products Reference

| ID | Type | Name | Price | Channels |
|----|------|------|-------|----------|
| 61 | simple | Test Product - Simple | $19 | `-1002483210080` |
| 67 | simple | Test 2 Products - Simple | $29 | `-1002483210080` |
| 292 | simple | Multi-Channel Test - Simple | $25 | `-1002483210080`, `-1002380644133` |
| 340 | variable | Test Product - Variable | varies | Per-variation (see below) |

**Variable product #340:**
- Variation #341 (Small, $15) → channel `-1002483210080` (Testing 2)
- Variation #342 (Large, $20) → channel `-1002380644133` (Testing 1)

## State Tracking

When resuming an interrupted session, determine current state by running:

```bash
# Which plugins are active?
ssh wctlgm-test "wp plugin list --status=active --format=table" 2>&1 | grep -E "woocommerce-subscriptions|flexible-subscriptions|wctlgm"

# Is activation flow on or off?
ssh wctlgm-test "wp option get wctlgm_require_activation_flow" 2>&1 | grep -v "Deprecated:"

# Recent orders (check what tests have run)
ssh wctlgm-test "wp db query 'SELECT id, status, date_created_gmt FROM wp_wc_orders ORDER BY id DESC LIMIT 10'" 2>&1 | grep -v "Deprecated:"

# Recent plugin log entries
ssh wctlgm-test "ls -t /srv/htdocs/wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite-*.log 2>/dev/null | head -1 | xargs tail -30" 2>&1 | grep -v "Deprecated:"
```

Use this output to determine which round and test to resume from.

## Troubleshooting

### Admin Credentials

If browser cookies are cleared during testing, you'll need to log back in. Site login: `wctlgm-manager-test` / password provided at session start.

### HPOS (High Performance Order Storage)

The staging site uses HPOS. Orders are stored in the `wp_wc_orders` table, not `wp_posts`. Key implications:

- **Order meta** is in `wp_wc_orders_meta`, not `wp_postmeta`. Use `wp db query "SELECT meta_key, meta_value FROM wp_wc_orders_meta WHERE order_id=ORDER_ID"` for inspection.
- **Admin order URLs** use the HPOS format: `/wp-admin/admin.php?page=wc-orders&action=edit&id=ORDER_ID` (not `post.php?post=ORDER_ID`).
- **Trashing orders** requires direct SQL: `wp db query "UPDATE wp_wc_orders SET status='wc-trash' WHERE id=ORDER_ID"`. The `wp_trash_post()` function works only for `wp_posts`-based orders.
- **WC API** (`wc_get_order()`, `$order->get_meta()`) works transparently with HPOS.

### WooCommerce Block-Based Checkout

The staging site uses the WooCommerce block-based checkout (not the classic shortcode checkout). Key differences:

- Billing info may be pre-filled from previous orders
- The "Place Order" button is rendered by the checkout block
- Order summary loads asynchronously — wait for "Loading price..." to disappear before placing the order
- The checkout URL is `/checkout/` (same as classic)

### Cart Shortcuts

For simple products, bypass the product page by using: `?add-to-cart=PRODUCT_ID` (e.g., `https://site.com/?add-to-cart=61`). For variable products, navigate to the product page and select the variation.

### Cart Contamination

If a product with invalid data is added to cart, it may cause fatal errors on cart/checkout pages. Fix by clearing WC sessions and persistent cart:

```bash
ssh wctlgm-test "wp eval '
global \$wpdb;
\$wpdb->query(\"TRUNCATE TABLE \" . \$wpdb->prefix . \"woocommerce_sessions\");
delete_user_meta(1, \"_woocommerce_persistent_cart_1\");
echo \"Sessions truncated and persistent cart deleted\";
'"
```

Then clear browser cookies via Playwright:
```javascript
// In Playwright MCP browser_run_code:
async (page) => { await page.context().clearCookies(); }
```

### Subscription Plugins

Both WooCommerce Subscriptions (7.0.0) and Flexible Subscriptions (1.7.5) are installed but inactive on the staging site. They can be activated via WP-CLI for Test 0.7d. WCS and FSub cannot be active simultaneously (class name collision). Always deactivate one before activating the other:

```bash
ssh wctlgm-test "wp plugin activate woocommerce-subscriptions" 2>&1 | grep -v "Deprecated:"
ssh wctlgm-test "wp plugin deactivate woocommerce-subscriptions" 2>&1 | grep -v "Deprecated:"
ssh wctlgm-test "wp plugin activate flexible-subscriptions" 2>&1 | grep -v "Deprecated:"
ssh wctlgm-test "wp plugin deactivate flexible-subscriptions" 2>&1 | grep -v "Deprecated:"
```

### Simulated vs Real Telegram Joins

With webhook simulation, the plugin processes the `chat_join_request` payload and sets `_telegram_user_id` on the order meta. However, the actual Telegram API call to `approveChatJoinRequest` fails because no real pending join request exists. This means:

- **`_telegram_user_id` on order meta** — always set correctly (written before API call)
- **`getChatMember` result** — shows `"left"` (user not actually added to channel)
- **Plugin logs** — will show API errors for `approveChatJoinRequest` and `revokeChatInviteLink` (expected)
- **`unbanChatMember` in cleanup** — safe to call regardless (no-op if user isn't a member)

For full end-to-end Telegram verification, use the "Open Invite Link via macOS" helper to perform a real join, then verify with `getChatMember`.

### Deprecated Warnings from woo-order-test

The `woo-order-test` payment gateway plugin generates PHP deprecated notices about dynamic property creation. These are unrelated to our plugin and can be suppressed with `grep -v "Deprecated:"` or ignored.

## Test Run History

### v1.7.0 — 2026-02-25

**Result:** 12 PASS, 0 FAIL, 3 SKIP, 3 DEFERRED across 2 rounds.

| Test | Description | Result | Notes |
|------|-------------|--------|-------|
| 0.1 | Settings Page — Fields and Layout | PASS | Bot token, bot URL, checkboxes, channels table, webhook button all present |
| 0.2 | Settings Page — Channel ID Fetch | SKIPPED | Requires manual Telegram message editing |
| 0.3 | Settings Page — Set Webhook | PASS | Webhook set, `wctlgm_webhook_clicked` option confirmed |
| 0.4 | Settings Page — Webhook Warning Notice | PASS | Notice appeared after clearing flag, contained link to settings |
| 0.5 | Product Data Panel — Tab Visibility | PASS | Tab shows/hides correctly per product type (simple, grouped, external, variable) |
| 0.6 | Product Data Panel — Field Content | PASS | Channel select, upsell text, help link all present on simple product #61 |
| 0.7 | Product Data Panel — Save Meta | SKIPPED | Tested implicitly via variation save in 0.7b |
| 0.7a | Variable Product — Panel Message | PASS | Standard fields hidden, variable message shown for variable type |
| 0.7b | Variable Product — Variation Channel Select | PASS | Variation-level select with channels, Select2 initialized |
| 0.7c | Variable Product — New Variation | PASS | New variation #344 had Telegram fields, cleaned up after |
| 0.7d | Variable Product — Subscription Type Guards | SKIPPED | Subscription plugins were inactive; **now updated to activate via WP-CLI** |
| 0.8 | Order Details — Direct Invite Display | DEFERRED | Covered by Test 1.1 (order #346); confirmed no admin metabox in lite |
| 0.9 | Order Details — Activation Flow Display | DEFERRED | Covered by Test 1.2 (order #347) |
| 0.10 | Order Details — Pending Order Display | DEFERRED | No pending orders available; create during future test run |
| 0.11 | WooCommerce Email Settings | PASS | Both email classes registered and configurable |
| 1.1 | Direct Invite — Simple Product (Order #346) | PASS | Invite link generated, frontend display correct, join simulated |
| 1.2 | Activation Flow — Simple Product (Order #347) | PASS | Code `7KmYdIs1`, activation via `/start` successful, invite link generated |
| 1.3 | External Invites — Allow/Deny | PASS | Approved when enabled, denied when disabled |
| 1.4 | Direct Invite — Variable Product (Order #348) | PASS | Variation #341 (Small), invite link for Testing 2 |
| 1.5 | Activation Flow — Variable Product (Order #349) | PASS | Code `hxz2XkxW`, activation successful, variation-level channel IDs used |

**Key observations:**
- HPOS is enabled on staging — all order meta queries use `wp_wc_orders_meta` table
- Lite plugin has no admin order metabox (frontend display only via `woocommerce_order_details_before_order_table`)
- WooCommerce block-based checkout is active (billing info pre-filled, async order summary)
- Both subscription plugins (WCS 7.0.0, FSub 1.7.5) are installed but inactive
- `getChatMember` verification was not run during this session — **added to playbook for future runs**
- `unbanChatMember` cleanup was not run during this session — **added to playbook for future runs**
- Debug log clean throughout (only unrelated deprecated notices from woo-order-test plugin)
- All test orders (346-349) trashed during cleanup
