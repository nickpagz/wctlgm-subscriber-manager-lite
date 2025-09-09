<?php

namespace Subscriber_Manager_Lite_for_Telegram;

use WP_Error;

/**
 * Subscriber_Manager_Lite_WCTLGM_API_Handler
 *
 * Handles interactions with the Telegram API.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_API_Handler {
	private $bot_token;
	private $logger;
	private $commands;

	public function __construct() {
		$this->bot_token = get_option( 'wctlgm_bot_token' );
		$this->logger    = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Logger();
		$this->commands  = array(
			array(
				'command'     => 'start',
				'description' => __( 'Start the bot', 'wctlgm-subscriber-manager-lite' ),
			),
			array(
				'command'     => 'activate',
				'description' => __( 'Activates your subscription', 'wctlgm-subscriber-manager-lite' ),
			),
			array(
				'command'     => 'help',
				'description' => __( 'Provides help information', 'wctlgm-subscriber-manager-lite' ),
			),
		);
	}

	public function handle_set_webhook_actions( $url, $secret_token ) {
		$set_webhook_response = $this->set_webhook( $url, $secret_token );
		if ( is_wp_error( $set_webhook_response ) ) {
			return $set_webhook_response;
		}

		$set_commands_response = $this->set_commands();
		if ( is_wp_error( $set_commands_response ) ) {
			return $set_commands_response;
		} else {
			return 'Webhook and commands are set successfully.';
		}
	}

	public function set_webhook( $url, $secret_token ) {
		if ( empty( $this->bot_token ) ) {
			$this->logger->error( __( 'No Bot Token found in set_webhook.', 'wctlgm-subscriber-manager-lite' ) );
			return new \WP_Error( 'no_bot_token', __( 'No Bot Token found.', 'wctlgm-subscriber-manager-lite' ) );
		}

		$allowed_updates = $this->get_allowed_updates();

		$api_url  = "https://api.telegram.org/bot{$this->bot_token}/setWebhook";
		$response = wp_remote_post(
			$api_url,
			array(
				'body'    => wp_json_encode(
					array(
						'url'             => $url,
						'secret_token'    => $secret_token,
						'allowed_updates' => $allowed_updates,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( __( 'Failed to set_webhook. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			$this->logger->error( __( 'Failed to set_webhook. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
			return new \WP_Error( 'telegram_api_error', $error_message );
		}

		return $data;
	}

	public function set_commands() {
		$api_url  = "https://api.telegram.org/bot{$this->bot_token}/setMyCommands";
		$response = wp_remote_post(
			$api_url,
			array(
				'body'    => wp_json_encode(
					array(
						'commands' => $this->commands,
						'scope'    => wp_json_encode( array( 'type' => 'all_private_chats' ) ),
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( __( 'Failed to set_commands. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			$this->logger->error( __( 'Failed to set_commands. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
			return new \WP_Error( 'telegram_api_error', $error_message );
		}

		return $data;
	}

	public function send_message( $chat_id, $message ) {
		$url      = "https://api.telegram.org/bot{$this->bot_token}/sendMessage";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id' => $chat_id,
						'text'    => $message,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
	}

	public function generate_invite_link( $chat_id ) {
		if ( empty( $this->bot_token ) ) {
			return new \WP_Error( 'no_bot_token', 'No Bot Token found.' );
		}

		$url      = "https://api.telegram.org/bot{$this->bot_token}/createChatInviteLink";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id'              => $chat_id,
						'creates_join_request' => 'true',
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['ok'] ) && $data['ok'] ) {
			return $data['result']['invite_link'];
		}

		return new \WP_Error( 'api_error', isset( $data['description'] ) ? $data['description'] : 'Failed to create invite link.' );
	}

	public function approve_join_request( $chat_id, $user_id ) {
		$url      = "https://api.telegram.org/bot{$this->bot_token}/approveChatJoinRequest";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id' => $chat_id,
						'user_id' => $user_id,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			return new \WP_Error( 'telegram_api_error', $error_message );
		}

		return $data;
	}

	public function revoke_invite_link( $chat_id, $invite_link ) {
		$url      = "https://api.telegram.org/bot{$this->bot_token}/revokeChatInviteLink";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id'     => $chat_id,
						'invite_link' => $invite_link,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			return new \WP_Error( 'telegram_api_error', $error_message );
		}

		return $data;
	}

	public function deny_join_request( $chat_id, $user_id ) {
		$url      = "https://api.telegram.org/bot{$this->bot_token}/declineChatJoinRequest";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id' => $chat_id,
						'user_id' => $user_id,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			return new \WP_Error( 'telegram_api_error', $error_message );
		}

		return $data;
	}

	/**
	 * Get allowed updates based on activation flow setting.
	 *
	 * @return array
	 */
	private function get_allowed_updates() {
		$require_activation = get_option( 'wctlgm_require_activation_flow', false );

		// Base updates (for now)
		$allowed_updates = array( 'chat_join_request', 'edited_message', 'edited_channel_post', 'chat_member' );

		// Add message updates only if activation flow is enabled
		if ( $require_activation ) {
			$allowed_updates[] = 'message';
		}

		// Log allowed_updates.
		$this->logger->info( __( 'Allowed updates: ', 'wctlgm-subscriber-manager-lite' ) . implode( ',', $allowed_updates ) );

		return $allowed_updates;
	}
}
