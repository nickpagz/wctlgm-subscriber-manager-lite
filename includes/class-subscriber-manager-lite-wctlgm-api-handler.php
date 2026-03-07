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
			$this->logger->error( __( 'Failed to send message. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
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
			$this->logger->error( __( 'Failed to generate invite link. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['ok'] ) && $data['ok'] ) {
			return $data['result']['invite_link'];
		}
		$this->logger->error( __( 'Failed to generate invite link. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );

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
			$this->logger->error( __( 'Failed to approve join request. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			$this->logger->error( __( 'Failed to approve join request. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
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
			$this->logger->error( __( 'Failed to revoke invite link. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			$this->logger->error( __( 'Failed to revoke invite link. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
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
			$this->logger->error( __( 'Failed to deny join request. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			$this->logger->error( __( 'Failed to deny join request. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
			return new \WP_Error( 'telegram_api_error', $error_message );
		}

		return $data;
	}

	/**
	 * Get a chat member's status in a channel or group.
	 *
	 * Uses transient caching to avoid hitting Telegram API rate limits.
	 *
	 * @param string $user_id    The Telegram user ID.
	 * @param string $channel_id The channel or group ID.
	 * @return string Member status: creator, administrator, member, restricted, left, kicked, or error.
	 */
	public function get_chat_member_status( $user_id, $channel_id ) {
		if ( empty( $this->bot_token ) ) {
			return 'error';
		}

		$cache_key = 'wctlgm_member_' . md5( $user_id . '_' . $channel_id );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$url      = "https://api.telegram.org/bot{$this->bot_token}/getChatMember";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id' => $channel_id,
						'user_id' => $user_id,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( __( 'Failed to get_chat_member_status. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return 'error';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['ok'] ) && $data['ok'] && isset( $data['result']['status'] ) ) {
			$status = $data['result']['status'];
			set_transient( $cache_key, $status, 5 * MINUTE_IN_SECONDS );
			return $status;
		}

		$this->logger->error( __( 'Failed to get_chat_member_status. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
		return 'error';
	}

	/**
	 * Get full chat member data from Telegram (status + user info).
	 *
	 * Unlike get_chat_member_status() which only returns the status string,
	 * this returns the full ChatMember result including the user object
	 * (first_name, last_name, username).
	 *
	 * @param string $user_id    The Telegram user ID.
	 * @param string $channel_id The channel or group ID.
	 * @return array|string Array with 'status' and 'user' keys on success, or 'error' string on failure.
	 */
	public function get_chat_member( $user_id, $channel_id ) {
		if ( empty( $this->bot_token ) ) {
			return 'error';
		}

		$url      = "https://api.telegram.org/bot{$this->bot_token}/getChatMember";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id' => $channel_id,
						'user_id' => $user_id,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'error';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['ok'] ) && $data['ok'] && isset( $data['result']['status'] ) ) {
			return array(
				'status' => $data['result']['status'],
				'user'   => isset( $data['result']['user'] ) ? $data['result']['user'] : array(),
			);
		}

		return 'error';
	}

	/**
	 * Remove (ban) a user from a channel or group.
	 *
	 * Calls Telegram's banChatMember API — this is a permanent ban.
	 *
	 * @param string $user_id    The Telegram user ID.
	 * @param string $channel_id The channel or group ID.
	 * @return array|\WP_Error API response data or WP_Error on failure.
	 */
	public function remove_user_from_channel( $user_id, $channel_id ) {
		if ( empty( $this->bot_token ) ) {
			return new \WP_Error( 'missing_bot_token', __( 'Bot token is not configured.', 'wctlgm-subscriber-manager-lite' ) );
		}

		$url      = "https://api.telegram.org/bot{$this->bot_token}/banChatMember";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id' => $channel_id,
						'user_id' => $user_id,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( __( 'Failed to remove_user_from_channel. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			$this->logger->error( __( 'Failed to remove_user_from_channel. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
			return new \WP_Error( 'telegram_api_error', $error_message );
		}

		return $data;
	}

	/**
	 * Unban a user from a channel or group.
	 *
	 * Calls Telegram's unbanChatMember API — removes without banning, allows rejoin.
	 *
	 * @param string $user_id    The Telegram user ID.
	 * @param string $channel_id The channel or group ID.
	 * @return array|\WP_Error API response data or WP_Error on failure.
	 */
	public function unban_user_from_channel( $user_id, $channel_id ) {
		if ( empty( $this->bot_token ) ) {
			return new \WP_Error( 'missing_bot_token', __( 'Bot token is not configured.', 'wctlgm-subscriber-manager-lite' ) );
		}

		$url      = "https://api.telegram.org/bot{$this->bot_token}/unbanChatMember";
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode(
					array(
						'chat_id' => $channel_id,
						'user_id' => $user_id,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( __( 'Failed to unban_user_from_channel. WordPress error: ', 'wctlgm-subscriber-manager-lite' ) . $response->get_error_message() );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['ok'] ) || ! $data['ok'] ) {
			$error_message = isset( $data['description'] ) ? $data['description'] : 'Unknown error';
			$this->logger->error( __( 'Failed to unban_user_from_channel. Telegram API error: ', 'wctlgm-subscriber-manager-lite' ) . $body );
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
