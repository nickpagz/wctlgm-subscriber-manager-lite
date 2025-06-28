<?php

namespace Subscriber_Manager_Lite_for_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Email_Handler
 *
 * Handles email functionality for the plugin.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Email_Handler {

	/**
	 * Static initialization method.
	 */
	public static function init() {
		// Only initialize if WooCommerce is available
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_email_classes', array( __CLASS__, 'add_email_classes' ) );
		}
	}

	/**
	 * Add our custom email classes to WooCommerce.
	 *
	 * @param array $email_classes Array of WooCommerce email classes.
	 * @return array
	 */
	public static function add_email_classes( $email_classes ) {
		// Load the email class file only when WooCommerce is ready
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/emails/class-subscriber-manager-lite-wctlgm-activation-email.php';

		// Add our email class
		$email_classes['wctlgm_activation'] = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Activation_Email();

		return $email_classes;
	}
}
