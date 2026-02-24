# E2E Testing Playbook

> **Version:** 1.6.0 | **Last updated:** 2026-02-24

## Purpose

End-to-end testing on a live staging site. Validates webhook flows, Telegram API timing, email delivery, WooCommerce integration, and admin UI behavior for the lite plugin.

This playbook is simpler than the pro version's — no subscription rounds, no variable products, no expiry, no automatic removal.

## Automation Levels

Tests are tagged by automation level:

- **[AUTO]** — Fully automatable via WP-CLI and curl
- **[PLAYWRIGHT]** — Automatable via browser automation (Playwright MCP)
- **[MANUAL]** — Requires human interaction with Telegram app

## Environment

> **TODO:** Configure staging site details when E2E testing environment is established.

| Item | Value |
|------|-------|
| Staging URL | *TBD* |
| SSH alias | *TBD* |
| Hosting | *TBD* |
| Bot username | *TBD* |

**Tools required:**
- **Playwright MCP** — Browser automation for checkout and admin pages
- **WP-CLI via SSH** — Backend commands for option/meta inspection and log checking

## WP-CLI Reference Commands

```bash
# Check plugin status
wp plugin list --status=active --name=wctlgm-subscriber-manager-lite

# Read options
wp option get wctlgm_bot_token
wp option get wctlgm_bot_url
wp option get wctlgm_secret_token
wp option get wctlgm_channels --format=json
wp option get wctlgm_require_activation_flow
wp option get wctlgm_allow_external_invites
wp option get wctlgm_webhook_clicked

# Set options
wp option update wctlgm_require_activation_flow 1
wp option update wctlgm_require_activation_flow 0

# Order meta inspection
wp post meta get <order_id> _activation_code
wp post meta get <order_id> _telegram_user_id
wp post meta list <order_id> --keys=_channel_invite_*

# Product meta
wp post meta get <product_id> _telegram_channel_ids --format=json

# Check debug log
tail -50 wp-content/debug.log

# Check plugin log (WooCommerce logs)
ls -la wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite*
tail -50 wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite-*.log
```

## Pre-Session Checklist

| # | Check | Command | Expected |
|---|-------|---------|----------|
| 1 | SSH works | `ssh <alias> wp option get siteurl` | Site URL output |
| 2 | Bot token set | `wp option get wctlgm_bot_token` | Non-empty string |
| 3 | Bot URL set | `wp option get wctlgm_bot_url` | `https://t.me/...` |
| 4 | Secret token set | `wp option get wctlgm_secret_token` | 32-char string |
| 5 | Channel configured | `wp option get wctlgm_channels --format=json` | Array with 1 entry (name + id) |
| 6 | Lite plugin active | `wp plugin is-active wctlgm-subscriber-manager-lite` | Exit 0 |
| 7 | Pro plugin inactive | `wp plugin is-active wctlgm-subscriber-manager` | Exit 1 or not installed |
| 8 | WooCommerce active | `wp plugin is-active woocommerce` | Exit 0 |
| 9 | Test product exists | `wp post meta get <product_id> _telegram_channel_ids` | Non-empty array |
| 10 | Debug log clean | `tail -5 wp-content/debug.log` | No fatal errors |

## Testing Matrix

| Round | Focus | Tests | Description |
|-------|-------|-------|-------------|
| 0 | Admin UI | 11 | Settings page, product panel, order details, emails |
| 1 | Subscriber Flows | 3 | Direct invite and activation flow for simple products |

## Round 0: Admin UI Validation

### Test 0.1: Settings Page — Fields and Layout [PLAYWRIGHT]

**Steps:**
1. Navigate to Settings > Telegram Subscriber Manager
2. Verify all fields present: Bot Token (password), Bot URL (text), Allow External Invites (checkbox), Require Activation Step (checkbox)
3. Verify single channel input row with Name, ID, and "Get ID" button
4. Verify "Set Webhook" button (disabled if no bot token)
5. Verify upsell text: "Need to add multiple channels or groups?"
6. Verify Save Changes button

**Expected:** All fields render correctly with current saved values.

### Test 0.2: Settings Page — Channel ID Fetch [PLAYWRIGHT + MANUAL]

**Steps:**
1. Click "Get ID" button
2. Verify message: "Please post a message in your Telegram channel or group and then edit it"
3. [MANUAL] Edit a message in the Telegram channel
4. Click "Get ID" again
5. Verify channel ID field is populated

**Expected:** Channel ID appears in the field after editing a message.

### Test 0.3: Settings Page — Set Webhook [PLAYWRIGHT]

**Steps:**
1. Click "Set Webhook" button
2. Verify success message
3. Check `wp option get wctlgm_webhook_clicked` = true

**Expected:** Webhook set successfully, option updated.

### Test 0.4: Settings Page — Webhook Warning Notice [PLAYWRIGHT + AUTO]

**Steps:**
1. `wp option delete wctlgm_webhook_clicked`
2. Navigate to any wp-admin page
3. Verify warning notice: "Please click the Set Webhook button"
4. Restore: `wp option update wctlgm_webhook_clicked 1`

**Expected:** Warning appears when webhook not set, disappears when set.

### Test 0.5: Product Data Panel — Tab Visibility [PLAYWRIGHT]

**Steps:**
1. Edit a simple product → verify "Telegram Access" tab visible
2. If WCS is active: verify tab hides for subscription products
3. Verify tab is not visible for variable, grouped, or external products

**Expected:** Tab only shows for simple products.

### Test 0.6: Product Data Panel — Field Content [PLAYWRIGHT]

**Steps:**
1. Open Telegram Access tab on a simple product
2. Verify channel select dropdown with configured channel(s)
3. Verify upsell text: "Need to set access expiry? Automatic user removal?"
4. Verify "Need Help?" documentation link

**Expected:** All fields and upsell content rendered correctly.

### Test 0.7: Product Data Panel — Save Meta [PLAYWRIGHT + AUTO]

**Steps:**
1. Select a channel in the dropdown
2. Save product
3. `wp post meta get <product_id> _telegram_channel_ids --format=json`
4. Verify channel ID saved correctly

**Expected:** Product meta saved with selected channel ID.

### Test 0.8: Order Details — Direct Invite Display [PLAYWRIGHT + AUTO]

**Steps:**
1. Ensure activation flow is disabled
2. Create an order with a Telegram-linked simple product (status → processing)
3. View order on frontend (My Account > Orders)
4. Verify "Telegram Access" heading and invite link(s)
5. View order in admin
6. Verify invite link meta visible

**Expected:** Invite links displayed on both frontend and admin.

### Test 0.9: Order Details — Activation Flow Display [PLAYWRIGHT + AUTO]

**Steps:**
1. Enable activation flow: `wp option update wctlgm_require_activation_flow 1`
2. Re-set webhook (activation flow change resets it)
3. Create an order with a Telegram-linked simple product (status → processing)
4. View order on frontend
5. Verify "Telegram Activation Code" heading with code and deep link
6. View order in admin
7. Verify activation code meta

**Expected:** Activation code and bot link displayed.

### Test 0.10: Order Details — Pending Order Display [PLAYWRIGHT]

**Steps:**
1. Create order in pending-payment status
2. View order on frontend
3. Verify message: "will be emailed and available after payment processing"

**Expected:** Pending message shown for both activation and direct flow modes.

### Test 0.11: WooCommerce Email Settings [PLAYWRIGHT]

**Steps:**
1. Navigate to WooCommerce > Settings > Emails
2. Verify "Telegram Channel Activation" email listed
3. Verify "Telegram Invite Links" email listed
4. Click into each to verify configurable

**Expected:** Both custom emails registered and configurable.

## Round 1: Subscriber Flows

### Test 1.1: Direct Invite Flow — Simple Product [AUTO + MANUAL]

**Steps:**
1. Ensure activation flow disabled
2. Create order via WP-CLI or checkout (status → processing)
3. Verify `_channel_invite_*` meta exists on order
4. Verify no `_activation_code` meta
5. Verify email scheduled/sent (check WC logs)
6. [AUTO] Simulate join request webhook:
   ```bash
   curl -X POST <webhook_url> \
     -H "Content-Type: application/json" \
     -H "X-Telegram-Bot-Api-Secret-Token: <secret>" \
     -d '{"chat_join_request":{"chat":{"id":"<channel_id>"},"from":{"id":"<test_user_id>"},"invite_link":{"invite_link":"<stored_invite_link>"}}}'
   ```
7. Verify `_telegram_user_id` set on order
8. Check plugin log for "Join request approved"

**Expected:** Full flow completes. User ID captured on join.

### Test 1.2: Activation Flow — Simple Product [AUTO + MANUAL]

**Steps:**
1. Enable activation flow
2. Re-set webhook
3. Create order (status → processing)
4. Verify `_activation_code` exists, no invite links
5. [AUTO] Simulate `/start` deep link:
   ```bash
   curl -X POST <webhook_url> \
     -H "Content-Type: application/json" \
     -H "X-Telegram-Bot-Api-Secret-Token: <secret>" \
     -d '{"message":{"chat":{"id":"<test_chat_id>","type":"private"},"from":{"id":"<test_user_id>"},"text":"/start <activation_code>","entities":[{"type":"bot_command","offset":0,"length":6}]}}'
   ```
6. Verify activation code deleted from order
7. Verify `_telegram_user_id` set
8. Verify invite link(s) generated and stored
9. [AUTO] Simulate join request (same as Test 1.1 step 6)
10. Verify approved (user ID matches)

**Expected:** Full activation flow completes. Code consumed, user linked, invites generated.

### Test 1.3: External Invites — Allow/Deny [AUTO]

**Steps:**
1. Enable external invites: `wp option update wctlgm_allow_external_invites 1`
2. Simulate join request with unrecognized invite link
3. Verify approved + logged as external invite
4. Disable external invites: `wp option delete wctlgm_allow_external_invites`
5. Simulate same join request
6. Verify denied

**Expected:** External invite setting correctly gates approval.

## Error Monitoring Protocol

After **every** action that triggers plugin code:

1. Check debug log: `tail -20 wp-content/debug.log`
2. Check plugin log: `tail -20 wp-content/uploads/wc-logs/wctlgm-subscriber-manager-lite-*.log`
3. If errors found: stop, document, investigate before continuing

## What Remains Manual

- **Real Telegram join:** Clicking an actual invite link in the Telegram app to trigger a real `chat_join_request` webhook. Optional — curl simulation covers the logic.
- **Email visual verification:** Confirming email templates render correctly in an email client.
- **Channel ID fetch:** The "edit message in channel" step requires interacting with the Telegram app.

## Test Run History

> No test runs recorded yet. Document results here when E2E tests are first executed.

| Date | Version | Tests Run | Passed | Failed | Notes |
|------|---------|-----------|--------|--------|-------|
| — | — | — | — | — | — |
