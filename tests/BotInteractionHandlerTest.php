<?php

use Brain\Monkey\Functions;
use Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler;

class BotInteractionHandlerTest extends WCTLGM_Lite_TestCase {

	/**
	 * @test
	 */
	public function start_command_with_no_args_returns_welcome() {
		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/start' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertSame( '12345', $response['chat_id'] );
		$this->assertStringContainsString( 'activation code', $response['text'] );
	}

	/**
	 * @test
	 */
	public function start_command_with_activation_code_delegates_to_activate() {
		// Set up a valid activation code scenario.
		$order = $this->create_mock_order(
			array(
				'id'     => 100,
				'status' => 'completed',
				'meta'   => array( '_activation_code' => 'TestCode' ),
				'items'  => array( $this->create_mock_item( 456 ) ),
			)
		);
		$order->shouldReceive( 'update_meta_data' )->with( '_telegram_user_id', '67890' );
		$order->shouldReceive( 'delete_meta_data' )->with( '_activation_code', 'TestCode' );
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

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/start TestCode' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'Activation successful', $response['text'] );
	}

	/**
	 * @test
	 */
	public function start_command_with_deep_link_format() {
		// Test activate_CODE format.
		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/start activate_InvalidCode' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		// With no matching order, activation fails.
		$this->assertStringContainsString( 'Activation failed', $response['text'] );
	}

	/**
	 * @test
	 */
	public function start_command_with_invalid_payload() {
		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/start !!' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'invalid or expired', $response['text'] );
	}

	/**
	 * @test
	 */
	public function activate_command_with_empty_code_returns_error() {
		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/activate' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'Please include your activation code', $response['text'] );
	}

	/**
	 * @test
	 */
	public function activate_command_with_invalid_code_returns_failure() {
		// No orders match the code.
		Functions\when( 'wc_get_orders' )->justReturn( array() );

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/activate BadCode' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'Activation failed', $response['text'] );
	}

	/**
	 * @test
	 */
	public function activate_command_with_valid_code_returns_success() {
		$order = $this->create_mock_order(
			array(
				'id'     => 100,
				'status' => 'completed',
				'meta'   => array( '_activation_code' => 'ValidCode' ),
				'items'  => array( $this->create_mock_item( 456 ) ),
			)
		);
		$order->shouldReceive( 'update_meta_data' );
		$order->shouldReceive( 'delete_meta_data' );
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

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/activate ValidCode' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'Activation successful', $response['text'] );
	}

	/**
	 * @test
	 */
	public function help_command_returns_help_text_with_site_name() {
		Functions\when( 'get_bloginfo' )->justReturn( 'My Test Site' );

		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data     = $this->build_telegram_message( '/help' );
		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'My Test Site', $response['text'] );
		$this->assertStringContainsString( '/activate', $response['text'] );
	}

	/**
	 * @test
	 */
	public function unknown_command_returns_invalid_message() {
		$handler = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data    = $this->build_telegram_message( '/unknown' );

		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'Invalid', $response['text'] );
	}

	/**
	 * @test
	 */
	public function no_message_returns_action_none() {
		$handler  = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$response = $handler->process_telegram_request( array() );

		$this->assertSame( 'none', $response['action'] );
	}

	/**
	 * @test
	 */
	public function non_private_chat_message_returns_action_none() {
		$handler = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data    = array(
			'message' => array(
				'chat' => array(
					'id'   => '12345',
					'type' => 'group',
				),
				'from' => array( 'id' => '67890' ),
				'text' => '/start',
			),
		);

		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'none', $response['action'] );
	}

	/**
	 * @test
	 */
	public function edited_message_with_settings_active_saves_channel_id() {
		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				if ( 'wctlgm_telegram_fetch_channel_id_active' === $key ) {
					return true;
				}
				return false;
			}
		);

		$set_transient_calls = array();
		Functions\when( 'set_transient' )->alias(
			function () use ( &$set_transient_calls ) {
				$set_transient_calls[] = func_get_args();
				return true;
			}
		);

		$delete_transient_calls = array();
		Functions\when( 'delete_transient' )->alias(
			function () use ( &$delete_transient_calls ) {
				$delete_transient_calls[] = func_get_args();
				return true;
			}
		);

		$handler = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data    = array(
			'edited_message' => array(
				'chat' => array( 'id' => '-100999' ),
			),
		);

		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'none', $response['action'] );
		$this->assertCount( 1, $set_transient_calls );
		$this->assertSame( 'wctlgm_channel_id_temp_store', $set_transient_calls[0][0] );
		$this->assertSame( '-100999', $set_transient_calls[0][1] );
		$this->assertCount( 1, $delete_transient_calls );
		$this->assertSame( 'wctlgm_telegram_fetch_channel_id_active', $delete_transient_calls[0][0] );
	}

	/**
	 * @test
	 */
	public function edited_message_without_settings_active_skips() {
		Functions\when( 'get_transient' )->justReturn( false );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data    = array(
			'edited_message' => array(
				'chat' => array( 'id' => '-100999' ),
			),
		);

		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'none', $response['action'] );
	}

	/**
	 * @test
	 */
	public function chat_join_request_valid_approves() {
		$order = $this->create_mock_order(
			array(
				'id'     => 100,
				'status' => 'completed',
				'meta'   => array(
					'_telegram_user_id'              => '67890',
					'_channel_invite_-1001234567890' => 'https://t.me/+valid_link',
				),
				'items'  => array( $this->create_mock_item( 456 ) ),
			)
		);

		$this->mock_plugin_options( array( 'wctlgm_require_activation_flow' => true ) );

		Functions\when( 'wc_get_orders' )->justReturn( array( $order ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data    = array(
			'chat_join_request' => array(
				'chat'        => array( 'id' => '-1001234567890' ),
				'from'        => array( 'id' => '67890' ),
				'invite_link' => array( 'invite_link' => 'https://t.me/+valid_link' ),
			),
		);

		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'none', $response['action'] );
	}

	/**
	 * @test
	 */
	public function chat_join_request_invalid_denies() {
		// No matching order found.
		Functions\when( 'wc_get_orders' )->justReturn( array() );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data    = array(
			'chat_join_request' => array(
				'chat'        => array( 'id' => '-1001234567890' ),
				'from'        => array( 'id' => '99999' ),
				'invite_link' => array( 'invite_link' => 'https://t.me/+unknown_link' ),
			),
		);

		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'none', $response['action'] );
	}

	/**
	 * @test
	 */
	public function message_without_entities_returns_default_invalid() {
		$handler = new Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$data    = array(
			'message' => array(
				'chat' => array(
					'id'   => '12345',
					'type' => 'private',
				),
				'from' => array( 'id' => '67890' ),
				'text' => 'just a regular message',
			),
		);

		$response = $handler->process_telegram_request( $data );

		$this->assertSame( 'sendMessage', $response['action'] );
		$this->assertStringContainsString( 'Invalid', $response['text'] );
	}
}
