<?php
/**
 * PHPUnit bootstrap for wctlgm-subscriber-manager-lite.
 *
 * Loads Composer autoloader, defines WordPress/WooCommerce stubs,
 * and loads the plugin class files.
 */

// Composer autoloader (PHPUnit, Brain\Monkey, Mockery).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WordPress constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

// Plugin constants.
if ( ! defined( 'WCTLGM_SML_PLUGIN_BASE' ) ) {
	define( 'WCTLGM_SML_PLUGIN_BASE', 'wctlgm-subscriber-manager-lite/wctlgm-subscriber-manager-lite.php' );
}
if ( ! defined( 'WCTLGM_SML_PLUGIN_DIR' ) ) {
	define( 'WCTLGM_SML_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

/*
 * Permanent function stubs.
 *
 * These are WordPress/WooCommerce functions that always behave the same in tests.
 * They are defined here so they exist before any class file is loaded, and they
 * cannot (and don't need to) be overridden by Brain\Monkey per-test.
 */

// Internationalization — passthrough.
function __( $text, $domain = 'default' ) {
	return $text;
}
function _e( $text, $domain = 'default' ) {
	echo $text;
}
function esc_html__( $text, $domain = 'default' ) {
	return $text;
}
function esc_html_e( $text, $domain = 'default' ) {
	echo $text;
}

// Escaping — passthrough.
function esc_html( $text ) {
	return $text;
}
function esc_attr( $text ) {
	return $text;
}
function esc_url( $url ) {
	return $url;
}
function esc_url_raw( $url ) {
	return $url;
}
function wp_kses_post( $data ) {
	return $data;
}

// Sanitization — passthrough.
function sanitize_text_field( $str ) {
	return $str;
}
function sanitize_url( $url ) {
	return $url;
}
function absint( $n ) {
	return abs( intval( $n ) );
}

// Encoding.
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

// Array utilities.
function wp_parse_args( $args, $defaults = array() ) {
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	} elseif ( is_string( $args ) ) {
		parse_str( $args, $args );
	}
	return array_merge( $defaults, $args );
}

// Error checking.
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

// WooCommerce logger.
function wc_get_logger() {
	return new WC_Logger();
}

// Load stub classes.
require_once __DIR__ . '/stubs/wordpress.php';
require_once __DIR__ . '/stubs/woocommerce.php';

// Load plugin class files in dependency order.
$plugin_dir = dirname( __DIR__ ) . '/includes/';

// Level 1: No plugin dependencies.
require_once $plugin_dir . 'class-subscriber-manager-lite-wctlgm-logger.php';

// Level 2: Depends on level 1.
require_once $plugin_dir . 'class-subscriber-manager-lite-wctlgm-api-handler.php';
require_once $plugin_dir . 'class-subscriber-manager-lite-wctlgm-database.php';
require_once $plugin_dir . 'class-subscriber-manager-lite-wctlgm-subscriptions-handler.php';

// Level 3: Depends on levels 1-2.
require_once $plugin_dir . 'class-subscriber-manager-lite-wctlgm-bot-interaction-handler.php';
require_once $plugin_dir . 'class-subscriber-manager-lite-wctlgm-order-handler.php';

// Level 4: Depends on level 3.
require_once $plugin_dir . 'class-subscriber-manager-lite-wctlgm-endpoint-handler.php';

// Load base test case.
require_once __DIR__ . '/TestCase.php';
