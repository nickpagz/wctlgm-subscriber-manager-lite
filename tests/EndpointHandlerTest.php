<?php

use Brain\Monkey\Functions;
use Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler;

class EndpointHandlerTest extends WCTLGM_Lite_TestCase {

	/**
	 * @test
	 */
	public function check_permission_with_valid_token_returns_true() {
		$this->mock_plugin_options( array( 'wctlgm_secret_token' => 'my_secret_123' ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$request = new WP_REST_Request();
		$request->set_header( 'X-Telegram-Bot-Api-Secret-Token', 'my_secret_123' );

		$result = $handler->check_telegram_token_permission( $request );

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function check_permission_with_invalid_token_returns_false() {
		$this->mock_plugin_options( array( 'wctlgm_secret_token' => 'my_secret_123' ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$request = new WP_REST_Request();
		$request->set_header( 'X-Telegram-Bot-Api-Secret-Token', 'wrong_token' );

		$result = $handler->check_telegram_token_permission( $request );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function check_permission_with_missing_token_returns_false() {
		$this->mock_plugin_options( array( 'wctlgm_secret_token' => 'my_secret_123' ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$request = new WP_REST_Request();
		// No token header set.

		$result = $handler->check_telegram_token_permission( $request );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 *
	 * Regression: on a fresh install the secret token is unset/empty. An
	 * unauthenticated request with no header must be rejected — previously
	 * '' === '' passed and accepted forged Telegram updates.
	 */
	public function check_permission_with_empty_saved_token_and_no_header_returns_false() {
		$this->mock_plugin_options( array( 'wctlgm_secret_token' => '' ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$request = new WP_REST_Request();
		// No token header set — mirrors an unauthenticated POST on a fresh install.

		$result = $handler->check_telegram_token_permission( $request );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 *
	 * Regression: an explicitly empty header must not match an empty saved
	 * secret either.
	 */
	public function check_permission_with_empty_saved_token_and_empty_header_returns_false() {
		$this->mock_plugin_options( array( 'wctlgm_secret_token' => '' ) );

		$handler = new Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$request = new WP_REST_Request();
		$request->set_header( 'X-Telegram-Bot-Api-Secret-Token', '' );

		$result = $handler->check_telegram_token_permission( $request );

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function handle_telegram_requests_returns_send_message_response() {
		$handler = new Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$request = new WP_REST_Request();
		$request->set_json_params(
			array(
				'message' => array(
					'chat' => array(
						'id'   => '12345',
						'type' => 'private',
					),
					'from' => array( 'id' => '67890' ),
					'text' => '/help',
					'entities' => array(
						array(
							'type'   => 'bot_command',
							'offset' => 0,
							'length' => 5,
						),
					),
				),
			)
		);

		$response = $handler->handle_telegram_requests( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'sendMessage', $data['method'] );
		$this->assertSame( '12345', $data['chat_id'] );
	}

	/**
	 * @test
	 */
	public function handle_telegram_requests_returns_ok_for_no_action() {
		$handler = new Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$request = new WP_REST_Request();
		$request->set_json_params( array() ); // Empty data = no message = action none.

		$response = $handler->handle_telegram_requests( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'ok', $data['status'] );
	}
}
