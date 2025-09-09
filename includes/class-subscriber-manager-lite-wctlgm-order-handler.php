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
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'maybe_process_order' ), 11, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'maybe_process_order' ), 11, 1 );
		add_action( 'woocommerce_email_order_details', array( __CLASS__, 'wctlgm_email_activation_info' ), 10, 4 );
		add_action( 'woocommerce_order_details_before_order_table', array( __CLASS__, 'display_activation_code_in_order_details' ), 10, 1 );
	}

	public static function maybe_process_order( $order ) {
		if ( is_numeric( $order ) ) {
			$order_id = $order;
			$order    = wc_get_order( $order_id );
		} else {
			$order_id = $order->get_id();
		}

		if ( ! self::order_has_telegram_product( $order ) ) {
			return;
		}

		$require_activation = get_option( 'wctlgm_require_activation_flow', true );

		if ( $require_activation ) {
			if ( $order->get_meta( '_activation_code', true ) ) {
				return;
			}
			$code_generated = self::generate_activation_code( $order );
		} else {
			// Generate invites directly when activation flow is disabled
			self::generate_and_store_invites( $order );
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
		$output .= '<h2>' . esc_html__( 'Telegram Activation Code', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
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
	 * Email activation info or invite links to the customer when the order is completed.
	 * Injects into WooCommerce's order completed email.
	 */
	public static function wctlgm_email_activation_info( $order, $sent_to_admin, $plain_text, $email ) {
		if ( 'customer_completed_order' === $email->id || 'customer_processing_order' === $email->id ) {
			self::email_activation_info( $order, $plain_text );
		}
	}

	private static function email_activation_info( $order, $plain_text = false ) {
		$require_activation = get_option( 'wctlgm_require_activation_flow', true );

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
		} else {
			// Show invite links when activation is disabled
			self::email_invite_links( $order, $plain_text );
		}
	}

	public static function display_activation_code_in_order_details( $order ) {
		if ( ! self::order_has_telegram_product( $order ) ) {
			return;
		}

		$require_activation = get_option( 'wctlgm_require_activation_flow', true );

		if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			if ( $require_activation ) {
				echo '<h2>' . esc_html__( 'Telegram Activation Code', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
				echo '<p>' . esc_html__( 'Telegram Channel activation details will be emailed and available in your dashboard after payment processing is complete.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
			} else {
				echo '<h2>' . esc_html__( 'Telegram Channel Access', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
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
			// Display invite links when activation is disabled
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
				$order->add_meta_data( '_channel_invite', sanitize_url( $invite['invite_link'] ) );
			}
			$order->save();
		}
	}

	/**
	 * Email invite links when activation is disabled.
	 */
	private static function email_invite_links( $order, $plain_text = false ) {
		$invites = $order->get_meta( '_channel_invite', false );
		if ( empty( $invites ) ) {
			// Generate invites if not already stored
			self::generate_and_store_invites( $order );
			$invites = $order->get_meta( '_channel_invite', false );
		}

		if ( empty( $invites ) ) {
			return;
		}

		// Get channel names for display
		$channels      = get_option( 'wctlgm_channels', array() );
		$channel_names = array();
		foreach ( $channels as $channel ) {
			$channel_names[ $channel['id'] ] = $channel['name'];
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Telegram Channel Access:', 'wctlgm-subscriber-manager-lite' );
			foreach ( $invites as $invite_link ) {
				// Find channel name by matching invite link to stored channel IDs
				$channel_name = 'Channel';
				foreach ( $order->get_items() as $item ) {
					$product_id  = $item->get_product_id();
					$channel_ids = get_post_meta( $product_id, '_telegram_channel_ids', true );
					if ( ! empty( $channel_ids ) ) {
						foreach ( $channel_ids as $channel_id ) {
							if ( isset( $channel_names[ $channel_id ] ) ) {
								$channel_name = $channel_names[ $channel_id ];
								break 2;
							}
						}
					}
				}
				echo "\n" . esc_html( $channel_name ) . ': ' . esc_url( $invite_link );
			}
		} else {
			echo '<h2>' . esc_html__( 'Telegram Channel Access', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
			echo '<p>' . esc_html__( 'Below are your private channel invite links:', 'wctlgm-subscriber-manager-lite' ) . '</p>';
			foreach ( $invites as $invite_link ) {
				// Find channel name by matching invite link to stored channel IDs
				$channel_name = 'Channel';
				foreach ( $order->get_items() as $item ) {
					$product_id  = $item->get_product_id();
					$channel_ids = get_post_meta( $product_id, '_telegram_channel_ids', true );
					if ( ! empty( $channel_ids ) ) {
						foreach ( $channel_ids as $channel_id ) {
							if ( isset( $channel_names[ $channel_id ] ) ) {
								$channel_name = $channel_names[ $channel_id ];
								break 2;
							}
						}
					}
				}
				printf(
					'<p><strong>%s:</strong> <a href="%s" target="_blank">%s</a></p>',
					esc_html( $channel_name ),
					esc_url( $invite_link ),
					esc_html__( 'Join Channel', 'wctlgm-subscriber-manager-lite' )
				);
			}
		}
	}

	/**
	 * Display invite links in order details when activation is disabled.
	 */
	private static function display_invite_links_in_order_details( $order ) {
		$invites = $order->get_meta( '_channel_invite', false );
		if ( empty( $invites ) ) {
			// Generate invites if not already stored
			self::generate_and_store_invites( $order );
			$invites = $order->get_meta( '_channel_invite', false );}

		if ( empty( $invites ) ) {
			echo '<h2>' . esc_html__( 'Telegram Channel Access', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
			echo '<p>' . esc_html__( 'No channels available for this order.', 'wctlgm-subscriber-manager-lite' ) . '</p>';
			return;
		}

		// Get channel names for display
		$channels      = get_option( 'wctlgm_channels', array() );
		$channel_names = array();
		foreach ( $channels as $channel ) {
			$channel_names[ $channel['id'] ] = $channel['name'];
		}

		echo '<h2>' . esc_html__( 'Telegram Channel Access', 'wctlgm-subscriber-manager-lite' ) . '</h2>';
		echo '<p>' . esc_html__( 'Below are your private channel invite links:', 'wctlgm-subscriber-manager-lite' ) . '</p>';
		foreach ( $invites as $invite_link ) {
			// Find channel name by matching invite link to stored channel IDs
			$channel_name = 'Channel';
			foreach ( $order->get_items() as $item ) {
				$product_id  = $item->get_product_id();
				$channel_ids = get_post_meta( $product_id, '_telegram_channel_ids', true );
				if ( ! empty( $channel_ids ) ) {
					foreach ( $channel_ids as $channel_id ) {
						if ( isset( $channel_names[ $channel_id ] ) ) {
							$channel_name = $channel_names[ $channel_id ];
							break 2;
						}
					}
				}
			}
			printf(
				'<p><strong>%s:</strong> <a href="%s" target="_blank">%s</a></p>',
				esc_html( $channel_name ),
				esc_url( $invite_link ),
				esc_html__( 'Join Channel', 'wctlgm-subscriber-manager-lite' )
			);
		}
	}
}
