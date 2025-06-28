<?php
/**
 * Telegram Channel Activation email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/telegram-channel-activation.php.
 *
 * @package WooCommerce\Templates\Emails\Plain
 * @version 1.2.0
 *
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__( 'Thank you for activating your subscription. Below are your private channel invite links:', 'wctlgm-subscriber-manager-lite' ) . "\n\n";

if ( ! empty( $invites ) ) {
	foreach ( $invites as $invite ) {
		echo esc_html( $invite['name'] ) . ': ' . esc_url( $invite['invite_link'] ) . "\n";
	}
}

echo "\n" . esc_html__( 'If you have any issues or questions, please contact support.', 'wctlgm-subscriber-manager-lite' ) . "\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
