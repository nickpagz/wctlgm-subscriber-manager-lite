<?php

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

/**
 * Class WC_Telegram_Order_Handler
 *
 * The main plugin class.
 *
 * @package WC_Telegram_Subscriber_Manager
 */
class WC_Telegram_Order_Handler {

	public static function init() {
		add_action( 'woocommerce_checkout_update_order_meta', array( 'WC_Telegram_Order_Handler', 'maybe_process_order' ), 11, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( 'WC_Telegram_Order_Handler', 'maybe_process_order' ), 11, 1 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( 'WC_Telegram_Order_Handler', 'wctlgm_display_activation_info' ), 10, 2 );
		add_action( 'woocommerce_email_order_details', array( 'WC_Telegram_Order_Handler', 'wctlgm_email_activation_info' ), 10, 4 );
	}

	public static function maybe_process_order( $order ) {
		if ( is_numeric( $order ) ) {
			$order_id = $order;
			$order    = wc_get_order( $order_id );
		} else {
			$order_id = $order->get_id();
		}

		if ( $order->get_meta( '_activation_code', true ) ) {
			return;
		}

		$code_generated = false;
		$items          = $order->get_items();

		foreach ( $items as $item ) {
			$product_id  = $item->get_product_id();
			$product     = wc_get_product( $product_id );
			$channel_ids = get_post_meta( $product_id, '_telegram_channel_ids', true );

			if ( $product->is_type( 'simple' ) && ! empty( $channel_ids ) && ! $code_generated ) {
				$code_generated = self::generate_activation_code( $order );
			}
		}
	}

	public static function generate_activation_code( $order ) {
		$activation_code = wp_generate_password( 8, false );
		$order->update_meta_data( '_activation_code', $activation_code );
		$order->save();
		return true;
	}

	public static function wctlgm_display_activation_info( $text, $order ) {
		$activation_text = self::get_activation_info_text( $order );
		return $text . $activation_text;
	}

	private static function get_activation_info_text( $order ) {
		$bot_url         = get_option( 'wctlgm_bot_url' );
		$activation_code = $order->get_meta( '_activation_code', true );
		$output          = '</div><div class="wctlgm-activation-info alignwide">';

		if ( ! empty( $activation_code ) ) {
			$output .= '<h2>' . esc_html__( 'Activation Code', 'wctlgm' ) . '</h2>';
			$output .= '<p>' . esc_html__( 'Here is your activation code:', 'wctlgm' ) . ' <strong>' . esc_html( $activation_code ) . '</strong></p>';
			$output .= sprintf(
				'<p>%s <a href="%s" target="_blank">%s</a></p>',
				esc_html( __( 'Please click on the following link and send your activation code to our Telegram bot:', 'wctlgm' ) ),
				esc_url( $bot_url . '?activate=' . $activation_code ),
				esc_html( __( 'Start Chat', 'wctlgm' ) )
			);
			$output .= '<br>';
		}

		return apply_filters( 'wctlgm_activation_info_output', $output, $activation_code, $bot_url );
	}

	public static function wctlgm_email_activation_info( $order, $sent_to_admin, $plain_text, $email ) {
		if ( 'customer_completed_order' === $email->id || 'customer_processing_order' === $email->id ) {
			self::email_activation_info( $order, $plain_text );
		}
	}


	private static function email_activation_info( $order, $plain_text = false ) {
		$bot_url         = get_option( 'wctlgm_bot_url' );
		$activation_code = $order->get_meta( '_activation_code', true );
		if ( ! empty( $activation_code ) ) {
			if ( $plain_text ) {
				echo "\n" . esc_html__( 'Activation Code:', 'wctlgm' ) . ' ' . esc_html( $activation_code );
				echo "\n" . esc_html__( 'Please click the following link and send your activation code to our Telegram bot:', 'wctlgm' ) . ' ' . esc_url( $bot_url ) . '?activate=' . esc_html( $activation_code );
			} else {
				echo '<h2>' . esc_html__( ' Activation Code', 'wctlgm' ) . '</h2>';
				echo '<p>' . esc_html__( 'Here is your activation code:', 'wctlgm' ) . ' <strong>' . esc_html( $activation_code ) . '</strong></p>';
				printf(
					'<p>%s <a href="%s" target="_blank">%s</a></p>',
					esc_html( __( 'Please click on the following link and send your activation code to our Telegram bot:', 'wctlgm' ) ),
					esc_url( $bot_url . '?activate=' . $activation_code ),
					esc_html( __( 'Start Chat', 'wctlgm' ) )
				);
			}
		}
	}
}
