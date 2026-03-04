<?php

namespace Subscriber_Manager_Lite_for_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Database
 *
 * Handles database schema creation, migrations, and CRUD operations
 * for Telegram user management custom tables.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Database {

	/**
	 * Database schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Initialize the database handler.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_tables_and_migrate' ) );
	}

	/**
	 * Check if database tables need creation or migration.
	 * Runs on admin_init to handle plugin updates and fresh installs.
	 */
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

	/**
	 * Create custom tables for Telegram user management.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$users_table     = $wpdb->prefix . 'wctlgm_telegram_users';
		$channels_table  = $wpdb->prefix . 'wctlgm_user_channels';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE $users_table (
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
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE $channels_table (
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
		) $charset_collate;";
		dbDelta( $sql );
	}

	// ──────────────────────────────────────────────────────────
	// User CRUD
	// ──────────────────────────────────────────────────────────

	/**
	 * Get or create a Telegram user record.
	 *
	 * @param string $telegram_user_id The Telegram user ID.
	 * @param array  $data Optional user data (telegram_username, first_name, last_name).
	 * @return int The user record ID.
	 */
	public static function get_or_create_user( $telegram_user_id, $data = array() ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'wctlgm_telegram_users';
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE telegram_user_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$telegram_user_id
			)
		);

		if ( $user_id ) {
			if ( ! empty( $data ) ) {
				self::update_user( $telegram_user_id, $data );
			}
			return (int) $user_id;
		}

		$insert_data = array( 'telegram_user_id' => $telegram_user_id );
		foreach ( array( 'telegram_username', 'first_name', 'last_name' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$insert_data[ $field ] = $data[ $field ];
			}
		}

		$wpdb->insert( $table, $insert_data );
		return $wpdb->insert_id;
	}

	/**
	 * Update a Telegram user record.
	 *
	 * @param string $telegram_user_id The Telegram user ID.
	 * @param array  $data Fields to update.
	 * @return bool Whether the update succeeded.
	 */
	public static function update_user( $telegram_user_id, $data ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'wctlgm_telegram_users';
		$update_data = array();

		foreach ( array( 'telegram_username', 'first_name', 'last_name' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		return false !== $wpdb->update(
			$table,
			$update_data,
			array( 'telegram_user_id' => $telegram_user_id )
		);
	}

	/**
	 * Get a Telegram user by their Telegram user ID.
	 *
	 * @param string $telegram_user_id The Telegram user ID.
	 * @return object|null The user record or null.
	 */
	public static function get_user( $telegram_user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_telegram_users';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE telegram_user_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$telegram_user_id
			)
		);
	}

	// ──────────────────────────────────────────────────────────
	// User-Channel Relationship CRUD
	// ──────────────────────────────────────────────────────────

	/**
	 * Add a user-channel relationship.
	 *
	 * For order-based records, uses (order_id, channel_id) as the logical key.
	 * For external invites (no order_id), uses (telegram_user_id, channel_id).
	 *
	 * @param array $data Record data. Required: channel_id. Optional: telegram_user_id, order_id, invite_link, status, etc.
	 * @return int|false The record ID or false on failure.
	 */
	public static function add_user_channel( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		// Check for existing record to avoid duplicates.
		$existing = self::find_user_channel_record( $data );
		if ( $existing ) {
			// Update existing record.
			$update_data = array();
			foreach ( array( 'telegram_user_id', 'invite_link', 'invite_issued_at', 'joined_at', 'left_at', 'status' ) as $field ) {
				if ( isset( $data[ $field ] ) ) {
					$update_data[ $field ] = $data[ $field ];
				}
			}
			if ( ! empty( $update_data ) ) {
				$wpdb->update( $table, $update_data, array( 'id' => $existing->id ) );
			}
			return (int) $existing->id;
		}

		$insert_data = array(
			'channel_id' => $data['channel_id'],
			'status'     => isset( $data['status'] ) ? $data['status'] : 'pending',
		);

		foreach ( array( 'telegram_user_id', 'order_id', 'invite_link', 'invite_issued_at', 'invite_revoked_at', 'joined_at', 'left_at' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$insert_data[ $field ] = $data[ $field ];
			}
		}

		$wpdb->insert( $table, $insert_data );
		return $wpdb->insert_id;
	}

	/**
	 * Find an existing user-channel record by its logical key.
	 *
	 * For order-based: (order_id, channel_id).
	 * For external: (telegram_user_id, channel_id) WHERE order_id IS NULL.
	 *
	 * @param array $data Must contain channel_id and either order_id or telegram_user_id.
	 * @return object|null The record or null.
	 */
	public static function find_user_channel_record( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		if ( ! empty( $data['order_id'] ) ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE order_id = %d AND channel_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$data['order_id'],
					$data['channel_id']
				)
			);
		}

		if ( ! empty( $data['telegram_user_id'] ) ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE telegram_user_id = %s AND channel_id = %s AND order_id IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$data['telegram_user_id'],
					$data['channel_id']
				)
			);
		}

		return null;
	}

	/**
	 * Update user-channel status.
	 *
	 * @param string $telegram_user_id The Telegram user ID.
	 * @param string $channel_id The channel ID.
	 * @param string $status The new status.
	 * @param array  $extra Additional data to update.
	 * @return bool Whether the update succeeded.
	 */
	public static function update_user_channel_status( $telegram_user_id, $channel_id, $status, $extra = array() ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'wctlgm_user_channels';
		$update_data = array( 'status' => $status );

		if ( 'active' === $status && ! isset( $extra['joined_at'] ) ) {
			// Only set joined_at when it hasn't been set yet, to preserve the original join timestamp.
			$existing = self::get_user_channel( $telegram_user_id, $channel_id );
			if ( ! $existing || empty( $existing->joined_at ) ) {
				$update_data['joined_at'] = current_time( 'mysql' );
			}
		} elseif ( in_array( $status, array( 'left', 'removed', 'banned', 'expired' ), true ) && ! isset( $extra['left_at'] ) ) {
			$update_data['left_at'] = current_time( 'mysql' );
		}

		$update_data = array_merge( $update_data, $extra );

		// Check if an active record exists for this user+channel.
		$has_active = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM `{$table}` WHERE telegram_user_id = %s AND channel_id = %s AND status = 'active' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$telegram_user_id,
				$channel_id
			)
		);

		if ( $has_active ) {
			// Update only the active record. A no-op update (0 rows affected) is still a success.
			$result = $wpdb->update(
				$table,
				$update_data,
				array(
					'telegram_user_id' => $telegram_user_id,
					'channel_id'       => $channel_id,
					'status'           => 'active',
				)
			);
		} else {
			// No active record exists; fall back to updating any record for this user+channel.
			$result = $wpdb->update(
				$table,
				$update_data,
				array(
					'telegram_user_id' => $telegram_user_id,
					'channel_id'       => $channel_id,
				)
			);
		}

		return false !== $result;
	}

	/**
	 * Update channel records by order ID to link a Telegram user.
	 *
	 * Called when a user activates or joins via an invite link tied to an order.
	 *
	 * @param int    $order_id The WooCommerce order ID.
	 * @param string $telegram_user_id The Telegram user ID.
	 * @return int Number of rows updated.
	 */
	public static function link_user_to_order_channels( $order_id, $telegram_user_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'wctlgm_user_channels';
		$result = $wpdb->update(
			$table,
			array( 'telegram_user_id' => $telegram_user_id ),
			array( 'order_id' => $order_id )
		);

		return $wpdb->rows_affected;
	}

	/**
	 * Get a single user-channel record.
	 *
	 * @param string $telegram_user_id The Telegram user ID.
	 * @param string $channel_id The channel ID.
	 * @return object|null The record or null.
	 */
	public static function get_user_channel( $telegram_user_id, $channel_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE telegram_user_id = %s AND channel_id = %s ORDER BY status = 'active' DESC, created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$telegram_user_id,
				$channel_id
			)
		);
	}

	/**
	 * Get all channel relationships for a user.
	 *
	 * @param string $telegram_user_id The Telegram user ID.
	 * @return array Array of relationship records.
	 */
	public static function get_user_channels( $telegram_user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE telegram_user_id = %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$telegram_user_id
			)
		);
	}

	/**
	 * Get channel records by order ID.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return array Array of channel records.
	 */
	public static function get_channels_by_order( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE order_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);
	}

	/**
	 * Find a channel record by invite link.
	 *
	 * @param string $invite_link The invite link URL.
	 * @param string $channel_id The channel ID.
	 * @return object|null The record or null.
	 */
	public static function find_by_invite_link( $invite_link, $channel_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE invite_link = %s AND channel_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$invite_link,
				$channel_id
			)
		);
	}

	/**
	 * Update invite link fields on a channel record.
	 *
	 * @param int   $record_id The record ID.
	 * @param array $data Fields to update.
	 * @return bool Whether the update succeeded.
	 */
	public static function update_channel_record( $record_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		return false !== $wpdb->update( $table, $data, array( 'id' => $record_id ) );
	}

	// ──────────────────────────────────────────────────────────
	// List Table Queries
	// ──────────────────────────────────────────────────────────

	/**
	 * Get subscriber rows for the list table.
	 *
	 * Returns one row per unique Telegram user, with aggregated channel/order data.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *     @type int    $per_page   Items per page. Default 20.
	 *     @type int    $page       Current page number. Default 1.
	 *     @type string $search     Search term. Default empty.
	 *     @type string $channel_id Filter by channel ID. Default empty.
	 *     @type string $orderby    Column to sort by. Default 'created_at'.
	 *     @type string $order      Sort direction. Default 'DESC'.
	 * }
	 * @return array Array of subscriber row objects.
	 */
	public static function get_subscribers_for_table( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page'   => 20,
			'page'       => 1,
			'search'     => '',
			'channel_id' => '',
			'orderby'    => 'created_at',
			'order'      => 'DESC',
		);
		$args     = wp_parse_args( $args, $defaults );

		$users_table    = $wpdb->prefix . 'wctlgm_telegram_users';
		$channels_table = $wpdb->prefix . 'wctlgm_user_channels';

		$where  = array();
		$values = array();

		// Channel filter.
		if ( ! empty( $args['channel_id'] ) ) {
			$where[]  = 'uc.channel_id = %s';
			$values[] = $args['channel_id'];
		}

		// Search.
		if ( ! empty( $args['search'] ) ) {
			$search = $args['search'];
			if ( is_numeric( $search ) ) {
				$where[]  = '( u.telegram_user_id = %s OR uc.order_id = %d )';
				$values[] = $search;
				$values[] = (int) $search;
			} else {
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$where[]  = '( u.telegram_username LIKE %s OR u.first_name LIKE %s OR u.last_name LIKE %s OR CONCAT(u.first_name, \' \', u.last_name) LIKE %s )';
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
			}
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Validate orderby.
		$allowed_orderby = array( 'telegram_user_id', 'telegram_username', 'first_name', 'created_at', 'joined_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

		// Map column to the right table alias.
		if ( 'joined_at' === $orderby ) {
			$orderby_col = 'earliest_joined';
		} elseif ( 'created_at' === $orderby ) {
			$orderby_col = 'u.created_at';
		} else {
			$orderby_col = 'u.' . $orderby;
		}

		$order  = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// Main query: one row per user, with their earliest join date and channel count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated columns.
		$sql = "SELECT u.*,
				GROUP_CONCAT(DISTINCT CONCAT(uc.channel_id, '|', uc.status) SEPARATOR ',') AS channel_status_pairs,
				GROUP_CONCAT(DISTINCT uc.order_id SEPARATOR ',') AS order_ids,
				MIN(uc.joined_at) AS earliest_joined,
				COUNT(DISTINCT uc.channel_id) AS channel_count
			FROM `{$users_table}` u
			LEFT JOIN `{$channels_table}` uc ON u.telegram_user_id = uc.telegram_user_id
			{$where_clause}
			GROUP BY u.telegram_user_id
			ORDER BY {$orderby_col} {$order}
			LIMIT %d OFFSET %d";

		$values[] = $args['per_page'];
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Count total subscribers for pagination.
	 *
	 * @param array $args Same args as get_subscribers_for_table (search, channel_id).
	 * @return int Total count.
	 */
	public static function count_subscribers( $args = array() ) {
		global $wpdb;

		$users_table    = $wpdb->prefix . 'wctlgm_telegram_users';
		$channels_table = $wpdb->prefix . 'wctlgm_user_channels';

		$where  = array();
		$values = array();

		if ( ! empty( $args['channel_id'] ) ) {
			$where[]  = 'uc.channel_id = %s';
			$values[] = $args['channel_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$search = $args['search'];
			if ( is_numeric( $search ) ) {
				$where[]  = '( u.telegram_user_id = %s OR uc.order_id = %d )';
				$values[] = $search;
				$values[] = (int) $search;
			} else {
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$where[]  = '( u.telegram_username LIKE %s OR u.first_name LIKE %s OR u.last_name LIKE %s OR CONCAT(u.first_name, \' \', u.last_name) LIKE %s )';
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
				$values[] = $like;
			}
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT COUNT(DISTINCT u.telegram_user_id) FROM `{$users_table}` u
			LEFT JOIN `{$channels_table}` uc ON u.telegram_user_id = uc.telegram_user_id
			{$where_clause}";

		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	// ──────────────────────────────────────────────────────────
	// Pending Invites (Orphaned Records)
	// ──────────────────────────────────────────────────────────

	/**
	 * Get pending channel records that have no telegram_user_id.
	 *
	 * These are orders with invites issued but the user hasn't joined via Telegram yet.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *     @type int    $per_page Items per page. Default 20.
	 *     @type int    $page     Current page. Default 1.
	 *     @type string $search   Search by order ID (numeric only).
	 * }
	 * @return array Array of pending record objects.
	 */
	public static function get_pending_records( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'search'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$table  = $wpdb->prefix . 'wctlgm_user_channels';
		$where  = array( 'telegram_user_id IS NULL', "status = 'pending'", 'order_id IS NOT NULL' );
		$values = array();

		if ( ! empty( $args['search'] ) && is_numeric( $args['search'] ) ) {
			$where[]  = 'order_id = %d';
			$values[] = (int) $args['search'];
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated WHERE clauses.
		$sql = "SELECT *
			FROM `{$table}`
			{$where_clause}
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d";

		$values[] = $args['per_page'];
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Count pending records without telegram_user_id.
	 *
	 * @return int Total count of orphaned pending invite records.
	 */
	public static function count_pending_records() {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$table}` WHERE telegram_user_id IS NULL AND status = 'pending' AND order_id IS NOT NULL" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Get a single user-channel record by its primary key.
	 *
	 * @param int $record_id The record ID.
	 * @return object|null The record or null.
	 */
	public static function get_channel_record( $record_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$record_id
			)
		);
	}

	// ──────────────────────────────────────────────────────────
	// Multi-Access Check
	// ──────────────────────────────────────────────────────────

	/**
	 * Check if a user has other active access to a channel (besides a specific record).
	 *
	 * @param string   $telegram_user_id The Telegram user ID.
	 * @param string   $channel_id The channel ID.
	 * @param int|null $exclude_order_id Order ID to exclude from the check.
	 * @return array Array of other active channel records.
	 */
	public static function get_other_active_channel_access( $telegram_user_id, $channel_id, $exclude_order_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wctlgm_user_channels';

		if ( $exclude_order_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE telegram_user_id = %s AND channel_id = %s AND status = 'active' AND order_id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$telegram_user_id,
					$channel_id,
					$exclude_order_id
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE telegram_user_id = %s AND channel_id = %s AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$telegram_user_id,
				$channel_id
			)
		);
	}

	// ──────────────────────────────────────────────────────────
	// Migration
	// ──────────────────────────────────────────────────────────

	/**
	 * Migrate existing data from order meta to custom tables.
	 *
	 * Scans all orders with _telegram_user_id meta and creates user + channel records.
	 * Also picks up orders with invite links but no Telegram user (pending).
	 *
	 * @return int Number of channel records created.
	 */
	public static function migrate_existing_data() {
		$migrated = 0;

		// Phase 1: Orders with Telegram user IDs (activated/joined users).
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => 'any',
				'meta_query' => array(
					array(
						'key'     => '_telegram_user_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $orders as $order ) {
			$migrated += self::migrate_single_order( $order );
		}

		// Phase 2: Orders with invite links but NO Telegram user ID (pending users).
		$pending_orders = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array( 'processing', 'completed' ),
				'meta_query' => array(
					array(
						'key'     => '_telegram_user_id',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $pending_orders as $order ) {
			$has_invite = false;
			foreach ( $order->get_meta_data() as $meta ) {
				if ( strpos( $meta->get_data()['key'], '_channel_invite_' ) === 0 ) {
					$has_invite = true;
					break;
				}
			}
			if ( $has_invite ) {
				$migrated += self::migrate_single_order( $order );
			}
		}

		Subscriber_Manager_Lite_WCTLGM_Logger::info(
			sprintf(
				/* translators: %d is the number of channel records migrated */
				__( 'Database migration completed. Created %d channel records.', 'wctlgm-subscriber-manager-lite' ),
				$migrated
			)
		);

		return $migrated;
	}

	/**
	 * Migrate a single order's data into custom tables.
	 *
	 * @param \WC_Order $order The order object.
	 * @return int Number of channel records created for this order.
	 */
	private static function migrate_single_order( $order ) {
		$telegram_user_id = $order->get_meta( '_telegram_user_id', true );
		$migrated         = 0;

		// Create user record if we have a Telegram user ID.
		if ( ! empty( $telegram_user_id ) ) {
			self::get_or_create_user( $telegram_user_id );
		}

		// Get invite links from order meta.
		$meta_data = $order->get_meta_data();
		foreach ( $meta_data as $meta ) {
			$meta_key   = $meta->get_data()['key'];
			$meta_value = $meta->get_data()['value'];

			if ( strpos( $meta_key, '_channel_invite_' ) !== 0 ) {
				continue;
			}

			$channel_id = str_replace( '_channel_invite_', '', $meta_key );
			$status     = self::determine_migration_status( $order, $telegram_user_id );

			$record_data = array(
				'channel_id'       => $channel_id,
				'order_id'         => $order->get_id(),
				'invite_link'      => $meta_value,
				'invite_issued_at' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
				'status'           => $status,
			);

			if ( ! empty( $telegram_user_id ) ) {
				$record_data['telegram_user_id'] = $telegram_user_id;
				if ( 'active' === $status ) {
					$record_data['joined_at'] = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null;
				}
			}

			self::add_user_channel( $record_data );
			++$migrated;
		}

		return $migrated;
	}

	/**
	 * Determine the status for a migrated record.
	 *
	 * @param \WC_Order $order The order object.
	 * @param string    $telegram_user_id The Telegram user ID (may be empty).
	 * @return string The status string.
	 */
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
}
