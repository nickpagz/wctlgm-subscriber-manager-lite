<?php

/**
 * @package WC_Telegram_Subscriber_Manager_Lite
 * @version 1.0.0
 * Plugin Name: WC Telegram Subscriber Manager Lite
 * Plugin URI: https://github.com/nickpagz/wctlgm-subscriber-manager-lite
 * Description: A plugin to automatically manage Telegram private channel subscribers via WooCommerce.
 * Version: 1.0.0
 * Author: Rektification
 * Author URI: https://turtlesignals.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

function check_for_pro_plugin() {
	// Check if the pro plugin is active
	if ( is_plugin_active( 'wctlgm-subscriber-manager/wctlgm-subscriber-manager.php' ) ) {
		// Deactivate the lite plugin
		deactivate_plugins( plugin_basename( __FILE__ ) );

		// Display an admin notice
		add_action( 'admin_notices', 'pro_plugin_active_notice' );
	}
}
add_action( 'admin_init', 'check_for_pro_plugin' );

function pro_plugin_active_notice() {
	?>
	<div class="notice notice-warning is-dismissible">
		<p><?php esc_html_e( 'The Pro version of this plugin is active. The Lite version has been deactivated to prevent conflicts.', 'wctlgm-subscriber-manager-lite' ); ?></p>
	</div>
	<?php
}

require WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-subscriber-manager-lite.php';

function run_wctlgm_subscriber_manager_lite() {
	new \WC_Telegram_Subscriber_Manager_Lite\WC_Telegram_Subscriber_Manager_Lite();
}

run_wctlgm_subscriber_manager_lite();
