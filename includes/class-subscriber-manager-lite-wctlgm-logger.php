<?php

namespace Subscriber_Manager_Lite_for_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Logger
 *
 * Centralized logging utility for the Telegram Subscriber Manager plugin.
 *
 * @package Subscriber_Manager_Pro_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Logger {

	/**
	 * Logger instance.
	 *
	 * @var WC_Logger
	 */
	private static $logger = null;

	/**
	 * Default context for all log entries.
	 *
	 * @var array
	 */
	private static $default_context = array(
		'source' => 'wctlgm-subscriber-manager-lite',
	);

	/**
	 * Get the logger instance.
	 *
	 * @return WC_Logger
	 */
	private static function get_logger() {
		if ( null === self::$logger ) {
			self::$logger = wc_get_logger();
		}
		return self::$logger;
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function debug( $message, $context = array() ) {
		self::get_logger()->debug( $message, self::merge_context( $context ) );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function info( $message, $context = array() ) {
		self::get_logger()->info( $message, self::merge_context( $context ) );
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function notice( $message, $context = array() ) {
		self::get_logger()->notice( $message, self::merge_context( $context ) );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function warning( $message, $context = array() ) {
		self::get_logger()->warning( $message, self::merge_context( $context ) );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function error( $message, $context = array() ) {
		self::get_logger()->error( $message, self::merge_context( $context ) );
	}

	/**
	 * Log a critical message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function critical( $message, $context = array() ) {
		self::get_logger()->critical( $message, self::merge_context( $context ) );
	}

	/**
	 * Log an alert message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function alert( $message, $context = array() ) {
		self::get_logger()->alert( $message, self::merge_context( $context ) );
	}

	/**
	 * Log an emergency message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function emergency( $message, $context = array() ) {
		self::get_logger()->emergency( $message, self::merge_context( $context ) );
	}

	/**
	 * Merge additional context with default context.
	 *
	 * @param array $context Additional context data.
	 * @return array Merged context array.
	 */
	private static function merge_context( $context = array() ) {
		return array_merge( self::$default_context, $context );
	}

	/**
	 * Log API request details.
	 *
	 * @param string $method    HTTP method.
	 * @param string $endpoint  API endpoint.
	 * @param array  $data      Request data.
	 * @param array  $context   Additional context data.
	 */
	public static function log_api_request( $method, $endpoint, $data = array(), $context = array() ) {
		self::debug(
			"API Request: {$method} {$endpoint}",
			array_merge(
				$context,
				array(
					'method'       => $method,
					'endpoint'     => $endpoint,
					'request_data' => $data,
				)
			)
		);
	}

	/**
	 * Log API response details.
	 *
	 * @param string $endpoint  API endpoint.
	 * @param array  $response  API response data.
	 * @param array  $context   Additional context data.
	 */
	public static function log_api_response( $endpoint, $response, $context = array() ) {
		$level   = isset( $response['ok'] ) && $response['ok'] ? 'debug' : 'error';
		$message = isset( $response['ok'] ) && $response['ok']
			? "API Response: Success for {$endpoint}"
			: "API Response: Error for {$endpoint}";

		self::$level(
			$message,
			array_merge(
				$context,
				array(
					'endpoint' => $endpoint,
					'response' => $response,
				)
			)
		);
	}
}
