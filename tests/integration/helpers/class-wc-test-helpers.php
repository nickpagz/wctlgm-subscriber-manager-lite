<?php
/**
 * WooCommerce factory helpers for integration tests.
 *
 * Creates real WC products and orders in the test database.
 * Simplified for the lite plugin — simple products only, no expiry/cancel/cutoff.
 */
class WCTLGM_Lite_WC_Test_Helpers {

	/**
	 * Create a simple product with Telegram channel configuration.
	 *
	 * @param array $args {
	 *     @type string $name        Product name.
	 *     @type float  $price       Product price.
	 *     @type array  $channel_ids Telegram channel IDs to grant access to.
	 * }
	 * @return WC_Product_Simple
	 */
	public function create_telegram_product( $args = array() ) {
		$defaults = array(
			'name'        => 'Telegram Access Product',
			'price'       => '29.99',
			'channel_ids' => array( '-1001234567890' ),
		);
		$args = wp_parse_args( $args, $defaults );

		$product = new WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_regular_price( $args['price'] );
		$product->set_status( 'publish' );
		$product->save();

		$product_id = $product->get_id();

		// Telegram meta is stored in postmeta.
		update_post_meta( $product_id, '_telegram_channel_ids', $args['channel_ids'] );

		return wc_get_product( $product_id );
	}

	/**
	 * Create a WooCommerce order containing a Telegram product.
	 *
	 * @param WC_Product $product The product to add.
	 * @param array      $args {
	 *     @type string $status Order status (without wc- prefix).
	 *     @type string $email  Customer email.
	 * }
	 * @return WC_Order
	 */
	public function create_order_with_product( $product, $args = array() ) {
		$defaults = array(
			'status' => 'pending',
			'email'  => 'test@example.com',
		);
		$args = wp_parse_args( $args, $defaults );

		$order = wc_create_order();
		$order->add_product( $product );
		$order->set_billing_email( $args['email'] );
		$order->set_billing_first_name( 'Test' );
		$order->set_billing_last_name( 'User' );
		$order->save();

		if ( 'pending' !== $args['status'] ) {
			$order->set_status( $args['status'] );
			$order->save();
		}

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Create a multi-channel product (access to multiple channels).
	 *
	 * @param array $channel_ids Channel IDs.
	 * @return WC_Product_Simple
	 */
	public function create_multi_channel_product( $channel_ids = array() ) {
		if ( empty( $channel_ids ) ) {
			$channel_ids = array( '-1001234567890', '-1009876543210' );
		}
		return $this->create_telegram_product(
			array(
				'name'        => 'Multi-Channel Product',
				'channel_ids' => $channel_ids,
			)
		);
	}

	/**
	 * Transition an order's status, triggering WooCommerce hooks.
	 *
	 * Uses WC_Order::set_status() + save() which fires
	 * 'woocommerce_order_status_changed'.
	 *
	 * @param WC_Order $order      The order.
	 * @param string   $new_status New status (without wc- prefix).
	 * @return WC_Order Refreshed order from DB.
	 */
	public function transition_order_status( $order, $new_status ) {
		$order->set_status( $new_status );
		$order->save();
		return wc_get_order( $order->get_id() );
	}
}
