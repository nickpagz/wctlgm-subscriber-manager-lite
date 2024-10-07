<?php

/**
 * Class WC_Telegram_API_Handler
 *
 * The main plugin class.
 *
 * @package WC_Telegram_Subscriber_Manager
 */
class WC_Telegram_API_Handler {
	private $bot_token;

	private $commands = array(
		array(
			'command'     => 'start',
			'description' => 'Start the bot',
		),
		array(
			'command'     => 'activate',
			'description' => 'Activates your subscription',
		),
		array(
			'command'     => 'help',
			'description' => 'Provides help information',
		),
	);

	public function __construct() {
		$this->bot_token = get_option( 'wctlgm_bot_token' );
	}

	public function handle_set_webhook_actions( $url, $secret_token ) {
		$set_webhook_response = $this->set_webhook( $url, $secret_token );
		if ( is_wp_error( $set_webhook_response ) ) {
			wp_send_json_error( array( 'message' => $set_webhook_response->get_error_message() ) );
			return;
		}

		$set_commands_response = $this->set_commands();
		if ( is_wp_error( $set_commands_response ) ) {
			wp_send_json_error( array( 'message' => $set_commands_response->get_error_message() ) );
		} else {
			wp_send_json_success( array( 'message' => 'Webhook and commands are set successfully.' ) );
		}
	}

	public function set_webhook( $url, $secret_token ) {
		if ( empty( $this->bot_token ) ) {
			return new WP_Error( 'no_bot_token', __( 'No Bot Token found.', 'wctlgm-subscriber-manager-lite' ) );
		}

		$api_url  = "https://api.telegram.org/bot{$this->bot_token}/setWebhook";
		$response = wp_remote_post(
			$api_url,
			array(
				'body'    => wp_json_encode(
					array(
						'url'             => $url,
						'secret_token'    => $secret_token,
						'allowed_updates' => array( 'message', 'edited_channel_post', 'chat_member', 'chat_join_request' ),
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
			return new WP_Error( 'no_bot_token', 'No Bot Token found.' );
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

		return new WP_Error( 'api_error', isset( $data['description'] ) ? $data['description'] : 'Failed to create invite link.' );
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
		return $data;
	}

	public function deny_join_request( $chat_id, $user_id ) {
		$url      = "https://api.telegram.org/bot{$this->bot_token}/denyJoinChatRequest";
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
		return $data;
	}
}
