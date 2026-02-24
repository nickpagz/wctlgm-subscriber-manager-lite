<?php
/**
 * Base test case for wctlgm-subscriber-manager-lite tests.
 *
 * Sets up Brain\Monkey, stubs common WordPress functions,
 * and provides helper methods for creating mock WooCommerce objects.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;

class WCTLGM_Lite_TestCase extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Default stubs for functions that need per-test behavior.
		// Tests can override these with Functions\expect() or Functions\when().
		$this->mock_plugin_options();

		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'ok'     => true,
					'result' => array( 'invite_link' => 'https://t.me/+test_invite' ),
				)
			)
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wc_get_order' )->justReturn( null );
		Functions\when( 'wc_get_orders' )->justReturn( array() );
		Functions\when( 'wc_get_product' )->justReturn( null );
		Functions\when( 'wp_generate_password' )->justReturn( 'TestCode' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'as_schedule_single_action' )->justReturn( 1 );
		Functions\when( 'register_rest_route' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'current_time' )->justReturn( '2025-01-01 00:00:00' );
	}

	protected function tearDown(): void {
		// Reset static properties to prevent test pollution.
		$this->reset_static_property(
			'Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Logger',
			'logger'
		);

		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Reset a private static property to null (or a specified value).
	 */
	protected function reset_static_property( $class, $property, $value = null ) {
		try {
			$reflection = new \ReflectionClass( $class );
			$prop       = $reflection->getProperty( $property );
			$prop->setAccessible( true );
			$prop->setValue( null, $value );
		} catch ( \ReflectionException $e ) {
			// Class or property doesn't exist, skip.
		}
	}

	/**
	 * Set up get_option to return plugin options.
	 *
	 * @param array $overrides Key-value pairs to override defaults.
	 */
	protected function mock_plugin_options( $overrides = array() ) {
		$defaults = array(
			'wctlgm_bot_token'               => 'test_bot_token',
			'wctlgm_secret_token'             => 'test_secret_token',
			'wctlgm_require_activation_flow'  => false,
			'wctlgm_allow_external_invites'   => false,
			'wctlgm_channels'                 => array(
				array(
					'id'   => '-1001234567890',
					'name' => 'Test Channel',
				),
			),
			'wctlgm_bot_url'                  => 'https://t.me/test_bot',
		);

		$options = array_merge( $defaults, $overrides );

		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) use ( $options ) {
				return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
			}
		);
	}

	/**
	 * Create a Mockery mock of a WC_Order.
	 *
	 * @param array $args Order configuration.
	 * @return \Mockery\MockInterface
	 */
	protected function create_mock_order( $args = array() ) {
		$defaults = array(
			'id'           => 123,
			'status'       => 'completed',
			'meta'         => array(),
			'items'        => array(),
			'meta_data'    => array(),
			'date_created' => time(),
		);
		$args     = array_merge( $defaults, $args );

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( $args['id'] );
		$order->shouldReceive( 'get_status' )->andReturn( $args['status'] );
		$order->shouldReceive( 'update_meta_data' )->byDefault();
		$order->shouldReceive( 'delete_meta_data' )->byDefault();
		$order->shouldReceive( 'add_meta_data' )->byDefault();
		$order->shouldReceive( 'save' )->byDefault();
		$order->shouldReceive( 'get_items' )->andReturn( $args['items'] );
		$order->shouldReceive( 'get_meta_data' )->andReturn( $args['meta_data'] );

		// Set up specific meta values.
		foreach ( $args['meta'] as $key => $value ) {
			$order->shouldReceive( 'get_meta' )
				->with( $key, true )
				->andReturn( $value );
		}
		// Default for unspecified meta keys.
		$order->shouldReceive( 'get_meta' )->andReturn( '' )->byDefault();

		// Date created.
		if ( $args['date_created'] ) {
			$date = Mockery::mock( 'WC_DateTime' );
			$date->shouldReceive( 'getTimestamp' )->andReturn( $args['date_created'] );
			$order->shouldReceive( 'get_date_created' )->andReturn( $date );
		} else {
			$order->shouldReceive( 'get_date_created' )->andReturn( null );
		}

		// Additional order methods that might be called.
		$order->shouldReceive( 'get_order_key' )->andReturn( 'wc_order_test' )->byDefault();
		$order->shouldReceive( 'get_total' )->andReturn( '10.00' )->byDefault();
		$order->shouldReceive( 'get_currency' )->andReturn( 'USD' )->byDefault();
		$order->shouldReceive( 'get_billing_email' )->andReturn( 'test@test.com' )->byDefault();
		$order->shouldReceive( 'get_billing_first_name' )->andReturn( 'Test' )->byDefault();
		$order->shouldReceive( 'get_billing_last_name' )->andReturn( 'User' )->byDefault();
		$order->shouldReceive( 'get_billing_phone' )->andReturn( '' )->byDefault();

		return $order;
	}

	/**
	 * Create a Mockery mock of a WC_Order_Item_Product.
	 *
	 * Lite only supports simple products — no variation_id parameter.
	 *
	 * @param int $product_id The product ID.
	 * @return \Mockery\MockInterface
	 */
	protected function create_mock_item( $product_id ) {
		$item = Mockery::mock( 'WC_Order_Item_Product' );
		$item->shouldReceive( 'get_product_id' )->andReturn( $product_id );
		$item->shouldReceive( 'get_variation_id' )->andReturn( 0 );
		return $item;
	}

	/**
	 * Create a Mockery mock of a WC_Product.
	 *
	 * @param array $args Product configuration.
	 * @return \Mockery\MockInterface
	 */
	protected function create_mock_product( $args = array() ) {
		$defaults = array(
			'id'   => 456,
			'type' => 'simple',
		);
		$args     = array_merge( $defaults, $args );

		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_id' )->andReturn( $args['id'] );
		$product->shouldReceive( 'is_type' )->andReturnUsing(
			function ( $type ) use ( $args ) {
				if ( is_array( $type ) ) {
					return in_array( $args['type'], $type, true );
				}
				return $args['type'] === $type;
			}
		);

		return $product;
	}

	/**
	 * Create a mock meta data object for get_meta_data().
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 * @return \Mockery\MockInterface
	 */
	protected function create_mock_meta( $key, $value ) {
		$meta = Mockery::mock( 'WC_Meta_Data' );
		$meta->shouldReceive( 'get_data' )->andReturn(
			array(
				'key'   => $key,
				'value' => $value,
			)
		);
		return $meta;
	}

	/**
	 * Build a Telegram message data payload for testing.
	 *
	 * @param string $text     Message text.
	 * @param string $chat_id  Chat ID.
	 * @param string $user_id  User ID.
	 * @return array
	 */
	protected function build_telegram_message( $text, $chat_id = '12345', $user_id = '67890' ) {
		$data = array(
			'message' => array(
				'chat' => array(
					'id'   => $chat_id,
					'type' => 'private',
				),
				'from' => array( 'id' => $user_id ),
				'text' => $text,
			),
		);

		// Auto-detect bot commands.
		if ( strpos( $text, '/' ) === 0 ) {
			$parts  = explode( ' ', $text, 2 );
			$length = strlen( $parts[0] );

			$data['message']['entities'] = array(
				array(
					'type'   => 'bot_command',
					'offset' => 0,
					'length' => $length,
				),
			);
		}

		return $data;
	}
}
