<?php
/**
 * Telegram Channel Invite Links email. Used when the activation flow is disabled.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/telegram-channel-invite-links.php.
 *
 * @package WooCommerce\Templates\Emails
 * @version 1.4.0
 *
 * @since 1.4.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Thank you for your purchase! Below are your invite links:', 'wctlgm-subscriber-manager-lite' ); ?></p>

<?php if ( ! empty( $invites ) ) : ?>
	<?php foreach ( $invites as $invite ) : ?>
		<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 5px;">
			<span style="font-weight: bold; font-size: 16px;"><?php echo esc_html( $invite['name'] ); ?>:</span><br>
			<a href="<?php echo esc_url( $invite['invite_link'] ); ?>" style="color: <?php echo esc_attr( $email->get_option( 'link_color', '#96588a' ) ); ?>; text-decoration: underline;"><?php echo esc_url( $invite['invite_link'] ); ?></a>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<p><?php esc_html_e( 'These invite links are one-time use only and should not be shared with others.', 'wctlgm-subscriber-manager-lite' ); ?></p>

<p><?php esc_html_e( 'If you have any issues or questions, please contact support.', 'wctlgm-subscriber-manager-lite' ); ?></p>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
