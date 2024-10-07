<?php

/**
 * WC_Telegram_Subscriptions_Handler
 *
 * The Class for handling Telegram subscriptions.
 *
 * @package WC_Telegram_Subscriber_Manager
 */
class WC_Telegram_Subscriptions_Handler {

	private $api_handler;

	public function __construct() {
		$this->api_handler = new WC_Telegram_API_Handler();
	}

	public function process_activation_code( $code, $telegram_user_id ) {
		$order = $this->find_order_by( '_activation_code', sanitize_text_field( $code ) );
		if ( $order ) {
			$order_id = $order->get_id();
			$order->update_meta_data( '_telegram_user_id', $telegram_user_id );
			$order->delete_meta_data( '_activation_code', $code );
			$response        = $this->get_channel_invites( $order );
			$channel_invites = $response['channels'];
			foreach ( $channel_invites as $invite ) {
				$order->add_meta_data( '_channel_invite', sanitize_url( $invite['invite_link'] ) );
			}
			$order->save();
			return array( $response, $order_id );
		}
		return false;
	}

	public function is_join_request_valid( $user_id, $invite_link ) {
		$order = $this->find_order_by( '_channel_invite', sanitize_url( $invite_link ) );
		if ( $order ) {
			$order_id         = $order->get_id();
			$telegram_user_id = $order->get_meta( '_telegram_user_id', true );
			if ( (string) $telegram_user_id === (string) $user_id ) {
				return true;
			}
		}
		return false;
	}

	private function find_order_by( $meta_key, $meta_value ) {
		$orders = wc_get_orders(
			array(
				'meta_query' => array(
					array(
						'key'     => $meta_key,
						'value'   => $meta_value,
						'compare' => '=',
					),
				),
			)
		);

		if ( count( $orders ) > 1 ) {
			return null;
		}

		if ( ! empty( $orders ) ) {
			return $orders[0];
		}

		return null;
	}

	private function get_channel_invites( $order ) {
		$invites = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id  = $item->get_product_id();
			$channel_ids = get_post_meta( $product_id, '_telegram_channel_ids', true );

			if ( ! empty( $channel_ids ) ) {
				foreach ( $channel_ids as $channel_id ) {
					$invite_link = $this->api_handler->generate_invite_link( $channel_id );
					if ( $invite_link && ! is_wp_error( $invite_link ) ) {
						$invites[] = array(
							'name'        => $this->get_channel_name_by_id( $channel_id ),
							'invite_link' => $invite_link,
						);
					}
				}
			}
		}

		if ( empty( $invites ) ) {
			return array(
				'success'  => false,
				'channels' => array(),
				'message'  => __( 'No channels found or failed to generate invites for products.', 'wctlgm-subscriber-manager' ),
			);
		}

		return array(
			'success'  => true,
			'channels' => $invites,
			'message'  => __( 'Channel invites generated successfully.', 'wctlgm-subscriber-manager' ),
		);
	}

	/**
	 * Retrieves the channel name from the saved settings using the channel ID.
	 *
	 * @param string $channel_id The channel ID for which the name needs to be retrieved.
	 * @return string|null The channel name or null if not found.
	 */
	private function get_channel_name_by_id( $channel_id ) {
		$channels = get_option( 'wctlgm_channels', array() );

		foreach ( $channels as $channel ) {
			if ( $channel['id'] === $channel_id ) {
				return $channel['name'];
			}
		}

		return null;
	}
}
