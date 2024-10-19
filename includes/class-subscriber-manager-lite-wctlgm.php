<?php

namespace Subscriber_Manager_Lite_for_WooCommerce_and_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM
 *
 * The main plugin class.
 *
 * @package Subscriber_Manager_Lite_for_WooCommerce_and_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM {

	/**
	 * Subscriber_Manager_Lite_WCTLGM constructor.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_settings();
		$this->initialize_handlers();
	}

	/**
	 * Loads additional dependencies for the plugin.
	 */
	private function load_dependencies() {
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-settings.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-subscriptions-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-order-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-api-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-bot-interaction-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-endpoint-handler.php';
	}

	/**
	 * Defines the admin settings page.
	 */
	private function define_admin_settings() {
		new \Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_Settings();
	}

	/**
	 * Initialize event handlers and other runtime components.
	 */
	private function initialize_handlers() {
		add_action(
			'plugins_loaded',
			function () {
				new \Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
			},
			10
		);

		add_action( 'init', array( '\Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler', 'init' ) );
		add_action( 'plugins_loaded', array( '\Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_Order_Handler', 'init' ) );
	}
}
