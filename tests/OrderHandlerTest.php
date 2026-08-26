<?php

use Brain\Monkey\Functions;
use Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Order_Handler;

class OrderHandlerTest extends WCTLGM_Lite_TestCase {

	/**
	 * @test
	 */
	public function maybe_process_order_returns_when_order_not_found() {
		Functions\when( 'wc_get_order' )->justReturn( null );

		// Should return without error.
		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 999, 'pending', 'processing' );
		$this->assertTrue( true ); // No exception means pass.
	}

	/**
	 * @test
	 */
	public function maybe_process_order_skips_non_telegram_products() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
			)
		);
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$product = $this->create_mock_product();
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->justReturn( '' ); // No channel IDs.

		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'pending', 'processing' );
		$this->assertTrue( true );
	}

	/**
	 * @test
	 */
	public function maybe_process_order_skips_unsupported_product_types() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
			)
		);
		Functions\when( 'wc_get_order' )->justReturn( $order );

		// Subscription product type — not supported in lite.
		$product = $this->create_mock_product( array( 'type' => 'subscription' ) );
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'pending', 'processing' );
		$this->assertTrue( true );
	}

	/**
	 * @test
	 */
	public function maybe_process_order_skips_invalid_status() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
			)
		);
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$product = $this->create_mock_product();
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		// on-hold is not a valid trigger status.
		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'pending', 'on-hold' );
		$this->assertTrue( true );
	}

	/**
	 * @test
	 */
	public function maybe_process_order_skips_processing_to_completed() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
			)
		);
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$product = $this->create_mock_product();
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		// processing -> completed should be skipped (already processed).
		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'processing', 'completed' );
		$this->assertTrue( true );
	}

	/**
	 * @test
	 *
	 * Regression (M1): an order line item whose product was later deleted
	 * (wc_get_product() returns false) but whose _telegram_channel_ids meta
	 * persists must not fatal on is_type() during order_has_telegram_product().
	 */
	public function maybe_process_order_handles_deleted_product() {
		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
			)
		);
		Functions\when( 'wc_get_order' )->justReturn( $order );

		// Product was deleted — wc_get_product returns false.
		Functions\when( 'wc_get_product' )->justReturn( false );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		// Should return without a fatal error (guard skips the deleted product).
		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'pending', 'processing' );
		$this->assertTrue( true );
	}

	/**
	 * @test
	 */
	public function maybe_process_order_generates_activation_code_when_required() {
		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => true ) );

		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
				'meta'  => array( '_activation_code' => '' ),
			)
		);

		// Expect activation code to be generated.
		$order->shouldReceive( 'update_meta_data' )
			->with( '_activation_code', 'TestCode' )
			->once();
		$order->shouldReceive( 'save' )->once();

		Functions\when( 'wc_get_order' )->justReturn( $order );

		$product = $this->create_mock_product();
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'pending', 'processing' );
		// Mockery verifies update_meta_data and save were called in tearDown.
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @test
	 */
	public function maybe_process_order_skips_existing_activation_code() {
		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => true ) );

		$order = $this->create_mock_order(
			array(
				'id'    => 100,
				'items' => array( $this->create_mock_item( 456 ) ),
				'meta'  => array( '_activation_code' => 'ExistingCode' ),
			)
		);

		// Should NOT call update_meta_data with _activation_code.
		$order->shouldNotReceive( 'update_meta_data' )->with( '_activation_code', \Mockery::any() );

		Functions\when( 'wc_get_order' )->justReturn( $order );

		$product = $this->create_mock_product();
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'pending', 'processing' );
		// Mockery verifies shouldNotReceive in tearDown.
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @test
	 */
	public function maybe_process_order_generates_invites_when_no_activation() {
		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => false ) );

		$order = $this->create_mock_order(
			array(
				'id'        => 100,
				'items'     => array( $this->create_mock_item( 456 ) ),
				'meta_data' => array(), // No existing invite meta.
			)
		);
		// Invite storage expectations.
		$order->shouldReceive( 'add_meta_data' )->atLeast()->once();
		$order->shouldReceive( 'save' )->atLeast()->once();

		Functions\when( 'wc_get_order' )->justReturn( $order );

		$product = $this->create_mock_product();
		Functions\when( 'wc_get_product' )->justReturn( $product );
		Functions\when( 'get_post_meta' )->alias(
			function ( $id, $key, $single = false ) {
				if ( '_telegram_channel_ids' === $key ) {
					return array( '-1001234567890' );
				}
				return '';
			}
		);

		Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order( 100, 'pending', 'completed' );
		// Mockery verifies add_meta_data and save were called in tearDown.
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @test
	 */
	public function generate_activation_code_stores_code_on_order() {
		$order = $this->create_mock_order( array( 'id' => 100 ) );

		Functions\when( 'wp_generate_password' )->justReturn( 'Abc12345' );

		$order->shouldReceive( 'update_meta_data' )
			->with( '_activation_code', 'Abc12345' )
			->once();
		$order->shouldReceive( 'save' )->once();

		$result = Subscriber_Manager_Lite_WCTLGM_Order_Handler::generate_activation_code( $order );
		$this->assertTrue( $result );
	}
}
