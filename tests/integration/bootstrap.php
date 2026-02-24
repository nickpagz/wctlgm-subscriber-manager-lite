<?php
/**
 * Integration test bootstrap for wctlgm-subscriber-manager-lite.
 *
 * Loads the WordPress test framework, activates WooCommerce,
 * and loads the plugin under test. Requires wp-env or equivalent
 * WordPress test environment.
 */

// Path to WordPress test framework inside wp-env container.
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// Fallback: try the wp-phpunit Composer package.
	$_tests_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test framework.\n";
	echo "If using wp-env, make sure the environment is running: npx wp-env start\n";
	echo "Looked in: " . $_tests_dir . "\n";
	exit( 1 );
}

// Load the test framework bootstrap functions.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WooCommerce and the plugin before tests run.
 *
 * Runs during 'muplugins_loaded' to ensure WooCommerce is
 * available when the plugin initializes.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		// Load WooCommerce — directory name varies by install method.
		$wc_path = null;
		$wc_candidates = glob( WP_PLUGIN_DIR . '/woocommerce*/woocommerce.php' );
		if ( ! empty( $wc_candidates ) ) {
			$wc_path = $wc_candidates[0];
		}

		if ( $wc_path && file_exists( $wc_path ) ) {
			require $wc_path;
		} else {
			echo "WooCommerce not found in: " . WP_PLUGIN_DIR . "\n";
			echo "Ensure WooCommerce is installed in the test environment.\n";
			exit( 1 );
		}

		// Skip Freemius SDK during tests.
		define( 'WCTLGM_SML_INTEGRATION_TESTING', true );

		// Load the plugin.
		require dirname( __DIR__, 2 ) . '/wctlgm-subscriber-manager-lite.php';
	}
);

/**
 * After WordPress and plugins are loaded, install WooCommerce tables
 * and configure default plugin options.
 */
tests_add_filter(
	'setup_theme',
	function () {
		// Install WooCommerce DB tables.
		WC_Install::install();

		// Set WooCommerce as active.
		update_option( 'woocommerce_db_version', WC()->version );

		// Default plugin options for test environment.
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
);

// Boot WordPress test framework.
require $_tests_dir . '/includes/bootstrap.php';

// Load integration test helpers.
require_once __DIR__ . '/helpers/class-telegram-api-interceptor.php';
require_once __DIR__ . '/helpers/class-wc-test-helpers.php';
require_once __DIR__ . '/helpers/class-telegram-webhook-simulator.php';

// Load the base integration test case.
require_once __DIR__ . '/IntegrationTestCase.php';
