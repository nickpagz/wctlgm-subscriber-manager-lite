<?php
/**
 * Integration tests for REST endpoint security.
 *
 * Tests token validation with real WordPress REST API infrastructure.
 */
class EndpointSecurityTest extends WCTLGM_Lite_Integration_TestCase {

	/**
	 * @test
	 */
	public function request_with_correct_token_is_accepted() {
		$request = new WP_REST_Request( 'POST', '/wctlgm/v1/telegram-bot/' );
		$request->set_header( 'X-Telegram-Bot-Api-Secret-Token', 'test_secret_token_for_integration' );

		$handler    = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$permission = $handler->check_telegram_token_permission( $request );

		$this->assertTrue( $permission );
	}

	/**
	 * @test
	 */
	public function request_with_wrong_token_is_rejected() {
		$request = new WP_REST_Request( 'POST', '/wctlgm/v1/telegram-bot/' );
		$request->set_header( 'X-Telegram-Bot-Api-Secret-Token', 'wrong_token' );

		$handler    = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$permission = $handler->check_telegram_token_permission( $request );

		$this->assertFalse( $permission );
	}

	/**
	 * @test
	 */
	public function request_with_missing_token_is_rejected() {
		$request = new WP_REST_Request( 'POST', '/wctlgm/v1/telegram-bot/' );

		$handler    = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
		$permission = $handler->check_telegram_token_permission( $request );

		$this->assertFalse( $permission );
	}

	/**
	 * @test
	 */
	public function help_command_returns_site_name() {
		$response = $this->webhook_simulator->send_help_command( '67890' );
		$data     = $response->get_data();

		$this->assertStringContainsString( get_bloginfo( 'name' ), $data['text'] );
		$this->assertStringContainsString( '/activate', $data['text'] );
	}
}
