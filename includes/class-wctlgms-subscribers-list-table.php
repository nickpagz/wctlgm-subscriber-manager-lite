<?php

namespace Subscriber_Manager_Lite_for_Telegram;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WCTLGMS_Subscribers_List_Table extends \WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Subscriber', 'wctlgm-subscriber-manager-lite' ),
			'plural'   => __( 'Subscribers', 'wctlgm-subscriber-manager-lite' ),
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'username'   => __( 'Username', 'wctlgm-subscriber-manager-lite' ),
			'user_id'    => __( 'Telegram User ID', 'wctlgm-subscriber-manager-lite' ),
			'channel'    => __( 'Channel', 'wctlgm-subscriber-manager-lite' ),
			'status'     => __( 'Status', 'wctlgm-subscriber-manager-lite' ),
			'expiry'     => __( 'Expiry', 'wctlgm-subscriber-manager-lite' ),
			'source'     => __( 'Source', 'wctlgm-subscriber-manager-lite' ),
			// Add more columns as needed
		);
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// For now, use mock data. Replace with real data fetching later.
		$data = array(
			array(
				'username' => '@john',
				'user_id'  => '123456789',
				'channel'  => 'MyChannel',
				'status'   => 'Active',
				'expiry'   => '2024-12-01',
				'source'   => 'Woo Order #123',
			),
			// Add more rows as needed
		);

		$this->items = $data;
	}

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}
} 