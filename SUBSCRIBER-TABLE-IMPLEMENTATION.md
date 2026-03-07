# Subscriber Table Feature — Implementation Guide

This document describes how the subscriber table feature was implemented in the **pro plugin** (`wctlgm-subscriber-manager`), to guide adding the same functionality to the **lite plugin** (`wctlgm-subscriber-manager-lite`).

## Overview

The subscriber table feature adds:
1. **Custom database tables** to track Telegram users and their channel memberships
2. **Admin list table** (WP_List_Table) showing all subscribers with search, filter, sort, and pagination
3. **Detail modal** with live Telegram status lookup and admin actions (remove, ban, unban, revoke invite)
4. **Sync functionality** to verify live Telegram membership and update DB
5. **Webhook-driven lifecycle tracking** — `chat_join_request` and `chat_member` events write to DB
6. **Order handler integration** — invite generation creates DB records
7. **Data migration** — backfills from existing order meta on first install

---

## Key Differences: Pro vs Lite Plugin

| Aspect | Pro Plugin | Lite Plugin |
|--------|-----------|-------------|
| **Class prefix** | `WC_Telegram_` | `Subscriber_Manager_Lite_WCTLGM_` |
| **File naming** | `class-wc-telegram-*.php` | `class-subscriber-manager-lite-wctlgm-*.php` |
| **Text domain** | `wctlgm-subscriber-manager` | `wctlgm-subscriber-manager-lite` |
| **Package name** | `Subscriber_Manager_Pro_for_Telegram` | `Subscriber_Manager_Lite_for_Telegram` |
| **Plugin constants** | `WCTLGM_SM_*` | `WCTLGM_SML_*` |
| **Namespace** | None (global classes) | `Subscriber_Manager_Lite_for_Telegram` (namespaced) |
| **Subscription handlers** | Factory pattern (WCS, FSub, Base) | Single handler class |
| **Subscription lifecycle** | Full (cancel, expire, renew) | None |
| **Main plugin file** | `wctlgm-subscriber-manager.php` | `wctlgm-subscriber-manager-lite.php` |

**Important:** The lite plugin uses PHP namespaces, so all new classes should be within the `Subscriber_Manager_Lite_for_Telegram` namespace (or follow whatever pattern the lite plugin uses). The pro plugin does NOT use namespaces.

**Important:** The lite plugin does NOT have subscription plugin support (WooCommerce Subscriptions, Flexible Subscriptions), so subscription lifecycle tracking (cancel/expire) is not needed. However, simple product expiry IS relevant if the lite plugin supports `_telegram_channel_expiry`.

---

## New Files to Create

### 1. Database Class

**Pro file:** `includes/class-wc-telegram-database.php` (860 lines)
**Lite equivalent:** `includes/class-subscriber-manager-lite-wctlgm-database.php`

This is a static class with no constructor. All methods are `public static`.

#### Database Schema

Two tables are created via `dbDelta()`:

**Table 1: `{prefix}wctlgm_telegram_users`**
```sql
CREATE TABLE {prefix}wctlgm_telegram_users (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    telegram_user_id varchar(255) NOT NULL,
    telegram_username varchar(255) DEFAULT NULL,
    first_name varchar(255) DEFAULT NULL,
    last_name varchar(255) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY telegram_user_id (telegram_user_id),
    KEY telegram_username (telegram_username)
);
```

**Table 2: `{prefix}wctlgm_user_channels`**
```sql
CREATE TABLE {prefix}wctlgm_user_channels (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    telegram_user_id varchar(255) DEFAULT NULL,
    channel_id varchar(255) NOT NULL,
    order_id bigint(20) DEFAULT NULL,
    invite_link text DEFAULT NULL,
    invite_issued_at datetime DEFAULT NULL,
    invite_revoked_at datetime DEFAULT NULL,
    joined_at datetime DEFAULT NULL,
    left_at datetime DEFAULT NULL,
    status varchar(50) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY telegram_user_id (telegram_user_id),
    KEY channel_id (channel_id),
    KEY order_id (order_id),
    KEY status (status)
);
```

#### Table Creation Trigger

Tables are created via two mechanisms:
1. `register_activation_hook` sets a `wctlgm_needs_setup` transient
2. `admin_init` hook calls `maybe_create_tables_and_migrate()` which checks both the transient and a `wctlgm_db_version` option

```php
public static function init() {
    add_action( 'admin_init', array( __CLASS__, 'maybe_create_tables_and_migrate' ) );
}

public static function maybe_create_tables_and_migrate() {
    $current_db_version = get_option( 'wctlgm_db_version', '0' );
    $needs_setup        = get_transient( 'wctlgm_needs_setup' );

    if ( $needs_setup || version_compare( $current_db_version, self::DB_VERSION, '<' ) ) {
        self::create_tables();

        // Only migrate on first install.
        if ( '0' === $current_db_version ) {
            self::migrate_existing_data();
        }

        delete_transient( 'wctlgm_needs_setup' );
        update_option( 'wctlgm_db_version', self::DB_VERSION );
    }
}
```

The activation hook (in main plugin file) should set the transient:
```php
register_activation_hook( __FILE__, function() {
    set_transient( 'wctlgm_needs_setup', true, 5 * MINUTE_IN_SECONDS );
});
```

#### Key Methods

**User CRUD:**
- `get_or_create_user( $telegram_user_id, $data )` — Creates user if not exists, updates if exists. Returns record ID.
- `update_user( $telegram_user_id, $data )` — Updates username, first_name, last_name.
- `get_user( $telegram_user_id )` — Returns user object by Telegram user ID.

**User-Channel CRUD:**
- `add_user_channel( $data )` — Adds or updates a channel record. Uses logical keys: `(order_id, channel_id)` for order-based records, `(telegram_user_id, channel_id) WHERE order_id IS NULL` for external invites.
- `find_user_channel_record( $data )` — Finds existing record by logical key.
- `update_user_channel_status( $telegram_user_id, $channel_id, $status, $extra )` — Updates status, auto-sets `joined_at` for `active` and `left_at` for leave/remove/ban/expire.
- `get_user_channel( $telegram_user_id, $channel_id )` — Returns single most-relevant record (orders `active` first).
- `get_user_channels( $telegram_user_id )` — Returns all channel records for a user.
- `get_channels_by_order( $order_id )` — Returns channel records by order ID.
- `find_by_invite_link( $invite_link, $channel_id )` — Finds record by invite link.
- `update_channel_record( $record_id, $data )` — Direct update by record ID.
- `link_user_to_order_channels( $order_id, $telegram_user_id )` — Links a Telegram user to all channel records for an order.

**List Table Queries:**
- `get_subscribers_for_table( $args )` — Main query for the list table. Returns one row per user with aggregated data.
- `count_subscribers( $args )` — Count for pagination.

**Multi-Access Check:**
- `get_other_active_channel_access( $telegram_user_id, $channel_id, $exclude_order_id )` — Checks if user has other active records for a channel.

**Migration:**
- `migrate_existing_data()` — Scans orders with `_telegram_user_id` or `_channel_invite_*` meta and creates DB records.

#### Critical: `update_user_channel_status()` Logic

This method has important behavior:

1. **`joined_at` preservation:** When setting status to `active`, only sets `joined_at` if the existing record has no `joined_at` value. This preserves the original join timestamp.
2. **`left_at` auto-set:** When setting status to `left`, `removed`, `banned`, or `expired`, auto-sets `left_at`.
3. **Two-phase update:** First tries to update a record with `status = 'active'` (most common case). If no rows affected, falls back to updating any record for the user+channel pair.

```php
public static function update_user_channel_status( $telegram_user_id, $channel_id, $status, $extra = array() ) {
    global $wpdb;

    $table       = $wpdb->prefix . 'wctlgm_user_channels';
    $update_data = array( 'status' => $status );

    if ( 'active' === $status && ! isset( $extra['joined_at'] ) ) {
        $existing = self::get_user_channel( $telegram_user_id, $channel_id );
        if ( ! $existing || empty( $existing->joined_at ) ) {
            $update_data['joined_at'] = current_time( 'mysql' );
        }
    } elseif ( in_array( $status, array( 'left', 'removed', 'banned', 'expired' ), true ) && ! isset( $extra['left_at'] ) ) {
        $update_data['left_at'] = current_time( 'mysql' );
    }

    $update_data = array_merge( $update_data, $extra );

    // Try active record first, then fall back to any record.
    $result = $wpdb->update( $table, $update_data, array(
        'telegram_user_id' => $telegram_user_id,
        'channel_id'       => $channel_id,
        'status'           => 'active',
    ));

    if ( 0 === $wpdb->rows_affected ) {
        $result = $wpdb->update( $table, $update_data, array(
            'telegram_user_id' => $telegram_user_id,
            'channel_id'       => $channel_id,
        ));
    }

    return false !== $result;
}
```

#### Critical: `get_user_channel()` Ordering

This method returns the **most relevant** record when multiple exist (e.g., from multiple orders):

```sql
SELECT * FROM {table}
WHERE telegram_user_id = %s AND channel_id = %s
ORDER BY status = 'active' DESC, created_at DESC
LIMIT 1
```

#### Critical: List Table SQL

The main query uses `GROUP_CONCAT` to aggregate channel+status pairs:

```sql
SELECT u.*,
    GROUP_CONCAT(DISTINCT CONCAT(uc.channel_id, '|', uc.status) SEPARATOR ',') AS channel_status_pairs,
    GROUP_CONCAT(DISTINCT uc.order_id SEPARATOR ',') AS order_ids,
    MIN(uc.joined_at) AS earliest_joined,
    COUNT(DISTINCT uc.channel_id) AS channel_count
FROM {users_table} u
LEFT JOIN {channels_table} uc ON u.telegram_user_id = uc.telegram_user_id
{WHERE clause}
GROUP BY u.telegram_user_id
ORDER BY {column} {direction}
LIMIT %d OFFSET %d
```

#### Migration Logic (Lite Simplified)

The lite plugin doesn't have a subscription handler factory, so migration status determination is simpler. In the pro plugin, `determine_migration_status()` checks WCS subscriptions. For the lite plugin, just check order status:

```php
private static function determine_migration_status( $order, $telegram_user_id ) {
    if ( empty( $telegram_user_id ) ) {
        return 'pending';
    }
    $order_status = $order->get_status();
    if ( in_array( $order_status, array( 'completed', 'processing' ), true ) ) {
        return 'active';
    }
    return 'expired';
}
```

---

### 2. List Table Class

**Pro file:** `includes/class-wc-telegram-users-list-table.php` (305 lines)
**Lite equivalent:** `includes/class-subscriber-manager-lite-wctlgm-users-list-table.php`

Extends `WP_List_Table`. Must `require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'` at the top.

#### Columns

```php
public function get_columns() {
    return array(
        'telegram_username' => __( 'Username', 'text-domain' ),
        'name'              => __( 'Name', 'text-domain' ),
        'channels'          => __( 'Channels/Groups', 'text-domain' ),
        'joined_at'         => __( 'Joined', 'text-domain' ),
    );
}
```

#### Sortable Columns

Only `joined_at` is sortable:
```php
public function get_sortable_columns() {
    return array(
        'joined_at' => array( 'joined_at', true ),
    );
}
```

#### Channel Filter Dropdown

`extra_tablenav( 'top' )` renders a dropdown of configured channels from `get_option( 'wctlgm_channels' )`.

#### `prepare_items()` — Nonce Handling

**Critical design decision:** Sorting/pagination params (`orderby`, `order`, `paged`) are read from `$_REQUEST` WITHOUT nonce verification (they're read-only GET params from WP_List_Table links), but validated against an allow-list. Search (`s`) and channel filter (`channel_filter`) require nonce verification.

```php
public function prepare_items() {
    // Sorting — no nonce needed, but validated against allow-list.
    $allowed_orderby = array( 'created_at', 'joined_at' );
    $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
    $orderby = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
    $order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
    $order   = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'DESC';

    // Search and filter — require nonce.
    $search  = '';
    $channel = '';
    if ( isset( $_REQUEST['_wctlgm_table_nonce'] ) && wp_verify_nonce( ... ) ) {
        $search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( ... ) : '';
        $channel = isset( $_REQUEST['channel_filter'] ) ? sanitize_text_field( ... ) : '';
    }
    // ... pass to Database::get_subscribers_for_table()
}
```

#### Username Column with Row Actions

```php
public function column_telegram_username( $item ) {
    $username = ! empty( $item->telegram_username ) ? '@' . esc_html( $item->telegram_username ) : '—';

    $actions = array(
        'view' => sprintf(
            '<a href="#" class="wctlgm-view-user" data-telegram-id="%s">%s</a>',
            esc_attr( $item->telegram_user_id ),
            __( 'View Details', 'text-domain' )
        ),
        'sync' => sprintf(
            '<a href="#" class="wctlgm-sync-user" data-telegram-id="%s">%s</a>',
            esc_attr( $item->telegram_user_id ),
            __( 'Sync Status', 'text-domain' )
        ),
    );

    return sprintf( '<strong>%s</strong>%s', $username, $this->row_actions( $actions ) );
}
```

#### Channels Column — Priority-Based Deduplication

The `channel_status_pairs` field from SQL can contain duplicate channels (from multiple orders). The column renderer deduplicates by keeping only the highest-priority status per channel:

```php
public function column_channels( $item ) {
    if ( empty( $item->channel_status_pairs ) ) {
        return '—';
    }

    $status_priority = array(
        'active'  => 1,
        'pending' => 2,
        'left'    => 3,
        'expired' => 4,
        'removed' => 5,
        'banned'  => 6,
    );

    $channels = array();
    $pairs    = explode( ',', $item->channel_status_pairs );

    foreach ( $pairs as $pair ) {
        $parts = explode( '|', $pair, 2 );
        if ( count( $parts ) !== 2 ) continue;

        $channel_id = trim( $parts[0] );
        $status     = trim( $parts[1] );
        $priority   = isset( $status_priority[ $status ] ) ? $status_priority[ $status ] : 99;

        if ( ! isset( $channels[ $channel_id ] ) || $priority < $channels[ $channel_id ]['priority'] ) {
            $channels[ $channel_id ] = array(
                'status'   => $status,
                'priority' => $priority,
            );
        }
    }

    $output = '';
    foreach ( $channels as $channel_id => $data ) {
        $name         = $this->get_channel_name( $channel_id );
        $status_label = $this->get_status_label( $data['status'] );

        $output .= sprintf(
            '<span class="wctlgm-channel-status-pair"><span class="wctlgm-channel-badge">%s</span> <span class="wctlgm-status-badge wctlgm-status-%s">%s</span></span>',
            esc_html( $name ),
            esc_attr( $data['status'] ),
            esc_html( $status_label )
        );
    }

    return $output;
}
```

#### Status Label Mapping

DB status values map to human-readable labels:

| DB Status | Display Label |
|-----------|--------------|
| `active` | Member |
| `pending` | Pending |
| `left` | Left |
| `removed` | Removed |
| `banned` | Banned |
| `expired` | Expired |

#### Channel Name Resolution

`get_channel_name( $channel_id )` looks up the channel name from `get_option( 'wctlgm_channels' )`. Falls back to the raw channel ID. Uses `trim()` on both sides of the comparison (important for matching).

---

### 3. JavaScript File

**Pro file:** `assets/js/wctlgm-admin-users.js` (282 lines)
**Lite equivalent:** `assets/js/wctlgm-admin-users.js`

#### Script Handle & Localization

Enqueued as `wctlgm-admin-users-js`, localized with `wctlgm_users_vars`:
```php
wp_localize_script( 'wctlgm-admin-users-js', 'wctlgm_users_vars', array(
    'nonce' => wp_create_nonce( 'wctlgm_users_nonce' ),
    'i18n'  => array(
        'loading'        => __( 'Loading...', ... ),
        'error'          => __( 'An error occurred. Please try again.', ... ),
        'username'       => __( 'Username', ... ),
        'name'           => __( 'Name', ... ),
        'telegram_id'    => __( 'Telegram User ID', ... ),
        'orders'         => __( 'Orders', ... ),
        'external'       => __( 'External invite', ... ),
        'channel_access' => __( 'Channel Access', ... ),
        'remove'         => __( 'Remove', ... ),
        'unban'          => __( 'Unban', ... ),
        'ban'            => __( 'Ban', ... ),
        'revoke_invite'  => __( 'Revoke Invite', ... ),
        'confirm_remove' => __( 'Remove this user from the channel? They can rejoin with a new invite link.', ... ),
        'confirm_unban'  => __( 'Unban this user? They will be able to rejoin with a new invite link.', ... ),
        'confirm_ban'    => __( 'Ban this user from the channel? This is permanent — they will NOT be able to rejoin.', ... ),
        'confirm_revoke' => __( 'Revoke this pending invite link?', ... ),
        'sync_status'    => __( 'Sync Status', ... ),
        'syncing'        => __( 'Syncing...', ... ),
        'sync_success'   => __( 'Status synced successfully.', ... ),
        'sync_error'     => __( 'Failed to sync status.', ... ),
    ),
));
```

#### Features

1. **Tab switching** — Settings tab and Subscribers tab. Auto-switches to Subscribers tab when URL has search/filter/orderby/paged params. Appends `#subscribers` hash to pagination links and form actions.

2. **Modal** — Opens on "View Details" click. AJAX calls `wctlgm_get_user_details`. Renders user info, orders, and per-channel status with action buttons. Closes on X, overlay click, or Escape key.

3. **Action buttons logic:**
   - `pending` status → "Revoke Invite" button
   - `banned` status → "Unban" button only (no Ban button)
   - All other statuses → "Remove" button + "Ban" button
   - "Unban" calls the `remove` action (which uses `unbanChatMember`)
   - Confirmation dialogs differ: unban vs remove vs ban

4. **Sync Status** — Row action. AJAX calls `wctlgm_sync_user_status`. Shows "Syncing..." text and dims row during request. Reloads page on success.

5. **Helpers:** `escHtml()` and `escAttr()` for safe HTML rendering.

---

### 4. CSS File

**Pro file:** `assets/css/wctlgm-admin-users.css` (247 lines)
**Lite equivalent:** `assets/css/wctlgm-admin-users.css`

Copy this file as-is. All class names use the `wctlgm-` prefix which is shared between pro and lite.

Contains styles for:
- Tab content panels (`.wctlgm-content-tab`, `.wctlgm-tab-active`)
- Status badges (`.wctlgm-status-badge`, `.wctlgm-status-active`, etc.)
- Channel badges (`.wctlgm-channel-badge`)
- Channel-status pairs in table (`.wctlgm-channel-status-pair`)
- Table enhancements (`.wctlgm-view-user`, `#wctlgm-subscriber-table-wrap`)
- Modal (`.wctlgm-modal-overlay`, `.wctlgm-modal`, etc.)
- Detail fields in modal (`.wctlgm-detail-row`, etc.)
- Channel rows in modal (`.wctlgm-channel-row`, etc.)
- Loading spinner (`.wctlgm-spinner`)
- Multi-access warning (`.wctlgm-warning`)

---

## Modifications to Existing Files

### 5. Main Plugin File

**Pro file:** `wctlgm-subscriber-manager.php`
**Lite equivalent:** `wctlgm-subscriber-manager-lite.php`

Add to the main plugin class (the orchestrator that loads all includes):

1. **Require the new files:**
```php
require_once plugin_dir_path( __FILE__ ) . 'includes/class-subscriber-manager-lite-wctlgm-database.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-subscriber-manager-lite-wctlgm-users-list-table.php';
```

2. **Initialize the database handler:**
```php
Subscriber_Manager_Lite_WCTLGM_Database::init();
```

3. **Add activation hook** (set transient for table creation):
```php
register_activation_hook( __FILE__, function() {
    set_transient( 'wctlgm_needs_setup', true, 5 * MINUTE_IN_SECONDS );
});
```

---

### 6. Settings Class

**Pro file:** `includes/class-wc-telegram-subscriber-manager-settings.php`
**Lite equivalent:** `includes/class-subscriber-manager-lite-wctlgm-settings.php`

#### Constructor Additions

Register 3 new AJAX actions:
```php
add_action( 'wp_ajax_wctlgm_get_user_details', array( $this, 'ajax_get_user_details' ) );
add_action( 'wp_ajax_wctlgm_subscriber_action', array( $this, 'ajax_subscriber_action' ) );
add_action( 'wp_ajax_wctlgm_sync_user_status', array( $this, 'ajax_sync_user_status' ) );
```

#### Script Enqueuing

In `enqueue_subscriber_manager_scripts()`, add (only on the settings page):

```php
// Only enqueue subscribers table assets on our settings page.
if ( 'settings_page_wctlgm-settings' !== $hook ) {
    return;
}

wp_enqueue_style(
    'wctlgm-admin-users-css',
    plugin_dir_url( __FILE__ ) . '../assets/css/wctlgm-admin-users.css',
    array(),
    filemtime( plugin_dir_path( __FILE__ ) . '../assets/css/wctlgm-admin-users.css' )
);

wp_enqueue_script(
    'wctlgm-admin-users-js',
    plugin_dir_url( __FILE__ ) . '../assets/js/wctlgm-admin-users.js',
    array( 'jquery' ),
    filemtime( plugin_dir_path( __FILE__ ) . '../assets/js/wctlgm-admin-users.js' ),
    true
);

wp_localize_script( 'wctlgm-admin-users-js', 'wctlgm_users_vars', array(
    'nonce' => wp_create_nonce( 'wctlgm_users_nonce' ),
    'i18n'  => array( /* ... see JS section above ... */ ),
));
```

**Note:** The hook name depends on how the settings page is registered. In the pro plugin it's `settings_page_wctlgm-settings` (registered via `add_options_page`). Verify the lite plugin uses the same menu slug.

#### Settings Page — Add Subscribers Tab

In the `settings_page()` method, add a second tab and the modal HTML:

```php
<h2 class="nav-tab-wrapper">
    <a href="#settings" class="nav-tab fs-tab wctlgm-tab">Settings</a>
    <a href="#subscribers" class="nav-tab fs-tab wctlgm-tab">Subscribers</a>
</h2>

<!-- Settings Tab -->
<div id="settings-content" class="wctlgm-content-tab">
    <!-- existing settings form -->
</div>

<!-- Subscribers Tab -->
<div id="subscribers-content" class="wctlgm-content-tab">
    <?php $this->users_page(); ?>
</div>

<!-- Subscriber Detail Modal -->
<div id="wctlgm-subscriber-modal" class="wctlgm-modal-overlay">
    <div class="wctlgm-modal">
        <div class="wctlgm-modal-header">
            <h3><?php esc_html_e( 'Subscriber Details', 'text-domain' ); ?></h3>
            <button class="wctlgm-modal-close" type="button">&times;</button>
        </div>
        <div class="wctlgm-modal-body" id="wctlgm-modal-body"></div>
    </div>
</div>
```

#### New Method: `users_page()`

```php
private function users_page() {
    $table = new Subscriber_Manager_Lite_WCTLGM_Users_List_Table();
    $table->prepare_items();
    ?>
    <div id="wctlgm-subscriber-table-wrap">
        <form method="get">
            <input type="hidden" name="page" value="wctlgm-settings" />
            <?php wp_nonce_field( 'wctlgm_subscriber_table', '_wctlgm_table_nonce' ); ?>
            <?php
            $table->search_box( __( 'Search Subscribers', 'text-domain' ), 'wctlgm-subscriber-search' );
            $table->display();
            ?>
        </form>
    </div>
    <?php
}
```

#### New Method: `ajax_get_user_details()`

Full implementation (from pro plugin, adapt class names):

```php
public function ajax_get_user_details() {
    check_ajax_referer( 'wctlgm_users_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
    }

    $telegram_id = isset( $_POST['telegram_id'] ) ? sanitize_text_field( wp_unslash( $_POST['telegram_id'] ) ) : '';
    if ( empty( $telegram_id ) ) {
        wp_send_json_error( array( 'message' => 'Missing Telegram ID.' ) );
    }

    $user = Database::get_user( $telegram_id ); // adapt class name
    if ( ! $user ) {
        wp_send_json_error( array( 'message' => 'User not found.' ) );
    }

    $channels_config = get_option( 'wctlgm_channels', array() );
    $channel_records = Database::get_user_channels( $telegram_id );

    // Build order list.
    $order_ids = array();
    foreach ( $channel_records as $record ) {
        if ( ! empty( $record->order_id ) ) {
            $order_ids[ $record->order_id ] = $record->order_id;
        }
    }

    $orders = array();
    foreach ( $order_ids as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $orders[] = array(
                'id'       => $order_id,
                'status'   => $order->get_status(),
                'edit_url' => $order->get_edit_order_url(),
            );
        } else {
            $orders[] = array(
                'id'     => $order_id,
                'status' => 'deleted',
            );
        }
    }

    // Build per-channel data with live Telegram status.
    // IMPORTANT: Use get_user_channel() per unique channel ID to deduplicate.
    $channels   = array();
    $unique_ids = array();
    foreach ( $channel_records as $r ) {
        $unique_ids[ $r->channel_id ] = true;
    }

    foreach ( array_keys( $unique_ids ) as $ch_id ) {
        $record = Database::get_user_channel( $telegram_id, $ch_id );
        if ( ! $record ) continue;

        // Resolve channel name.
        $channel_name = $record->channel_id;
        foreach ( $channels_config as $ch ) {
            if ( $ch['id'] === $record->channel_id ) {
                $channel_name = $ch['name'];
                break;
            }
        }

        // Get live Telegram status for active/pending users.
        $status       = $record->status;
        $status_label = ucfirst( $status );

        if ( in_array( $status, array( 'active', 'pending' ), true ) ) {
            $live_status = $this->api_handler->get_chat_member_status( $telegram_id, $record->channel_id );

            if ( in_array( $live_status, array( 'member', 'administrator', 'creator' ), true ) ) {
                $status       = 'active';
                $status_label = 'Member';
            } elseif ( 'restricted' === $live_status ) {
                $status       = 'active';
                $status_label = 'Restricted';
            } elseif ( 'left' === $live_status ) {
                $status       = 'left';
                $status_label = 'Left';
            } elseif ( 'kicked' === $live_status ) {
                $status       = 'banned';
                $status_label = 'Banned';
            } elseif ( 'pending' === $status ) {
                $status_label = 'Pending';
            }

            // Write back live status to DB.
            if ( $status !== $record->status ) {
                Database::update_user_channel_status( $telegram_id, $record->channel_id, $status );
            }
        }

        $channels[] = array(
            'channel_id'   => $record->channel_id,
            'name'         => $channel_name,
            'status'       => $status,
            'status_label' => $status_label,
        );
    }

    $name = trim( ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ) );

    wp_send_json_success( array(
        'telegram_id' => $user->telegram_user_id,
        'username'    => $user->telegram_username,
        'name'        => $name,
        'orders'      => $orders,
        'channels'    => $channels,
    ));
}
```

**Note:** `$this->api_handler->get_chat_member_status()` — the lite plugin's API handler class must have a `get_chat_member_status()` method that calls Telegram's `getChatMember` API. This method should use a 5-minute transient cache with key format: `'wctlgm_member_' . md5( $user_id . '_' . $channel_id )`. Check if the lite plugin already has this method. If not, it must be added to the API handler.

#### New Method: `ajax_subscriber_action()`

Dispatches to three private handler methods:

```php
public function ajax_subscriber_action() {
    check_ajax_referer( 'wctlgm_users_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
    }

    $sub_action  = sanitize_text_field( wp_unslash( $_POST['sub_action'] ?? '' ) );
    $telegram_id = sanitize_text_field( wp_unslash( $_POST['telegram_id'] ?? '' ) );
    $channel_id  = sanitize_text_field( wp_unslash( $_POST['channel_id'] ?? '' ) );

    if ( empty( $sub_action ) || empty( $telegram_id ) || empty( $channel_id ) ) {
        wp_send_json_error( array( 'message' => 'Missing required parameters.' ) );
    }

    switch ( $sub_action ) {
        case 'remove':
            $this->handle_remove_action( $telegram_id, $channel_id );
            break;
        case 'ban':
            $this->handle_ban_action( $telegram_id, $channel_id );
            break;
        case 'revoke':
            $this->handle_revoke_action( $telegram_id, $channel_id );
            break;
        default:
            wp_send_json_error( array( 'message' => 'Invalid action.' ) );
    }
}
```

**Remove action** — calls `unbanChatMember` (removes without banning, allows rejoin):
```php
private function handle_remove_action( $telegram_id, $channel_id ) {
    $result = $this->api_handler->unban_user_from_channel( $telegram_id, $channel_id );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }
    Database::update_user_channel_status( $telegram_id, $channel_id, 'removed' );
    wp_send_json_success( array( 'message' => 'User removed from channel.' ) );
}
```

**Ban action** — calls `banChatMember` (permanent ban):
```php
private function handle_ban_action( $telegram_id, $channel_id ) {
    $result = $this->api_handler->remove_user_from_channel( $telegram_id, $channel_id );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }
    Database::update_user_channel_status( $telegram_id, $channel_id, 'banned' );
    wp_send_json_success( array( 'message' => 'User banned from channel.' ) );
}
```

**Revoke action** — revokes a pending invite link:
```php
private function handle_revoke_action( $telegram_id, $channel_id ) {
    $record = Database::get_user_channel( $telegram_id, $channel_id );
    if ( ! $record || empty( $record->invite_link ) ) {
        wp_send_json_error( array( 'message' => 'No invite link found to revoke.' ) );
    }
    $result = $this->api_handler->revoke_invite_link( $channel_id, $record->invite_link );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }
    Database::update_channel_record( $record->id, array(
        'invite_revoked_at' => current_time( 'mysql' ),
        'status'            => 'removed',
    ));
    wp_send_json_success( array( 'message' => 'Invite link revoked.' ) );
}
```

**Note:** The lite plugin's API handler needs `unban_user_from_channel()`, `remove_user_from_channel()`, and `revoke_invite_link()` methods. Check if they already exist. If not, add them:
- `unban_user_from_channel( $user_id, $channel_id )` → calls Telegram `unbanChatMember` API
- `remove_user_from_channel( $user_id, $channel_id )` → calls Telegram `banChatMember` API
- `revoke_invite_link( $channel_id, $invite_link )` → calls Telegram `revokeChatInviteLink` API

#### New Method: `ajax_sync_user_status()`

```php
public function ajax_sync_user_status() {
    check_ajax_referer( 'wctlgm_users_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
    }

    $telegram_id = sanitize_text_field( wp_unslash( $_POST['telegram_id'] ?? '' ) );
    if ( empty( $telegram_id ) ) {
        wp_send_json_error( array( 'message' => 'Missing Telegram ID.' ) );
    }

    $channel_records = Database::get_user_channels( $telegram_id );
    if ( empty( $channel_records ) ) {
        wp_send_json_error( array( 'message' => 'No channel records found.' ) );
    }

    $updated = 0;
    foreach ( $channel_records as $record ) {
        if ( ! in_array( $record->status, array( 'active', 'pending' ), true ) ) {
            continue;
        }

        // Clear transient cache to force fresh API call.
        // KEY FORMAT MUST MATCH get_chat_member_status() in API handler.
        $cache_key = 'wctlgm_member_' . md5( $telegram_id . '_' . $record->channel_id );
        delete_transient( $cache_key );

        $live_status = $this->api_handler->get_chat_member_status( $telegram_id, $record->channel_id );

        if ( in_array( $live_status, array( 'member', 'administrator', 'creator', 'restricted' ), true ) ) {
            if ( 'active' !== $record->status ) {
                Database::update_user_channel_status( $telegram_id, $record->channel_id, 'active' );
                ++$updated;
            }
        } elseif ( 'left' === $live_status ) {
            if ( 'left' !== $record->status ) {
                Database::update_user_channel_status( $telegram_id, $record->channel_id, 'left' );
                ++$updated;
            }
        } elseif ( 'kicked' === $live_status ) {
            if ( 'banned' !== $record->status ) {
                Database::update_user_channel_status( $telegram_id, $record->channel_id, 'banned' );
                ++$updated;
            }
        }
    }

    wp_send_json_success( array(
        'message' => sprintf( 'Sync complete. %d record(s) updated.', $updated ),
    ));
}
```

---

### 7. Bot Interaction Handler

**Pro file:** `includes/class-wc-telegram-bot-interaction-handler.php`
**Lite equivalent:** `includes/class-subscriber-manager-lite-wctlgm-bot-interaction-handler.php`

#### Modify `process_join_request()`

After the join request is approved, track in the database:

```php
// Track in custom database.
Database::get_or_create_user( $user_id, $user_data );

// Find existing record by invite link and update, or create for external invites.
$existing = Database::find_by_invite_link( $invite_link, $chat_id );
if ( $existing ) {
    Database::update_channel_record(
        $existing->id,
        array(
            'telegram_user_id'  => $user_id,
            'status'            => 'active',
            'joined_at'         => current_time( 'mysql' ),
            'invite_revoked_at' => current_time( 'mysql' ),
        )
    );
} else {
    // External invite — no existing record.
    Database::add_user_channel(
        array(
            'telegram_user_id'  => $user_id,
            'channel_id'        => $chat_id,
            'invite_link'       => $invite_link,
            'invite_revoked_at' => current_time( 'mysql' ),
            'status'            => 'active',
            'joined_at'         => current_time( 'mysql' ),
        )
    );
}
```

The `$user_data` comes from `extract_user_data()`, which extracts `telegram_username`, `first_name`, `last_name` from the Telegram webhook data. If this helper doesn't exist in the lite plugin, add it:

```php
private function extract_user_data( $from ) {
    return array(
        'telegram_username' => isset( $from['username'] ) ? sanitize_text_field( $from['username'] ) : null,
        'first_name'        => isset( $from['first_name'] ) ? sanitize_text_field( $from['first_name'] ) : null,
        'last_name'         => isset( $from['last_name'] ) ? sanitize_text_field( $from['last_name'] ) : null,
    );
}
```

#### Add `process_chat_member_update()`

This is a new method that handles Telegram `chat_member` webhook events (user left, kicked, rejoined, etc.):

```php
protected function process_chat_member_update( $data ) {
    $chat_id    = sanitize_text_field( $data['chat_member']['chat']['id'] );
    $user_id    = sanitize_text_field( $data['chat_member']['new_chat_member']['user']['id'] );
    $new_status = sanitize_text_field( $data['chat_member']['new_chat_member']['status'] );
    $user_data  = $this->extract_user_data( $data['chat_member']['new_chat_member']['user'] );

    // Update user data if we know this user.
    $existing_user = Database::get_user( $user_id );
    if ( $existing_user ) {
        Database::update_user( $user_id, $user_data );
    }

    // Map Telegram status to our DB status.
    switch ( $new_status ) {
        case 'left':
            // GUARD: Don't overwrite admin-set statuses (removed/banned).
            $channel = Database::get_user_channel( $user_id, $chat_id );
            if ( ! $channel || ! in_array( $channel->status, array( 'removed', 'banned' ), true ) ) {
                Database::update_user_channel_status( $user_id, $chat_id, 'left' );
            }
            break;
        case 'kicked':
            // GUARD: Don't overwrite 'removed' with 'banned'.
            $channel = Database::get_user_channel( $user_id, $chat_id );
            if ( ! $channel || 'removed' !== $channel->status ) {
                Database::update_user_channel_status( $user_id, $chat_id, 'banned' );
            }
            break;
        case 'member':
        case 'administrator':
        case 'creator':
            // GUARD: Only re-activate from 'left' status.
            $channel = Database::get_user_channel( $user_id, $chat_id );
            if ( $channel && 'left' === $channel->status ) {
                Database::update_user_channel_status( $user_id, $chat_id, 'active' );
            }
            break;
    }

    return array( 'action' => 'none' );
}
```

**Why these guards matter:** When an admin removes a user via the modal (using `unbanChatMember`), Telegram sends a `left` webhook. Without the guard, the `removed` status would be overwritten by `left`. Similarly, `banChatMember` can trigger a `kicked` then `left` sequence. The guards preserve admin intent.

#### Route `chat_member` Updates

In `process_telegram_request()`, add routing for the `chat_member` update type:

```php
if ( isset( $data['chat_member'] ) ) {
    return $this->process_chat_member_update( $data );
}
```

**Note:** The Telegram webhook must be configured with `allowed_updates` that includes `chat_member`. Check if the lite plugin's webhook setup already includes this. If not, the `handle_set_webhook_actions()` method in the API handler needs to pass `allowed_updates` when setting the webhook:

```php
$allowed_updates = array( 'message', 'chat_join_request', 'chat_member' );
```

---

### 8. Order Handler

**Pro file:** `includes/class-wc-telegram-order-handler.php`
**Lite equivalent:** `includes/class-subscriber-manager-lite-wctlgm-order-handler.php`

#### Modify `generate_and_store_invites()`

After generating invite links, create DB records:

```php
foreach ( $response['channels'] as $invite ) {
    $order->add_meta_data( '_channel_invite_' . $invite['channel_id'], sanitize_url( $invite['invite_link'] ) );

    // Track in custom database as pending until join is confirmed by webhook.
    $telegram_user_id = $order->get_meta( '_telegram_user_id', true );
    Database::add_user_channel(
        array(
            'telegram_user_id' => ! empty( $telegram_user_id ) ? $telegram_user_id : null,
            'channel_id'       => $invite['channel_id'],
            'order_id'         => $order_id,
            'invite_link'      => $invite['invite_link'],
            'invite_issued_at' => current_time( 'mysql' ),
            'status'           => 'pending',
        )
    );
}
```

**Important:** Status is always `pending` here, even if the order has a `telegram_user_id`. The status changes to `active` when the user actually joins via the `chat_join_request` webhook.

---

### 9. Subscriptions Handler (If Applicable)

**Pro file:** `includes/class-wc-telegram-subscriptions-handler-base.php`
**Lite equivalent:** `includes/class-subscriber-manager-lite-wctlgm-subscriptions-handler.php`

The lite plugin has a single subscriptions handler (no factory pattern). If the lite plugin supports user removal (e.g., via `remove_user_from_telegram_channels()`), add the terminal status preservation guard:

```php
private static function remove_user_from_telegram_channels( $telegram_user_id, $channel_ids ) {
    // ...existing removal logic...
    foreach ( $channel_ids as $channel_id ) {
        $api_handler->unban_user_from_channel( $telegram_user_id, $channel_id );

        // Only set 'removed' if the caller hasn't already set a terminal status.
        $record = Database::get_user_channel( $telegram_user_id, $channel_id );
        if ( ! $record || ! in_array( $record->status, array( 'expired', 'banned' ), true ) ) {
            Database::update_user_channel_status( $telegram_user_id, $channel_id, 'removed' );
        }
    }
}
```

If the lite plugin doesn't have this method at all, it can be skipped — users would only be managed via the admin UI.

---

### 10. API Handler — Required Methods

Verify the lite plugin's API handler has these methods. If missing, add them:

#### `get_chat_member_status( $user_id, $channel_id )`

Calls Telegram's `getChatMember` API with a 5-minute transient cache:

```php
public function get_chat_member_status( $user_id, $channel_id ) {
    $cache_key = 'wctlgm_member_' . md5( $user_id . '_' . $channel_id );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $response = $this->make_api_request( 'getChatMember', array(
        'chat_id' => $channel_id,
        'user_id' => $user_id,
    ));

    if ( is_wp_error( $response ) ) {
        return 'error';
    }

    $status = $response['result']['status'] ?? 'error';
    set_transient( $cache_key, $status, 5 * MINUTE_IN_SECONDS );
    return $status;
}
```

**Critical:** The cache key format (`'wctlgm_member_' . md5( $user_id . '_' . $channel_id )`) must match between `get_chat_member_status()` and `ajax_sync_user_status()` (which clears the cache). Note the underscore separator in the `md5()` input.

#### `unban_user_from_channel( $user_id, $channel_id )`

Calls Telegram's `unbanChatMember` API (removes user without banning):

```php
public function unban_user_from_channel( $user_id, $channel_id ) {
    return $this->make_api_request( 'unbanChatMember', array(
        'chat_id' => $channel_id,
        'user_id' => $user_id,
    ));
}
```

#### `remove_user_from_channel( $user_id, $channel_id )`

Calls Telegram's `banChatMember` API (permanent ban):

```php
public function remove_user_from_channel( $user_id, $channel_id ) {
    return $this->make_api_request( 'banChatMember', array(
        'chat_id' => $channel_id,
        'user_id' => $user_id,
    ));
}
```

#### `revoke_invite_link( $channel_id, $invite_link )`

Calls Telegram's `revokeChatInviteLink` API:

```php
public function revoke_invite_link( $channel_id, $invite_link ) {
    return $this->make_api_request( 'revokeChatInviteLink', array(
        'chat_id'     => $channel_id,
        'invite_link' => $invite_link,
    ));
}
```

---

## Status Values Reference

| Status | Meaning | Set By |
|--------|---------|--------|
| `pending` | Invite issued, user hasn't joined yet | Order handler (invite generation) |
| `active` | User is a member of the channel | Join request webhook, sync, modal live check |
| `left` | User voluntarily left the channel | `chat_member` webhook (`left` status) |
| `removed` | Admin removed user via modal (can rejoin) | Admin action (Remove/Unban button) |
| `banned` | Admin banned user via modal (permanent) | Admin action (Ban button) |
| `expired` | Access expired (subscription or simple product expiry) | Subscription handler / expiry scheduler |

---

## Webhook Guards Summary

These guards prevent webhook-driven status updates from overwriting admin-set or system-set statuses:

| Webhook Status | Guard | Reason |
|---------------|-------|--------|
| `left` | Skip if current status is `removed` or `banned` | `unbanChatMember` triggers a `left` webhook |
| `kicked` | Skip if current status is `removed` | Admin Remove calls `unbanChatMember` which may trigger `kicked` first |
| `member`/`administrator`/`creator` | Only activate if current status is `left` | Don't overwrite `removed`, `banned`, or `expired` |

---

## Files Summary

| New Files | Description |
|-----------|-------------|
| `includes/class-subscriber-manager-lite-wctlgm-database.php` | Custom tables, CRUD, migration, table queries |
| `includes/class-subscriber-manager-lite-wctlgm-users-list-table.php` | WP_List_Table extension for admin table |
| `assets/js/wctlgm-admin-users.js` | Tab switching, modal, AJAX actions, sync |
| `assets/css/wctlgm-admin-users.css` | All subscriber table styling |

| Modified Files | Changes |
|----------------|---------|
| Main plugin file | Require new files, init DB, activation hook |
| Settings class | Add 3 AJAX handlers, enqueue JS/CSS, add Subscribers tab + modal, add `users_page()` |
| Bot interaction handler | Add DB tracking to `process_join_request()`, add new `process_chat_member_update()`, route `chat_member` updates |
| Order handler | Add DB record creation in `generate_and_store_invites()` |
| API handler | Ensure `get_chat_member_status()`, `unban_user_from_channel()`, `remove_user_from_channel()`, `revoke_invite_link()` methods exist |
| Subscriptions handler (optional) | Add terminal status guard in removal logic |
