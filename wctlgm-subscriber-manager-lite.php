<?php
/**
 * @package WC_Telegram_Subscriber_Manager_Lite
 * @version 1.0.0
 * Plugin Name: WC Telegram Subscriber Manager Lite
 * Plugin URI: https://github.com/nickpagz/wctlgm-subscriber-manager-lite
 * Description: A plugin to automatically manage Telegram private channel subscribers via WooCommerce.
 * Version: 1.0.0
 * Author: Nick Pagazani
 * Author URI: https://github.com/nickpagz
 * Text Domain: wctlgm-subscriber-manager-lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'WCTLGM_SML_PLUGIN_BASE' ) ) {
	define( 'WCTLGM_SML_PLUGIN_BASE', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'WCTLGM_SML_PLUGIN_DIR' ) ) {
	define( 'WCTLGM_SML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin activation.
 */
function activate_wctlgm_subscriber_manager_lite() {
	// Check for custom post type.
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( WCTLGM_SML_PLUGIN_BASE );
		wp_die( esc_html__( 'Plugin not activated. WooCommerce not found.', 'wctlgm-subscriber-manager-lite' ) );
	}
}

register_activation_hook( __FILE__, 'activate_wctlgm_subscriber_manager_lite' );

/**
 * Plugin deactivation.
 */
function deactivate_wctlgm_subscriber_manager_lite() {
	// Nothing to do here, yet.
}

register_deactivation_hook( __FILE__, 'deactivate_wctlgm_subscriber_manager_lite' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);


require WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-subscriber-manager-lite.php';

function run_wctlgm_subscriber_manager_lite() {
	new WC_Telegram_Subscriber_Manager_Lite();
}

run_wctlgm_subscriber_manager_lite();
