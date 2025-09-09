<?php
/**
 * Telegram Channel Post-Activation email with invitation links.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/telegram-channel-activation.php.
 *
 * @package WooCommerce\Templates\Emails
 * @version 1.2.0
 *
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Thank you for activating your subscription. Below are your invitation links:', 'wctlgm-subscriber-manager-lite' ); ?></p>

<?php if ( ! empty( $invites ) ) : ?>
	<?php foreach ( $invites as $invite ) : ?>
		<div style="margin-bottom: 10px;">
			<span style="font-weight: bold;"><?php echo esc_html( $invite['name'] ); ?>:</span>
			<a href="<?php echo esc_url( $invite['invite_link'] ); ?>" style="color: <?php echo esc_attr( $email->get_option( 'link_color', '#96588a' ) ); ?>;"><?php esc_html_e( 'Join Channel', 'wctlgm-subscriber-manager-lite' ); ?></a>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

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
