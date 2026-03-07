<?php

namespace Subscriber_Manager_Lite_for_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM
 *
 * The main plugin class.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
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
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-email-handler.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-database.php';
		require_once WCTLGM_SML_PLUGIN_DIR . 'includes/class-subscriber-manager-lite-wctlgm-users-list-table.php';
	}

	/**
	 * Defines the admin settings page.
	 */
	private function define_admin_settings() {
		add_action( 'init', array( $this, 'init_settings' ) );
	}

	/**
	 * Initialize settings.
	 */
	public function init_settings() {
		new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Settings();
	}

	/**
	 * Initialize event handlers and other runtime components.
	 */
	private function initialize_handlers() {
		Subscriber_Manager_Lite_WCTLGM_Database::init();

		add_action(
			'plugins_loaded',
			function () {
				new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();
			},
			10
		);

		add_action( 'init', array( '\Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler', 'init' ) );
		add_action( 'plugins_loaded', array( '\Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Order_Handler', 'init' ) );
		add_action( 'plugins_loaded', array( '\Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Email_Handler', 'init' ) );
	}
}
