<?php
/**
 * Integration tests for the complete activation flow.
 *
 * Tests the cross-handler interaction:
 * Order placed → activation code generated (Order Handler) →
 * /activate command processed (Bot Interaction Handler) →
 * code looked up via wc_get_orders meta query (Subscriptions Handler) →
 * invite links generated and stored.
 */
class ActivationFlowTest extends WCTLGM_Lite_Integration_TestCase {

	/**
	 * @test
	 */
	public function full_activation_flow_generates_invite_links() {
		update_option( 'wctlgm_require_activation_flow', true );

		// Step 1: Create product and order.
		$product = $this->wc_helpers->create_telegram_product(
			array(
				'channel_ids' => array( '-1001234567890' ),
			)
		);
		$order = $this->wc_helpers->create_order_with_product( $product );

		// Step 2: Transition to processing — should generate activation code.
		$order = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$activation_code = $order->get_meta( '_activation_code', true );
		$this->assertNotEmpty( $activation_code, 'Activation code should be generated.' );

		// No invite links yet (activation flow requires user action).
		$this->telegram_api->assert_not_called( $this, 'createChatInviteLink' );

		// Step 3: Simulate Telegram user activating with the code.
		$telegram_user_id = '67890';
		$response         = $this->webhook_simulator->send_activate_command(
			$telegram_user_id,
			$activation_code
		);

		// Step 4: Verify activation response.
		$data = $response->get_data();
		$this->assertEquals( 'sendMessage', $data['method'] );
		$this->assertStringContainsString( 'Activation successful', $data['text'] );
		$this->assertStringContainsString( 'https://t.me/', $data['text'] );

		// Step 5: Verify order meta was updated.
		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( $telegram_user_id, $order->get_meta( '_telegram_user_id', true ) );
		$this->assertEmpty( $order->get_meta( '_activation_code', true ), 'Activation code should be deleted after use.' );

		// Step 6: Verify invite link was stored.
		$invite_link = $order->get_meta( '_channel_invite_-1001234567890', true );
		$this->assertNotEmpty( $invite_link );
		$this->assertStringStartsWith( 'https://t.me/', $invite_link );

		// Step 7: Verify Telegram API was called to create the invite link.
		$this->telegram_api->assert_called( $this, 'createChatInviteLink' );
	}

	/**
	 * @test
	 */
	public function activation_with_invalid_code_fails() {
		update_option( 'wctlgm_require_activation_flow', true );

		$response = $this->webhook_simulator->send_activate_command( '67890', 'INVALID_CODE' );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Activation failed', $data['text'] );
		$this->telegram_api->assert_not_called( $this, 'createChatInviteLink' );
	}

	/**
	 * @test
	 */
	public function activation_code_not_regenerated_on_second_processing() {
		update_option( 'wctlgm_require_activation_flow', true );

		$product = $this->wc_helpers->create_telegram_product();
		$order   = $this->wc_helpers->create_order_with_product( $product );

		// First transition generates the code.
		$order      = $this->wc_helpers->transition_order_status( $order, 'processing' );
		$first_code = $order->get_meta( '_activation_code', true );
		$this->assertNotEmpty( $first_code );

		// Re-trigger processing (simulates idempotent re-processing).
		\Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order(
			$order->get_id(),
			'pending',
			'processing'
		);

		$order       = wc_get_order( $order->get_id() );
		$second_code = $order->get_meta( '_activation_code', true );
		$this->assertEquals( $first_code, $second_code, 'Activation code should not change on re-processing.' );
	}

	/**
	 * @test
	 */
	public function activation_via_start_deep_link() {
		update_option( 'wctlgm_require_activation_flow', true );

		$product = $this->wc_helpers->create_telegram_product(
			array(
				'channel_ids' => array( '-1001234567890' ),
			)
		);
		$order = $this->wc_helpers->create_order_with_product( $product );
		$order = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$activation_code = $order->get_meta( '_activation_code', true );

		// Use /start with activation code as deep-link payload.
		$response = $this->webhook_simulator->send_start_command(
			'67890',
			null,
			$activation_code
		);

		$data = $response->get_data();
		$this->assertStringContainsString( 'Activation successful', $data['text'] );

		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( '67890', $order->get_meta( '_telegram_user_id', true ) );
	}

	/**
	 * @test
	 */
	public function activation_with_empty_code_returns_help() {
		update_option( 'wctlgm_require_activation_flow', true );

		$response = $this->webhook_simulator->send_activate_command( '67890', '' );
		$data     = $response->get_data();

		$this->assertStringContainsString( '/help', $data['text'] );
	}
}
