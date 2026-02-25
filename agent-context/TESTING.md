# Testing Guide

> **Version:** 1.7.0 | **Last updated:** 2026-02-25

## Current Status

**No test suite exists for the lite plugin.** There is no `phpunit.xml`, no `tests/` directory, and no dev dependencies in `composer.json`.

The pro version (`wctlgm-subscriber-manager`) has a comprehensive test suite with PHPUnit, Brain\Monkey, and Mockery that serves as a reference for establishing testing infrastructure here.

## Proposed Test Stack

| Tool | Version | Purpose |
|------|---------|---------|
| PHPUnit | ^9.6 | Test runner |
| Brain\Monkey | ^2.6 | WordPress function mocking |
| Mockery | ^1.6 | Object mocking |

Integration tests (wp-env / Docker) are not planned for the initial phase. The lite plugin's simpler architecture makes unit tests with mocking sufficient for high-confidence coverage.

## Setup Plan

### Step 1: Add dev dependencies to composer.json

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "brain/monkey": "^2.6",
        "mockery/mockery": "^1.6",
        "symfony/polyfill-php73": "^1.29"
    },
    "scripts": {
        "test:unit": "vendor/bin/phpunit"
    }
}
```

### Step 2: Create phpunit.xml.dist

```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    verbose="true"
    beStrictAboutTestsThatDoNotTestAnything="true"
>
    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">tests</directory>
            <exclude>tests/stubs</exclude>
        </testsuite>
    </testsuites>
</phpunit>
```

### Step 3: Create test bootstrap (`tests/bootstrap.php`)

The bootstrap should:
1. Load Composer autoloader (`vendor/autoload.php`)
2. Initialize Brain\Monkey (`Brain\Monkey\setUp()`)
3. Define WordPress constants: `ABSPATH`, `WCTLGM_SML_PLUGIN_DIR`, `WCTLGM_SML_PLUGIN_BASE`, `HOUR_IN_SECONDS`
4. Load WordPress function stubs (`tests/stubs/wordpress.php`)
5. Load WooCommerce class stubs (`tests/stubs/woocommerce.php`)
6. Require plugin class files in dependency order (matching the load order in CONVENTIONS.md)

**Note:** Must handle namespaced classes — all plugin classes use `Subscriber_Manager_Lite_for_Telegram` namespace.

### Step 4: Create base TestCase (`tests/TestCase.php`)

```php
namespace Subscriber_Manager_Lite_for_Telegram\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;
use Mockery;

class TestCase extends PHPUnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock WooCommerce order item.
     */
    protected function create_mock_item( $product_id, $variation_id = 0 ) {
        $item = Mockery::mock( 'WC_Order_Item_Product' );
        $item->shouldReceive( 'get_product_id' )->andReturn( $product_id );
        $item->shouldReceive( 'get_variation_id' )->andReturn( $variation_id );
        return $item;
    }
}
```

### Step 5: Create stubs

**`tests/stubs/wordpress.php`** — Stub WordPress functions not handled by Brain\Monkey:
- `sanitize_text_field()`, `sanitize_url()`, `esc_url_raw()`, `esc_html()`, `esc_url()`, `esc_attr()`
- `wp_generate_password()`, `wp_json_encode()`
- `get_bloginfo()`
- `plugin_basename()`, `plugin_dir_path()`, `plugin_dir_url()`
- `wp_kses_post()`

**`tests/stubs/woocommerce.php`** — Stub WooCommerce classes:
- `WC_Order` with methods: `get_id()`, `get_items()`, `get_status()`, `get_meta()`, `update_meta_data()`, `delete_meta_data()`, `add_meta_data()`, `save()`, `get_meta_data()`
- `WC_Order_Item_Product` with: `get_product_id()`, `get_variation_id()`
- `WC_Product` with: `is_type()`, `get_id()`
- `WC_Email` base class
- `WC()` function returning mailer with `get_emails()`

## Proposed Test Coverage

### Priority 1: Core Logic

#### SubscriptionsHandlerTest

| Test | Scenario |
|------|----------|
| `test_process_activation_code_valid` | Valid code, valid order status → sets user ID, deletes code, generates invites |
| `test_process_activation_code_invalid` | Non-existent code → returns false |
| `test_process_activation_code_wrong_status` | Order in on-hold/cancelled → returns false |
| `test_is_join_request_valid_activation_flow_match` | Activation flow + matching user ID → true |
| `test_is_join_request_valid_activation_flow_mismatch` | Activation flow + wrong user ID → false |
| `test_is_join_request_valid_direct_flow_capture` | Direct flow + no existing user ID → sets ID, returns true |
| `test_is_join_request_valid_direct_flow_overwrite` | Direct flow + existing different user ID → logs warning, returns false |
| `test_is_join_request_valid_direct_flow_same_user` | Direct flow + same user ID → returns true |
| `test_is_join_request_valid_external_invites` | No order found + external invites enabled → true |
| `test_is_join_request_valid_no_order_no_external` | No order found + external invites disabled → false |
| `test_get_channel_invites_with_channels` | Products with channel IDs → generates invite links |
| `test_get_channel_invites_variation` | Variable product variation with channel IDs → uses variation meta, not parent |
| `test_get_channel_invites_existing_invite` | Existing invite link → reuses it |
| `test_get_channel_invites_no_channels` | No channel IDs → returns success=false |
| `test_find_order_by_multiple_matches` | Multiple orders match → returns null |

#### OrderHandlerTest

| Test | Scenario |
|------|----------|
| `test_maybe_process_order_processing_status` | Status → processing → generates code/invites |
| `test_maybe_process_order_completed_status` | Status → completed → generates code/invites |
| `test_maybe_process_order_skip_processing_to_completed` | processing → completed → skips |
| `test_maybe_process_order_non_telegram_product` | No `_telegram_channel_ids` → skips |
| `test_maybe_process_order_variable_product` | Variable product with variation channel IDs → processes |
| `test_maybe_process_order_unsupported_product` | Subscription/grouped product → skips |
| `test_maybe_process_order_wrong_status` | Status → on-hold → skips |
| `test_activation_code_generation` | Generates 8-char code, saves to order |
| `test_activation_code_not_regenerated` | Code already exists → skips |
| `test_direct_flow_generates_invites` | Activation disabled → calls get_channel_invites, stores meta, schedules email |
| `test_direct_flow_skips_existing_invites` | Invites already exist → skips |

#### BotInteractionHandlerTest

| Test | Scenario |
|------|----------|
| `test_start_command_no_payload` | `/start` → welcome message |
| `test_start_command_with_code` | `/start CODE` → triggers activation |
| `test_start_command_namespaced` | `/start activate_CODE` → triggers activation |
| `test_activate_valid_code` | `/activate VALID` → activation successful, schedules email |
| `test_activate_invalid_code` | `/activate BAD` → activation failed |
| `test_activate_empty_code` | `/activate` → error message |
| `test_help_command` | `/help` → help text |
| `test_join_request_valid` | chat_join_request + valid → approve + revoke |
| `test_join_request_invalid` | chat_join_request + invalid → deny |
| `test_edited_message_with_settings_active` | Edited message + transient active → saves channel ID |
| `test_edited_message_without_settings_active` | Edited message + transient not active → skips |
| `test_non_private_message` | Group message → ignored |

### Priority 2: Infrastructure

#### EndpointHandlerTest

| Test | Scenario |
|------|----------|
| `test_valid_secret_token` | Matching header token → returns true |
| `test_invalid_secret_token` | Wrong token → returns false |
| `test_missing_secret_token` | No header → returns false |
| `test_request_routing` | Valid request → delegates to Bot_Interaction_Handler |

#### SettingsTest

| Test | Scenario |
|------|----------|
| `test_sanitize_channels_single` | Input with name+id → returns single-entry array |
| `test_sanitize_channels_empty` | Empty input → returns array with empty entry |
| `test_sanitize_checkbox` | Checked → true, unchecked → false |
| `test_handle_activation_flow_change` | Toggle → deletes wctlgm_webhook_clicked |
| `test_migrate_activation_flow` | Legacy option exists → migrates to new key |
| `test_migrate_skips_if_done` | Migration flag set → skips |

### Priority 3: Email

#### EmailHandlerTest

| Test | Scenario |
|------|----------|
| `test_email_classes_registered` | Filter returns both `wctlgm_activation` and `wctlgm_invite_links` |

## Writing New Tests

### Pattern

```php
namespace Subscriber_Manager_Lite_for_Telegram\Tests;

use Brain\Monkey\Functions;
use Mockery;

class SubscriptionsHandlerTest extends TestCase {
    private $handler;

    protected function setUp(): void {
        parent::setUp();
        // Mock dependencies created in constructor
        Functions\when('get_option')->alias(function($key, $default = false) {
            return $default;
        });
        $this->handler = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
    }

    public function test_example() {
        // Arrange
        Functions\expect('wc_get_orders')
            ->once()
            ->andReturn([]);

        // Act
        $result = $this->handler->is_join_request_valid('123', 'link', '-100123', false);

        // Assert
        $this->assertFalse($result);
    }
}
```

### Mocking WordPress Functions

```php
// Simple return value
Functions\when('get_option')->justReturn('some_value');

// Conditional returns
Functions\when('get_option')->alias(function($key, $default = false) {
    $options = [
        'wctlgm_require_activation_flow' => true,
        'wctlgm_allow_external_invites' => false,
    ];
    return $options[$key] ?? $default;
});

// Expect specific calls
Functions\expect('update_option')
    ->once()
    ->with('wctlgm_webhook_clicked', true);
```

### Mocking WooCommerce Objects

```php
// Mock order
$order = Mockery::mock('WC_Order');
$order->shouldReceive('get_id')->andReturn(123);
$order->shouldReceive('get_status')->andReturn('processing');
$order->shouldReceive('get_items')->andReturn([$item]);
$order->shouldReceive('get_meta')
    ->with('_activation_code', true)
    ->andReturn('abc12345');
$order->shouldReceive('update_meta_data')->with('_telegram_user_id', '999');
$order->shouldReceive('delete_meta_data')->with('_activation_code', 'abc12345');
$order->shouldReceive('save');

// Mock product (simple or variable in lite)
$product = Mockery::mock('WC_Product');
$product->shouldReceive('is_type')->with(['simple', 'variable'])->andReturn(true);

Functions\when('wc_get_product')->justReturn($product);
```

## Areas of Complexity

These areas are trickier to test and may need creative mocking:

1. **Transient-based channel ID fetch flow** — Settings AJAX and webhook handler interact via transients (`wctlgm_telegram_fetch_channel_id_active`, `wctlgm_channel_id_temp_store`). Test each side independently.

2. **Action Scheduler integration** — `as_schedule_single_action()` calls need to be stubbed/mocked. Define the function in stubs if needed.

3. **Freemius SDK calls** — `wctlgm_fs()->get_upgrade_url()` and `wctlgm_fs()->is_plan('pro')` appear in settings and product panels. Stub the `wctlgm_fs()` function to return a mock object.

4. **Logger as both static and instance** — The Logger class has static methods but is also instantiated. Constructor calls `wc_get_logger()` which must be stubbed.
