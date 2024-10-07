<?php

/**
 * Class WC_Telegram_Subscriber_Manager
 *
 * The main plugin class.
 *
 * @package WC_Telegram_Subscriber_Manager
 */
class WC_Telegram_Subscriber_Manager_Lite {

	/**
	 * WC_Telegram_Subscriber_Manager_Lite constructor.
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
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-subscriber-manager-settings.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-subscriptions-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-order-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-api-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-bot-interaction-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-wc-telegram-endpoint-handler.php';
	}

	/** */
	private function maybe_load_extensions() {
		do_action( 'wctlgm_load_extensions' );
	}

	/**
	 * Defines the admin settings page.
	 */
	private function define_admin_settings() {
		new WC_Telegram_Subscriber_Manager_Settings();
	}

	/**
	 * Initialize event handlers and other runtime components.
	 */
	private function initialize_handlers() {
		add_action(
			'plugins_loaded',
			function () {
				new WC_Telegram_Endpoint_Handler();
			},
			10
		);

		add_action( 'init', array( 'WC_Telegram_Bot_Interaction_Handler', 'init' ) );
		add_action( 'plugins_loaded', array( 'WC_Telegram_Order_Handler', 'init' ) );
		add_action(
			'plugins_loaded',
			function () {
				$this->maybe_load_extensions();
			},
			10
		);
	}
}
