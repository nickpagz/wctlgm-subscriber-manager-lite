<?php

use Brain\Monkey\Functions;
use Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler;

class SubscriptionsHandlerTest extends WCTLGM_Lite_TestCase {

	/**
	 * @test
	 */
	public function process_activation_code_with_valid_code_returns_result() {
		$order = $this->create_mock_order(
			array(
				'id'     => 100,
				'status' => 'completed',
				'meta'   => array(
					'_activation_code'              => 'ValidCode',
					'_channel_invite_-1001234567890' => '',
				),
				'items'  => array( $this->create_mock_item( 456 ) ),
			)
		);
		$order->shouldReceive( 'update_meta_data' )->with( '_telegram_user_id', '67890' );
		$order->shouldReceive( 'delete_meta_data' )->with( '_activation_code', 'ValidCode' );
		$order->shouldReceive( 'add_meta_data' );
		$order->shouldReceive( 'save' );

		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->process_activation_code( 'ValidCode', '67890' );

		$this->assertIsArray( $result );
		$this->assertSame( 100, $result[1] ); // Order ID.
		$this->assertTrue( $result[0]['success'] ); // Response success.
	}

	/**
	 * @test
	 *
	 * Regression (M2): when invite generation fails, the one-time activation
	 * code must be preserved — no delete_meta_data, no _telegram_user_id
	 * update, and no save — so the customer can retry rather than being left
	 * "activated" with no access and a burned code.
	 */
	public function process_activation_code_preserves_code_on_invite_failure() {
		$order = $this->create_mock_order(
			array(
				'id'     => 100,
				'status' => 'completed',
				'meta'   => array( '_activation_code' => 'ValidCode' ),
				'items'  => array( $this->create_mock_item( 456 ) ),
			)
		);

		// The code must NOT be consumed and the order must NOT be mutated/saved.
		$order->shouldNotReceive( 'delete_meta_data' );
		$order->shouldNotReceive( 'update_meta_data' );
		$order->shouldNotReceive( 'save' );

		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );
		// No channel IDs configured → get_channel_invites() returns success=false.
		Functions\when( 'get_post_meta' )->justReturn( array() );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->process_activation_code( 'ValidCode', '67890' );

		// Returns the failure response + order ID so the caller can surface
		// "Activation failed" while leaving the code intact for a retry.
		$this->assertIsArray( $result );
		$this->assertSame( 100, $result[1] );
		$this->assertFalse( $result[0]['success'] );
	}

	/**
	 * @test
	 */
	public function process_activation_code_with_invalid_code_returns_false() {
		// No matching orders.
		Functions\when( 'wc_get_orders' )->justReturn( array() );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->process_activation_code( 'BadCode', '67890' );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function process_activation_code_with_pending_order_returns_false() {
		$order = $this->create_mock_order(
			array(
				'id'     => 100,
				'status' => 'pending',
			)
		);
		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->process_activation_code( 'SomeCode', '67890' );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function process_activation_code_with_multiple_orders_returns_false() {
		$order1 = $this->create_mock_order( array( 'id' => 100 ) );
		$order2 = $this->create_mock_order( array( 'id' => 101 ) );
		Functions\when( 'wc_get_orders' )->justReturn( array( $order1, $order2 ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->process_activation_code( 'DuplicateCode', '67890' );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function is_join_request_valid_with_activation_flow_matching_user() {
		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => true ) );

		$order = $this->create_mock_order(
			array(
				'id'        => 100,
				'status'    => 'completed',
				'meta'      => array(
					'_telegram_user_id'              => '67890',
					'_channel_invite_-1001234567890' => 'https://t.me/+valid',
				),
				'items'     => array( $this->create_mock_item( 456 ) ),
				'meta_data' => array(
					$this->create_mock_meta( '_channel_invite_-1001234567890', 'https://t.me/+valid' ),
				),
			)
		);

		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->is_join_request_valid( '67890', 'https://t.me/+valid', '-1001234567890' );

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function is_join_request_valid_with_activation_flow_wrong_user() {
		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => true ) );

		$order = $this->create_mock_order(
			array(
				'id'        => 100,
				'status'    => 'completed',
				'meta'      => array(
					'_telegram_user_id'              => '67890',
					'_channel_invite_-1001234567890' => 'https://t.me/+valid',
				),
				'items'     => array( $this->create_mock_item( 456 ) ),
				'meta_data' => array(
					$this->create_mock_meta( '_channel_invite_-1001234567890', 'https://t.me/+valid' ),
				),
			)
		);

		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->is_join_request_valid( '99999', 'https://t.me/+valid', '-1001234567890' );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function is_join_request_valid_without_activation_captures_user_id() {
		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => false ) );

		$order = $this->create_mock_order(
			array(
				'id'        => 100,
				'status'    => 'completed',
				'meta'      => array(
					'_telegram_user_id'              => '',
					'_channel_invite_-1001234567890' => 'https://t.me/+valid',
				),
				'items'     => array( $this->create_mock_item( 456 ) ),
				'meta_data' => array(
					$this->create_mock_meta( '_channel_invite_-1001234567890', 'https://t.me/+valid' ),
				),
			)
		);
		$order->shouldReceive( 'update_meta_data' )
			->with( '_telegram_user_id', '67890' )
			->once();
		$order->shouldReceive( 'save' )->once();

		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->is_join_request_valid( '67890', 'https://t.me/+valid', '-1001234567890' );

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function is_join_request_valid_rejects_user_id_overwrite() {
		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => false ) );

		$order = $this->create_mock_order(
			array(
				'id'        => 100,
				'status'    => 'completed',
				'meta'      => array(
					'_telegram_user_id'              => '67890',
					'_channel_invite_-1001234567890' => 'https://t.me/+valid',
				),
				'items'     => array( $this->create_mock_item( 456 ) ),
				'meta_data' => array(
					$this->create_mock_meta( '_channel_invite_-1001234567890', 'https://t.me/+valid' ),
				),
			)
		);

		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		// Different user trying to use the same invite link.
		$result = $handler->is_join_request_valid( '99999', 'https://t.me/+valid', '-1001234567890' );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function is_join_request_valid_allows_external_invites_when_no_order() {
		Functions\when( 'wc_get_orders' )->justReturn( array() );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->is_join_request_valid( '67890', 'https://t.me/+external', '-1001234567890', true );

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function is_join_request_valid_no_order_returns_false() {
		Functions\when( 'wc_get_orders' )->justReturn( array() );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$result  = $handler->is_join_request_valid( '67890', 'https://t.me/+unknown', '-1001234567890', false );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function get_channel_invites_generates_new_links() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
				'meta'  => array(
					'_channel_invite_-1001234567890' => '',
				),
			)
		);

		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$response = $handler->get_channel_invites( $order );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $response['channels'] );
		$this->assertSame( '-1001234567890', $response['channels'][0]['channel_id'] );
		$this->assertSame( 'https://t.me/+test_invite', $response['channels'][0]['invite_link'] );
	}

	/**
	 * @test
	 */
	public function get_channel_invites_reuses_existing_links() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
				'meta'  => array(
					'_channel_invite_-1001234567890' => 'https://t.me/+existing_link',
				),
			)
		);

		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$response = $handler->get_channel_invites( $order );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'https://t.me/+existing_link', $response['channels'][0]['invite_link'] );
	}

	/**
	 * @test
	 */
	public function get_channel_invites_uses_variation_id_for_meta_lookup() {
		$variation_id = 789;
		$product_id   = 456;

		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( $product_id, $variation_id ) ),
				'meta'  => array(
					'_channel_invite_-1001234567890' => '',
				),
			)
		);

		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) use ( $variation_id ) {
				// Should be called with variation ID, not product ID.
				if ( $id === $variation_id && '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$response = $handler->get_channel_invites( $order );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $response['channels'] );
		$this->assertSame( '-1001234567890', $response['channels'][0]['channel_id'] );
	}

	/**
	 * @test
	 */
	public function get_channel_invites_returns_failure_when_no_channels() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
			)
		);

		Functions\when( 'get_post_meta' )->justReturn( array() ); // No channel IDs.

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$response = $handler->get_channel_invites( $order );

		$this->assertFalse( $response['success'] );
		$this->assertEmpty( $response['channels'] );
	}
}
