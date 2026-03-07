<?php

namespace Subscriber_Manager_Lite_for_Telegram;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Users_List_Table
 *
 * Displays Telegram subscribers in a WordPress admin list table.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Users_List_Table extends \WP_List_Table {

	/**
	 * Configured channels from plugin settings.
	 *
	 * @var array
	 */
	private $channels;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'subscriber',
				'plural'   => 'subscribers',
				'ajax'     => false,
			)
		);
		$this->channels = get_option( 'wctlgm_channels', array() );
	}

	/**
	 * Define table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'telegram_username' => __( 'Username', 'wctlgm-subscriber-manager-lite' ),
			'name'              => __( 'Name', 'wctlgm-subscriber-manager-lite' ),
			'channels'          => __( 'Channels/Groups', 'wctlgm-subscriber-manager-lite' ),
			'joined_at'         => __( 'Joined', 'wctlgm-subscriber-manager-lite' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'joined_at' => array( 'joined_at', true ),
		);
	}

	/**
	 * Extra controls above/below the table — channel filter dropdown.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || empty( $this->channels ) ) {
			return;
		}
		$current_channel = '';
		if ( isset( $_REQUEST['_wctlgm_table_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wctlgm_table_nonce'] ) ), 'wctlgm_subscriber_table' ) ) {
			$current_channel = isset( $_REQUEST['channel_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['channel_filter'] ) ) : '';
		}
		?>
		<div class="alignleft actions">
			<select name="channel_filter" id="wctlgm-channel-filter">
				<option value=""><?php esc_html_e( 'All Channels', 'wctlgm-subscriber-manager-lite' ); ?></option>
				<?php foreach ( $this->channels as $channel ) : ?>
					<option value="<?php echo esc_attr( $channel['id'] ); ?>" <?php selected( $current_channel, $channel['id'] ); ?>>
						<?php echo esc_html( $channel['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'wctlgm-subscriber-manager-lite' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare table items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$page     = $this->get_pagenum();
		$search   = '';
		$channel  = '';

		// Sorting/pagination params are safe read-only GET params from WP_List_Table
		// links — validate against an allow-list rather than requiring a nonce.
		$allowed_orderby = array( 'created_at', 'joined_at' );
		$orderby         = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, allow-listed
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
		$order           = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, validated below
		$order           = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order ) : 'DESC';

		// Search and channel filter require nonce (user-submitted form data).
		if ( isset( $_REQUEST['_wctlgm_table_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wctlgm_table_nonce'] ) ), 'wctlgm_subscriber_table' ) ) {
			$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
			$channel = isset( $_REQUEST['channel_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['channel_filter'] ) ) : '';
		}

		$args = array(
			'per_page'   => $per_page,
			'page'       => $page,
			'search'     => $search,
			'channel_id' => $channel,
			'orderby'    => $orderby,
			'order'      => $order,
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			$this->get_primary_column_name(),
		);

		$this->items = Subscriber_Manager_Lite_WCTLGM_Database::get_subscribers_for_table( $args );
		$total_items = Subscriber_Manager_Lite_WCTLGM_Database::count_subscribers( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Render username column with row actions.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_telegram_username( $item ) {
		$username = ! empty( $item->telegram_username ) ? '@' . esc_html( $item->telegram_username ) : '—';

		$actions = array(
			'view' => sprintf(
				'<a href="#" class="wctlgm-view-user" data-telegram-id="%s">%s</a>',
				esc_attr( $item->telegram_user_id ),
				__( 'View Details', 'wctlgm-subscriber-manager-lite' )
			),
			'sync' => sprintf(
				'<a href="#" class="wctlgm-sync-user" data-telegram-id="%s">%s</a>',
				esc_attr( $item->telegram_user_id ),
				__( 'Sync Status', 'wctlgm-subscriber-manager-lite' )
			),
		);

		return sprintf( '<strong>%s</strong>%s', $username, $this->row_actions( $actions ) );
	}

	/**
	 * Render name column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_name( $item ) {
		$name = trim( ( $item->first_name ?? '' ) . ' ' . ( $item->last_name ?? '' ) );
		return ! empty( $name ) ? esc_html( $name ) : '—';
	}

	/**
	 * Render channels column with per-channel status badges.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_channels( $item ) {
		if ( empty( $item->channel_status_pairs ) ) {
			return '—';
		}

		// Priority order: active is most relevant, then pending, etc.
		$status_priority = array(
			'active'  => 1,
			'pending' => 2,
			'left'    => 3,
			'expired' => 4,
			'removed' => 5,
			'banned'  => 6,
		);

		// Deduplicate: keep only the most relevant status per channel.
		$channels = array();
		$pairs    = explode( ',', $item->channel_status_pairs );

		foreach ( $pairs as $pair ) {
			$parts = explode( '|', $pair, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}

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

	/**
	 * Render joined date column.
	 *
	 * @param object $item Row data.
	 * @return string
	 */
	public function column_joined_at( $item ) {
		if ( empty( $item->earliest_joined ) ) {
			return '—';
		}

		return esc_html( wp_date( get_option( 'date_format' ), strtotime( $item->earliest_joined ) ) );
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item Row data.
	 * @param string $column_name Column identifier.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '—';
	}

	/**
	 * Message displayed when no subscribers found.
	 */
	public function no_items() {
		esc_html_e( 'No subscribers found.', 'wctlgm-subscriber-manager-lite' );
	}

	/**
	 * Get a human-readable label for a channel status.
	 *
	 * @param string $status The DB status value.
	 * @return string The display label.
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'active'  => __( 'Member', 'wctlgm-subscriber-manager-lite' ),
			'pending' => __( 'Pending', 'wctlgm-subscriber-manager-lite' ),
			'left'    => __( 'Left', 'wctlgm-subscriber-manager-lite' ),
			'removed' => __( 'Removed', 'wctlgm-subscriber-manager-lite' ),
			'banned'  => __( 'Banned', 'wctlgm-subscriber-manager-lite' ),
			'expired' => __( 'Expired', 'wctlgm-subscriber-manager-lite' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}

	/**
	 * Get the display name for a channel ID.
	 *
	 * @param string $channel_id The channel ID.
	 * @return string The channel name or truncated ID.
	 */
	private function get_channel_name( $channel_id ) {
		$channel_id = trim( $channel_id );
		foreach ( $this->channels as $channel ) {
			if ( trim( $channel['id'] ) === $channel_id ) {
				return $channel['name'];
			}
		}
		return $channel_id;
	}
}
