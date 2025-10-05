<?php

namespace Subscriber_Manager_Lite_for_Telegram;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Order_Handler
 *
 * The main plugin class.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Order_Handler {

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'maybe_process_order' ), 10, 3 );
		add_action( 'woocommerce_email_order_details', array( __CLASS__, 'wctlgm_email_activation_info' ), 10, 4 );
		add_action( 'woocommerce_order_details_before_order_table', array( __CLASS__, 'display_activation_code_in_order_details' ), 10, 1 );
		add_action( 'wctlgm_send_invite_links_email', array( __CLASS__, 'send_invite_links_email' ), 10, 1 );
	}

	public static function maybe_process_order( $order_id, $old_status, $new_status ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( ! self::order_has_telegram_product( $order ) ) {
			return;
		}

		if ( ! in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
			return;
		}

		// Skip if already processed (processing → completed)
		if ( 'processing' === $old_status && 'completed' === $new_status ) {
			return;
		}

		$require_activation = get_option( 'wctlgm_require_activation_flow', false );

		if ( $require_activation ) {
			if ( $order->get_meta( '_activation_code', true ) ) {
				return;
			}
			$code_generated = self::generate_activation_code( $order );
		} else {
			// Check if invites already exist before generating
			$existing_invites = self::get_all_channel_invites_for_order( $order );
			if ( empty( $existing_invites ) ) {
				self::generate_and_store_invites( $order );
			}
		}
	}

	public static function generate_activation_code( $order ) {
		$activation_code = wp_generate_password( 8, false );
		$order->update_meta_data( '_activation_code', $activation_code );
		$order->save();
		return true;
	}

	private static function get_activation_info_text( $order ) {
		if ( ! self::order_has_telegram_product( $order ) ) {
			return '';
		}

		$activation_code = $order->get_meta( '_activation_code', true );
		$output          = '</div><div class="wctlgm-activation-info alignwide">';

		if ( ! empty( $activation_code ) ) {
			$output .= self::get_activation_info_text_for_order( $order, $activation_code );
			$output .= '<br>';
		}

		return apply_filters( 'wctlgm_activation_info_output', $output, $activation_code );
	}

	private static function get_activation_info_text_for_order( $order, $activation_code ) {
		$bot_url = get_option( 'wctlgm_bot_url' );
		$output  = '<h2>' . esc_html__( 'Telegram Activation Code', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
		$output .= '<p>' . esc_html__( 'Here is your activation code:', 'wctlgm-subscriber-manager-lite' ) . ' <strong>' . esc_html( $activation_code ) . '</strong></p>';
		$output .= sprintf(
			'<p>%s <a href="%s" target="_blank">%s</a></p>',
			esc_html( __( 'Please click on the following link to open Telegram and enter your activation code in our Telegram bot:', 'wctlgm-subscriber-manager-lite' ) ),
			esc_url( $bot_url . '?start=' . $activation_code ),
			esc_html( __( 'Start Chat', 'wctlgm-subscriber-manager-lite' ) )
		);
		return $output;
	}

	/**
	 * Email activation info to the customer when the order is completed.
	 * Injects into WooCommerce's order processing or completed email.
	 */
	public static function wctlgm_email_activation_info( $order, $sent_to_admin, $plain_text, $email ) {
		if ( 'customer_completed_order' === $email->id || 'customer_processing_order' === $email->id ) {
			self::maybe_email_activation_info( $order, $plain_text );
		}
	}

	private static function maybe_email_activation_info( $order, $plain_text = false ) {
		$require_activation = get_option( 'wctlgm_require_activation_flow', false );

		if ( $require_activation ) {
			$bot_url         = get_option( 'wctlgm_bot_url' );
			$activation_code = $order->get_meta( '_activation_code', true );
			if ( ! empty( $activation_code ) ) {
				if ( $plain_text ) {
					echo "\n" . esc_html__( 'Telegram Activation Code:', 'wctlgm-subscriber-manager-lite' ) . ' ' . esc_html( $activation_code );
					echo "\n" . esc_html__( 'Please click on the following link to open Telegram and enter your activation code in our Telegram bot:', 'wctlgm-subscriber-manager-lite' ) . ' ' . esc_url( $bot_url ) . '?activate=' . esc_html( $activation_code );
				} else {
					echo '<h2>' . esc_html__( 'Telegram Activation Code', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
					echo '<p>' . esc_html__( 'Here is your activation code:', 'wctlgm-subscriber-manager-lite' ) . ' <strong>' . esc_html( $activation_code ) . '</strong></p>';
					printf(
						'<p>%s <a href="%s" target="_blank">%s</a></p>',
						esc_html( __( 'Please click on the following link to open Telegram and enter your activation code in our Telegram bot:', 'wctlgm-subscriber-manager-lite' ) ),
						esc_url( $bot_url . '?start=' . $activation_code ),
						esc_html( __( 'Start Chat', 'wctlgm-subscriber-manager-lite' ) )
					);
				}
			}
		}
	}

	public static function display_activation_code_in_order_details( $order ) {
		if ( ! self::order_has_telegram_product( $order ) ) {
			return;
		}

		$require_activation = get_option( 'wctlgm_require_activation_flow', false );

		if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			if ( $require_activation ) {
				echo '<h2>' . esc_html__( 'Telegram Activation Code', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
				echo '<p>' . esc_html__( 'Telegram access activation details will be emailed and available in your dashboard after payment processing is complete.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
			} else {
				echo '<h2>' . esc_html__( 'Telegram Access', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
				echo '<p>' . esc_html__( 'Invite links will be emailed and available in your dashboard after payment processing is complete.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
			}
			return;
		}

		if ( $require_activation ) {
			$activation_code  = $order->get_meta( '_activation_code', true );
			$telegram_user_id = $order->get_meta( '_telegram_user_id', true );

			$is_activated = ! empty( $telegram_user_id ) || empty( $activation_code );

			if ( $is_activated ) {
				echo '<h2>' . esc_html__( 'Telegram Activation Code', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
				echo '<p>' . esc_html__( 'Activated', 'wctlgm-subscriber-manager-lite' ) . '</p>';
			} else {
				echo wp_kses_post( self::get_activation_info_text_for_order( $order, $activation_code ) );
			}
		} else {
			// Display invite links when activation flow is disabled
			self::display_invite_links_in_order_details( $order );
		}
	}

	private static function order_has_telegram_product( $order ) {
		$items = $order->get_items();

		foreach ( $items as $item ) {
			$product_id  = $item->get_product_id();
			$product     = wc_get_product( $product_id );
			$channel_ids = get_post_meta( $product_id, '_telegram_channel_ids', true );

			if ( ! empty( $channel_ids ) && $product->is_type( 'simple' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate and store invite links for an order when activation is disabled.
	 */
	private static function generate_and_store_invites( $order ) {
		$subscriptions_handler = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$response              = $subscriptions_handler->get_channel_invites( $order );
		if ( $response['success'] && ! empty( $response['channels'] ) ) {
			foreach ( $response['channels'] as $invite ) {
				// Store invite link with channel ID as meta key suffix
				$order->add_meta_data( '_channel_invite_' . $invite['channel_id'], sanitize_url( $invite['invite_link'] ) );
			}
			$order->save();

			// Schedule email to be sent with a slight delay using Action Scheduler
			as_schedule_single_action(
				time() + 5, // 5 seconds delay
				'wctlgm_send_invite_links_email',
				array( array( $order->get_id(), $response['channels'] ) ),
			);
		}
	}

	/**
	 * Send invite links email via Action Scheduler.
	 * (Action Scheduler unwraps the nested array for us).
	 *
	 * @param array $data Array containing order_id and invites data.
	 */
	public static function send_invite_links_email( $data ) {
		if ( empty( $data ) || ! is_array( $data ) || count( $data ) < 2 ) {
			return;
		}

		$order_id = $data[0];
		$invites  = $data[1];

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Get the email instance
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['wctlgm_invite_links'] ) ) {
			$email = $emails['wctlgm_invite_links'];
			$email->trigger( $order_id, $invites );
		}
	}

	/**
	 * Display invite links in order details when activation is disabled.
	 */
	private static function display_invite_links_in_order_details( $order ) {
		// Get all indexed channel invite meta data
		$invites = self::get_all_channel_invites_for_order( $order );
		if ( empty( $invites ) ) {
			// Generate invites if not already stored
			self::generate_and_store_invites( $order );
			$invites = self::get_all_channel_invites_for_order( $order );
		}

		if ( empty( $invites ) ) {
			echo '<h2>' . esc_html__( 'Telegram Access', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
			echo '<p>' . esc_html__( 'No Telegram access available for this order.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
			return;
		}

		echo '<h2>' . esc_html__( 'Telegram Access', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
		echo '<p>' . esc_html__( 'Below are your private invite links:', 'wctlgm-subscriber-manager-lite' ) . '</p>';
		foreach ( $invites as $invite ) {
			printf(
				'<p><strong>%s:</strong> <a href="%s" target="_blank">%s</a></p>',
				esc_html( $invite['name'] ),
				esc_url( $invite['invite_link'] ),
				esc_html__( 'Join', 'wctlgm-subscriber-manager-lite' )
			);
		}
	}

	/**
	 * Get all channel or group invites for an order using indexed meta keys.
	 *
	 * @param WC_Order $order The order object.
	 * @return array Array of invite data with channel_id, channel_name, and invite_link.
	 */
	private static function get_all_channel_invites_for_order( $order ) {
		$invites = array();
		// Get all meta data for this order
		$meta_data = $order->get_meta_data();
		foreach ( $meta_data as $meta ) {
			$meta_key   = $meta->get_data()['key'];
			$meta_value = $meta->get_data()['value'];
			// Check if this is a channel or group invite meta
			if ( strpos( $meta_key, '_channel_invite_' ) === 0 ) {
				// Extract channel ID from meta key
				$channel_id = str_replace( '_channel_invite_', '', $meta_key );
				// Get channel name
				$channel_name = self::get_channel_name_by_id( $channel_id );
				$invites[]    = array(
					'channel_id'  => $channel_id,
					'name'        => $channel_name ? $channel_name : 'Channel or Group',
					'invite_link' => $meta_value,
				);
			}
		}
		return $invites;
	}

	/**
	 * Get channel or group name by ID.
	 *
	 * @param string $channel_id The channel or group ID.
	 * @return string|null The channel or group name or null if not found.
	 */
	private static function get_channel_name_by_id( $channel_id ) {
		$channels = get_option( 'wctlgm_channels', array() );

		foreach ( $channels as $channel ) {
			if ( $channel['id'] === $channel_id ) {
				return $channel['name'];
			}
		}

		return null;
	}
}
