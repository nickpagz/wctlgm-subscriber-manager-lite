<?php

use Brain\Monkey\Functions;
use Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_API_Handler;

class ApiHandlerRevokeInviteLinkTest extends WCTLGM_Lite_TestCase {

	/**
	 * @test
	 */
	public function revoke_invite_link_returns_data_on_success() {
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'ok'     => true,
					'result' => array( 'is_revoked' => true ),
				)
			)
		);

		$handler = new Subscriber_Manager_Lite_WCTLGM_API_Handler();
		$result  = $handler->revoke_invite_link( '-1001234567890', 'https://t.me/+abc123' );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['ok'] );
	}

	/**
	 * A link that was already revoked (e.g. consumed at join) must not block an
	 * admin revoke — it can no longer grant access, which is the goal of a revoke.
	 *
	 * @test
	 */
	public function revoke_invite_link_treats_already_revoked_link_as_success() {
		Functions\when( 'wp_remote_post' )->justReturn(
			array( 'response' => array( 'code' => 400 ) )
		);
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'ok'          => false,
					'error_code'  => 400,
					'description' => 'Bad Request: INVITE_HASH_EXPIRED',
				)
			)
		);

		$handler = new Subscriber_Manager_Lite_WCTLGM_API_Handler();
		$result  = $handler->revoke_invite_link( '-1001234567890', 'https://t.me/+abc123' );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['already_revoked'] );
	}

	/**
	 * @test
	 */
	public function revoke_invite_link_returns_error_on_other_failures() {
		Functions\when( 'wp_remote_post' )->justReturn(
			array( 'response' => array( 'code' => 400 ) )
		);
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'ok'          => false,
					'error_code'  => 400,
					'description' => 'Bad Request: not enough rights to manage chat invite link',
				)
			)
		);

		$handler = new Subscriber_Manager_Lite_WCTLGM_API_Handler();
		$result  = $handler->revoke_invite_link( '-1001234567890', 'https://t.me/+abc123' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'not enough rights', $result->get_error_message() );
	}
}
