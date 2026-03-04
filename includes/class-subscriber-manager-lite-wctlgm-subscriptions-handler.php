<?php

namespace Subscriber_Manager_Lite_for_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler
 *
 * The Class for handling Telegram subscriptions.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler {

	private $api_handler;
	private $logger;

	public function __construct() {
		$this->api_handler = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_API_Handler();
		$this->logger      = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Logger();
	}

	public function process_activation_code( $code, $telegram_user_id ) {
		$order = $this->find_order_by( '_activation_code', sanitize_text_field( $code ) );
		if ( $order && in_array( $order->get_status(), array( 'completed', 'processing' ), true ) ) {
			$order_id = $order->get_id();
			$order->update_meta_data( '_telegram_user_id', sanitize_text_field( $telegram_user_id ) );
			$order->delete_meta_data( '_activation_code', sanitize_text_field( $code ) );
			$response        = $this->get_channel_invites( $order );
			$channel_invites = $response['channels'];
			foreach ( $channel_invites as $invite ) {
				$order->add_meta_data( '_channel_invite_' . $invite['channel_id'], sanitize_url( $invite['invite_link'] ) );

				// Create pending DB record for subscriber tracking.
				Subscriber_Manager_Lite_WCTLGM_Database::add_user_channel(
					array(
						'order_id'         => $order_id,
						'channel_id'       => $invite['channel_id'],
						'invite_link'      => $invite['invite_link'],
						'invite_issued_at' => current_time( 'mysql' ),
					)
				);
			}
			$order->save();
			return array( $response, $order_id );
		}
		return false;
	}

	public function is_join_request_valid( $user_id, $invite_link, $chat_id, $allow_external_invites = false ) {
		$order = $this->find_order_by_invite_link( sanitize_url( $invite_link ), $chat_id );
		if ( $order ) {
			// Ensure order is in a valid status (completed or processing)
			if ( ! in_array( $order->get_status(), array( 'completed', 'processing' ), true ) ) {
				return false;
			}

			$order_id           = $order->get_id();
			$telegram_user_id   = $order->get_meta( '_telegram_user_id', true );
			$require_activation = get_option( 'wctlgm_require_activation_flow', false );
			if ( $require_activation ) {
				return (string) $telegram_user_id === (string) $user_id;
			} else {
				// Activation disabled - capture the user ID and approve
				// Check if user ID already exists and log if overwriting
				if ( ! empty( $telegram_user_id ) && $telegram_user_id !== $user_id ) {
					$this->logger->warning(
						sprintf(
							'Attempted User ID overwrite in order %1$d: %2$s -> %3$s',
							$order_id,
							$telegram_user_id,
							$user_id
						)
					);
					return false;
				}

				// Only update if no user ID exists
				$order->update_meta_data( '_telegram_user_id', sanitize_text_field( $user_id ) );
				$order->save();
				return true;
			}
		}

		if ( $allow_external_invites ) {
			// To-do: This is where we would add external users for 2.0.
			$this->logger->info( __( 'External invite link approved: ', 'wctlgm-subscriber-manager-lite' ) . $user_id . ' - ' . $invite_link );
			return true;
		}

		return false;
	}

	private function find_order_by( $meta_key, $meta_value, $compare = '=' ) {
		$orders = wc_get_orders(
			array(
				'meta_query' => array(
					array(
						'key'     => $meta_key,
						'value'   => $meta_value,
						'compare' => $compare,
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

	/**
	 * Find order by invite link, searching through all indexed channel invite meta keys.
	 *
	 * @param string $invite_link The invite link to search for.
	 * @return WC_Order|null The order if found, null otherwise.
	 */
	private function find_order_by_invite_link( $invite_link, $chat_id = null ) {
		if ( $chat_id ) {
			$meta_key = '_channel_invite_' . $chat_id;
			return $this->find_order_by( $meta_key, $invite_link );
		}

		return null;
	}

	public function get_channel_invites( $order ) {
		$invites = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$variation_id = $item->get_variation_id();
			$meta_id      = $variation_id ? $variation_id : $item->get_product_id();
			$channel_ids  = get_post_meta( $meta_id, '_telegram_channel_ids', true );

			if ( ! empty( $channel_ids ) ) {
				foreach ( $channel_ids as $channel_id ) {

					// Check if invite link already exists for this order and channel
					$existing_invite = $this->get_existing_invite_for_channel( $order, $channel_id );

					if ( $existing_invite ) {
						// Use existing invite link
						$invites[] = array(
							'channel_id'  => $channel_id,
							'name'        => $this->get_channel_name_by_id( $channel_id ),
							'invite_link' => $existing_invite,
						);
					} else {
						// Generate new invite link only if none exists
						$invite_link = $this->api_handler->generate_invite_link( $channel_id );
						if ( $invite_link && ! is_wp_error( $invite_link ) ) {
							$invites[] = array(
								'channel_id'  => $channel_id,
								'name'        => $this->get_channel_name_by_id( $channel_id ),
								'invite_link' => $invite_link,
							);
						}
					}
				}
			}
		}

		if ( empty( $invites ) ) {
			return array(
				'success'  => false,
				'channels' => array(),
				'message'  => __( 'No channels found or failed to generate invites for products.', 'wctlgm-subscriber-manager-lite' ),
			);
		}

		return array(
			'success'  => true,
			'channels' => $invites,
			'message'  => __( 'Channel invites generated successfully.', 'wctlgm-subscriber-manager-lite' ),
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

	/**
	 * Check if an invite link already exists for a specific order and channel.
	 *
	 * @param WC_Order $order The order object.
	 * @param string   $channel_id The channel ID to check for.
	 * @return string|null The existing invite link or null if not found.
	 */
	private function get_existing_invite_for_channel( $order, $channel_id ) {
		$existing_invite = $order->get_meta( '_channel_invite_' . $channel_id, true );

		if ( empty( $existing_invite ) ) {
			return null;
		}

		return $existing_invite;
	}
}
