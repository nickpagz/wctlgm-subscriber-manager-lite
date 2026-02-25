<?php
/**
 * Integration tests for the join request validation flow.
 *
 * Tests the full chain: invite link stored on order → join request received →
 * order looked up by invite link + chat_id → user validated → approved/denied.
 */
class JoinRequestFlowTest extends WCTLGM_Lite_Integration_TestCase {

	/**
	 * @test
	 */
	public function valid_join_request_after_direct_invite_is_approved() {
		update_option( 'wctlgm_require_activation_flow', false );

		$product = $this->wc_helpers->create_telegram_product(
			array(
				'channel_ids' => array( '-1001234567890' ),
			)
		);
		$order = $this->wc_helpers->create_order_with_product( $product );
		$order = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$invite_link = $order->get_meta( '_channel_invite_-1001234567890', true );
		$this->assertNotEmpty( $invite_link, 'Invite link should exist before join request.' );

		$this->telegram_api->reset();

		// Simulate join request using the stored invite link.
		$this->webhook_simulator->send_join_request( '67890', '-1001234567890', $invite_link );

		// Should be approved and link revoked.
		$this->telegram_api->assert_called( $this, 'approveChatJoinRequest' );
		$this->telegram_api->assert_called( $this, 'revokeChatInviteLink' );
		$this->telegram_api->assert_not_called( $this, 'declineChatJoinRequest' );

		// User ID should be captured on the order.
		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( '67890', $order->get_meta( '_telegram_user_id', true ) );
	}

	/**
	 * @test
	 */
	public function valid_join_request_after_activation_is_approved() {
		update_option( 'wctlgm_require_activation_flow', true );

		$product = $this->wc_helpers->create_telegram_product(
			array(
				'channel_ids' => array( '-1001234567890' ),
			)
		);
		$order = $this->wc_helpers->create_order_with_product( $product );
		$order = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$activation_code = $order->get_meta( '_activation_code', true );

		// Activate with user 67890.
		$this->webhook_simulator->send_activate_command( '67890', $activation_code );

		// Get the invite link stored after activation.
		$order       = wc_get_order( $order->get_id() );
		$invite_link = $order->get_meta( '_channel_invite_-1001234567890', true );
		$this->assertNotEmpty( $invite_link );

		$this->telegram_api->reset();

		// Same user joins — should be approved.
		$this->webhook_simulator->send_join_request( '67890', '-1001234567890', $invite_link );

		$this->telegram_api->assert_called( $this, 'approveChatJoinRequest' );
		$this->telegram_api->assert_not_called( $this, 'declineChatJoinRequest' );
	}

	/**
	 * @test
	 */
	public function wrong_user_in_activation_mode_is_denied() {
		update_option( 'wctlgm_require_activation_flow', true );

		$product = $this->wc_helpers->create_telegram_product(
			array(
				'channel_ids' => array( '-1001234567890' ),
			)
		);
		$order = $this->wc_helpers->create_order_with_product( $product );
		$order = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$activation_code = $order->get_meta( '_activation_code', true );

		// Activate with user 67890.
		$this->webhook_simulator->send_activate_command( '67890', $activation_code );

		$order       = wc_get_order( $order->get_id() );
		$invite_link = $order->get_meta( '_channel_invite_-1001234567890', true );

		$this->telegram_api->reset();

		// DIFFERENT user (99999) tries to join.
		$this->webhook_simulator->send_join_request( '99999', '-1001234567890', $invite_link );

		$this->telegram_api->assert_called( $this, 'declineChatJoinRequest' );
		$this->telegram_api->assert_not_called( $this, 'approveChatJoinRequest' );
	}

	/**
	 * @test
	 */
	public function user_id_overwrite_in_direct_mode_is_denied() {
		update_option( 'wctlgm_require_activation_flow', false );

		$product = $this->wc_helpers->create_telegram_product(
			array(
				'channel_ids' => array( '-1001234567890' ),
			)
		);
		$order = $this->wc_helpers->create_order_with_product( $product );
		$order = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$invite_link = $order->get_meta( '_channel_invite_-1001234567890', true );

		// First user joins successfully.
		$this->webhook_simulator->send_join_request( '67890', '-1001234567890', $invite_link );
		$this->telegram_api->assert_called( $this, 'approveChatJoinRequest' );

		$this->telegram_api->reset();

		// Second user tries to use same invite link — should be denied.
		$this->webhook_simulator->send_join_request( '99999', '-1001234567890', $invite_link );
		$this->telegram_api->assert_called( $this, 'declineChatJoinRequest' );
		$this->telegram_api->assert_not_called( $this, 'approveChatJoinRequest' );

		// Original user ID should be preserved.
		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( '67890', $order->get_meta( '_telegram_user_id', true ) );
	}

	/**
	 * @test
	 */
	public function join_request_with_external_invites_allowed() {
		update_option( 'wctlgm_allow_external_invites', true );

		// No order exists for this invite link — but external invites are allowed.
		$this->webhook_simulator->send_join_request(
			'67890',
			'-1001234567890',
			'https://t.me/+external_link_12345'
		);

		$this->telegram_api->assert_called( $this, 'approveChatJoinRequest' );
		$this->telegram_api->assert_not_called( $this, 'declineChatJoinRequest' );
	}
}
