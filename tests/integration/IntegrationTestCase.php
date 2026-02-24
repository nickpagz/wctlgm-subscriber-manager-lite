<?php
/**
 * Base integration test case for wctlgm-subscriber-manager-lite.
 *
 * Extends WP_UnitTestCase to provide:
 * - Telegram API interceptor (captures all wp_remote_post calls to Telegram)
 * - WooCommerce product/order factory helpers
 * - Webhook simulation helpers
 * - Automatic cleanup via transactional rollback (inherited from WP_UnitTestCase)
 */
class WCTLGM_Lite_Integration_TestCase extends WP_UnitTestCase {

	/** @var WCTLGM_Lite_Telegram_API_Interceptor */
	protected $telegram_api;

	/** @var WCTLGM_Lite_WC_Test_Helpers */
	protected $wc_helpers;

	/** @var WCTLGM_Lite_Telegram_Webhook_Simulator */
	protected $webhook_simulator;

	public function set_up() {
		parent::set_up();

		// Initialize helpers.
		$this->telegram_api      = new WCTLGM_Lite_Telegram_API_Interceptor();
		$this->wc_helpers        = new WCTLGM_Lite_WC_Test_Helpers();
		$this->webhook_simulator = new WCTLGM_Lite_Telegram_Webhook_Simulator();

		// Install HTTP interceptor to prevent real Telegram API calls.
		$this->telegram_api->install();

		// Reset static properties that persist between tests.
		$this->reset_static_property(
			'Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Logger',
			'logger'
		);

		// Ensure default plugin options are set for each test.
		update_option( 'wctlgm_bot_token', 'test_bot_token_for_integration' );
		update_option( 'wctlgm_secret_token', 'test_secret_token_for_integration' );
		update_option( 'wctlgm_bot_url', 'https://t.me/test_integration_bot' );
		update_option(
			'wctlgm_channels',
			array(
				array(
					'id'   => '-1001234567890',
					'name' => 'Test Channel',
				),
				array(
					'id'   => '-1009876543210',
					'name' => 'Test Group',
				),
			)
		);
		update_option( 'wctlgm_require_activation_flow', false );
		update_option( 'wctlgm_allow_external_invites', false );
	}

	/**
	 * Filter out known HPOS meta_query notices before WP_UnitTestCase checks.
	 *
	 * The plugin uses meta_query with wc_get_orders() which triggers a
	 * _doing_it_wrong notice under WooCommerce HPOS.
	 */
	public function assert_post_conditions() {
		unset( $this->caught_doing_it_wrong['WC_Order_Data_Store_CPT::query'] );
		parent::assert_post_conditions();
	}

	public function tear_down() {
		$this->telegram_api->uninstall();
		parent::tear_down();
	}

	/**
	 * Reset a private/protected static property via reflection.
	 *
	 * @param string $class    Fully qualified class name.
	 * @param string $property Property name.
	 * @param mixed  $value    Value to set (null by default).
	 */
	protected function reset_static_property( $class, $property, $value = null ) {
		try {
			$reflection = new ReflectionClass( $class );
			$prop       = $reflection->getProperty( $property );
			$prop->setAccessible( true );
			$prop->setValue( null, $value );
		} catch ( ReflectionException $e ) {
			// Class or property doesn't exist in this environment, skip.
		}
	}
}
