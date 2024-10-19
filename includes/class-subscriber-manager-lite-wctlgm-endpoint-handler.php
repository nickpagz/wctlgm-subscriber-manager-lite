<?php

namespace Subscriber_Manager_Lite_for_WooCommerce_and_Telegram;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler
 *
 * The class that sets up the required API endpoint for handling Telegram bot interaction requests.
 *
 * @package Subscriber_Manager_Lite_for_WooCommerce_and_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_telegram_webhook_endpoint' ) );
	}

	public function register_telegram_webhook_endpoint() {
		register_rest_route(
			'wctlgm/v1',
			'/telegram-bot/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_telegram_requests' ),
				'permission_callback' => array( $this, 'check_telegram_token_permission' ),
			)
		);
	}

	public function check_telegram_token_permission( WP_REST_Request $request ) {
		$received_token = (string) $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
		$saved_token    = (string) get_option( 'wctlgm_secret_token' );
		return $received_token === $saved_token;
	}

	public function handle_telegram_requests( WP_REST_Request $request ) {
		$data        = $request->get_json_params();
		$bot_handler = new \Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler();
		$response    = $bot_handler->process_telegram_request( $data );

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response( $response->get_error_message(), 400 );
		}

		return new \WP_REST_Response( $this->prepare_response_payload( $response ), 200 );
	}

	protected function prepare_response_payload( $response ) {
		if ( isset( $response['action'] ) && 'sendMessage' === $response['action'] ) {
			return array(
				'method'  => 'sendMessage',
				'chat_id' => $response['chat_id'],
				'text'    => $response['text'],
			);
		}

		return array( 'status' => 'ok' );
	}
}
