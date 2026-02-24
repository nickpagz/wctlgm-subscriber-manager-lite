<?php
/**
 * Integration tests for the direct invite flow (activation disabled).
 *
 * Tests the end-to-end flow: product created → order placed → status changes →
 * invite links generated via Telegram API → stored as indexed order meta.
 */
class DirectInviteFlowTest extends WCTLGM_Lite_Integration_TestCase {

	/**
	 * @test
	 */
	public function order_processing_generates_invite_links_for_single_channel() {
		update_option( 'wctlgm_require_activation_flow', false );

		$product = $this->wc_helpers->create_telegram_product(
			array(
				'channel_ids' => array( '-1001234567890' ),
			)
		);

		$order = $this->wc_helpers->create_order_with_product( $product );
		$order = $this->wc_helpers->transition_order_status( $order, 'processing' );

		// Invite link should be stored as indexed meta.
		$invite_link = $order->get_meta( '_channel_invite_-1001234567890', true );
		$this->assertNotEmpty( $invite_link, 'Invite link should be generated and stored.' );
		$this->assertStringStartsWith( 'https://t.me/', $invite_link );

		// Telegram API should have been called once to create the invite link.
		$this->telegram_api->assert_call_count( $this, 'createChatInviteLink', 1 );
	}

	/**
	 * @test
	 */
	public function duplicate_processing_does_not_regenerate_invites() {
		update_option( 'wctlgm_require_activation_flow', false );

		$product = $this->wc_helpers->create_telegram_product();
		$order   = $this->wc_helpers->create_order_with_product( $product );
		$order   = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$original_invite = $order->get_meta( '_channel_invite_-1001234567890', true );
		$this->assertNotEmpty( $original_invite );

		$this->telegram_api->reset();

		// Manually re-trigger the order handler (simulates re-processing).
		\Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Order_Handler::maybe_process_order(
			$order->get_id(),
			'pending',
			'processing'
		);

		// No new API calls — existing invites should be preserved.
		$this->telegram_api->assert_not_called( $this, 'createChatInviteLink' );

		// Original invite should be unchanged.
		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( $original_invite, $order->get_meta( '_channel_invite_-1001234567890', true ) );
	}

	/**
	 * @test
	 */
	public function processing_to_completed_transition_is_skipped() {
		update_option( 'wctlgm_require_activation_flow', false );

		$product = $this->wc_helpers->create_telegram_product();
		$order   = $this->wc_helpers->create_order_with_product( $product );
		$order   = $this->wc_helpers->transition_order_status( $order, 'processing' );

		$this->telegram_api->reset();

		// Transition processing → completed should be skipped.
		$order = $this->wc_helpers->transition_order_status( $order, 'completed' );

		// No new API calls.
		$this->telegram_api->assert_not_called( $this, 'createChatInviteLink' );
	}

	/**
	 * @test
	 */
	public function non_telegram_product_order_is_ignored() {
		update_option( 'wctlgm_require_activation_flow', false );

		// Create a product WITHOUT Telegram channel IDs.
		$product = new WC_Product_Simple();
		$product->set_name( 'Regular Product' );
		$product->set_regular_price( '19.99' );
		$product->set_status( 'publish' );
		$product->save();

		$order = wc_create_order();
		$order->add_product( $product );
		$order->save();
		$order->set_status( 'processing' );
		$order->save();

		// No Telegram API calls should be made.
		$this->telegram_api->assert_not_called( $this, 'createChatInviteLink' );
	}
}
