<?php

/**
 * @package Subscriber_Manager_Lite_for_Telegram
 * @version 2.0.0
 * Plugin Name: Subscriber Manager Lite for Telegram
 * Plugin URI: https://wctlgm.com
 * Description: Automatically manage access to private Telegram channels and groups through WooCommerce. Invite links, subscriber management, and more.
 * Version: 2.0.0
 * Author: Rektification
 * Author URI: https://wctlgm.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wctlgm-subscriber-manager-lite
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'WCTLGM_SML_INTEGRATION_TESTING' ) && ! function_exists( 'wctlgm_fs' ) ) {
	// Create a helper function for easy SDK access.
	function wctlgm_fs() {
		global $wctlgm_fs;

		if ( ! isset( $wctlgm_fs ) ) {
			// Include Freemius SDK.
			require_once __DIR__ . '/vendor/autoload.php';
			$wctlgm_fs = fs_dynamic_init(
				array(
					'id'                  => '16907',
					'slug'                => 'wctlgm-subscriber-manager-lite',
					'premium_slug'        => 'wctlgm-subscriber-manager',
					'type'                => 'plugin',
					'public_key'          => 'pk_f64df69d37ee38537f9f2a1abbb61',
					'is_premium'          => false,
					'premium_suffix'      => '',
					'has_premium_version' => true,
					'is_premium_only'     => false,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'navigation'          => 'tabs',
					'menu'                => array(
						'slug'   => 'wctlgm-settings',
						'parent' => array(
							'slug' => 'options-general.php',
						),
					),
				)
			);
		}

		return $wctlgm_fs;
	}

	// Init Freemius.
	wctlgm_fs();
	// Signal that SDK was initiated.
	do_action( 'wctlgm_fs_loaded' );
}



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
function wctlgm_subscriber_manager_lite_activation() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_die( 'Plugin not activated. WooCommerce not found.' );
	}

	// Check if the pro plugin is active and prevent activation
	$active_plugins = get_option( 'active_plugins', array() );
	if ( in_array( 'wctlgm-subscriber-manager/wctlgm-subscriber-manager.php', $active_plugins, true ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			'<h1>' . esc_html__( 'Plugin Activation Failed', 'wctlgm-subscriber-manager-lite' ) . '</h1>' .
			'<p>' . esc_html__( 'The Pro version of Subscriber Manager for Telegram is already active. Please deactivate the Pro version before activating the Lite version.', 'wctlgm-subscriber-manager-lite' ) . '</p>' .
			'<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Return to Plugins page', 'wctlgm-subscriber-manager-lite' ) . '</a></p>'
		);
	}

	// Trigger database table creation and migration on next admin_init.
	set_transient( 'wctlgm_needs_setup', true, WEEK_IN_SECONDS );
}

register_activation_hook( __FILE__, 'wctlgm_subscriber_manager_lite_activation' );

/**
 * Plugin deactivation.
 */
function wctlgm_subscriber_manager_lite_deactivation() {
	// Nothing to do here, yet.
}

register_deactivation_hook( __FILE__, 'wctlgm_subscriber_manager_lite_deactivation' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

function wctlgm_subscriber_manager_lite_check_for_pro_plugin() {
	// Check if the pro plugin is active
	$active_plugins = get_option( 'active_plugins', array() );
	if ( in_array( 'wctlgm-subscriber-manager/wctlgm-subscriber-manager.php', $active_plugins, true ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		add_action( 'admin_notices', 'wctlgm_subscriber_manager_lite_pro_plugin_active_notice' );
	}
}
add_action( 'admin_init', 'wctlgm_subscriber_manager_lite_check_for_pro_plugin' );

function wctlgm_subscriber_manager_lite_pro_plugin_active_notice() {
	?>
	<div class="notice notice-warning is-dismissible">
		<p><?php esc_html_e( 'The Pro version of Subscriber Manager for Telegram is active. The Lite version has been deactivated to prevent conflicts.', 'wctlgm-subscriber-manager-lite' ); ?></p>
	</div>
	<?php
}

require WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm.php';
require WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-logger.php';

function wctlgm_subscriber_manager_lite_start() {
	new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM();
}

wctlgm_subscriber_manager_lite_start();
