<?php

namespace Subscriber_Manager_Lite_for_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler
 *
 * The class that sets up the required API endpoint for handling Telegram bot interaction requests.
 *
 * @package Subscriber_Manager_Lite_for_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler {

	private $api_handler;
	private $chat_id;
	private $user_id;

	public function __construct() {
		$this->api_handler = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_API_Handler();
	}

	public static function init() {
		add_action( 'wctglm_send_activation_email', array( __CLASS__, 'send_activation_email' ) );
	}

	public function process_telegram_request( $data ) {
		if ( isset( $data['chat_join_request'] ) ) {
			return $this->process_join_request( $data );
		}

		if ( ( isset( $data['edited_channel_post'] ) ) || ( isset( $data['edited_message'] ) ) ) {
			$chat_id = null;
			if ( isset( $data['edited_channel_post']['chat']['id'] ) ) {
				$chat_id = $data['edited_channel_post']['chat']['id'];
			} elseif ( isset( $data['edited_message']['chat']['id'] ) ) {
				$chat_id = $data['edited_message']['chat']['id'];
			}

			if ( null !== $chat_id ) {
				$chat_id = sanitize_text_field( $chat_id );
				if ( $this->is_action_initiated_from_settings() ) {
					// Optionally check if the chat ID matches expected channels
					return $this->save_channel_id( $chat_id );
				}
			}
			return array( 'action' => 'none' );
		}

		if ( ! isset( $data['message'] ) ) {
			return array( 'action' => 'none' );
		}

		if ( isset( $data['message']['chat']['type'] ) && 'private' !== $data['message']['chat']['type'] ) {
			return array( 'action' => 'none' );
		}

		$this->chat_id = sanitize_text_field( $data['message']['chat']['id'] );
		$this->user_id = sanitize_text_field( $data['message']['from']['id'] );

		list( $command, $args ) = $this->extract_command_and_args( $data['message'] );

		switch ( $command ) {
			case '/start':
				return $this->handle_start_command( $args );
			case '/activate':
				return $this->handle_activation_command( $args );
			case '/help':
				return $this->handle_help_command();
			default:
				return $this->build_response( __( 'Invalid. Please use the /help command for more information.', 'wctlgm-subscriber-manager-lite' ) );
		}
	}

	private function is_action_initiated_from_settings() {
		return get_transient( 'wctlgm_telegram_fetch_channel_id_active' ) === true;
	}

	private function save_channel_id( $chat_id ) {
		set_transient( 'wctlgm_channel_id_temp_store', $chat_id, HOUR_IN_SECONDS );
		delete_transient( 'wctlgm_telegram_fetch_channel_id_active' );
		return array( 'action' => 'none' );
	}

	protected function process_join_request( $data ) {
		$chat_id     = sanitize_text_field( $data['chat_join_request']['chat']['id'] );
		$user_id     = sanitize_text_field( $data['chat_join_request']['from']['id'] );
		$invite_link = esc_url_raw( $data['chat_join_request']['invite_link']['invite_link'] );

		$subscriptions_handler  = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$allow_external_invites = get_option( 'wctlgm_allow_external_invites', false );

		if ( $subscriptions_handler->is_join_request_valid( $user_id, $invite_link ) ) {
			$response_approval = $this->api_handler->approve_join_request( $chat_id, $user_id );
			$response_revoke   = $this->api_handler->revoke_invite_link( $chat_id, $invite_link );
		} elseif ( ! $allow_external_invites ) {
			$response_deny = $this->api_handler->deny_join_request( $chat_id, $user_id );
		}

		return array( 'action' => 'none' );
	}

	private function extract_command_and_args( $message ) {
		$text = '';
		if ( isset( $message['text'] ) ) {
			$text = $message['text'];
		} elseif ( isset( $message['caption'] ) ) {
			$text = $message['caption'];
		}

		$entities = array();
		if ( isset( $message['entities'] ) ) {
			$entities = $message['entities'];
		} elseif ( isset( $message['caption_entities'] ) ) {
			$entities = $message['caption_entities'];
		}

		if ( empty( $entities ) || empty( $text ) ) {
			return array( null, null );
		}

		foreach ( $entities as $entity ) {
			if ( 'bot_command' === $entity['type'] ) {
				$offset  = (int) $entity['offset'];
				$length  = (int) $entity['length'];
				$command = substr( $text, $offset, $length ); // e.g. "/start" or "/start@YourBot"

				// Normalize "/start@YourBot" → "/start"
				$command = strtolower( preg_replace( '/@.+$/', '', $command ) );

				// Arguments are whatever comes after the command
				$args = trim( substr( $text, $offset + $length ) );

				return array( strtolower( $command ), $args );
			}
		}
		return array( null, null );
	}

	protected function handle_start_command( $args = '' ) {
		$args = trim( (string) $args );

		// No payload → normal welcome
		if ( '' === $args ) {
			$message = __( 'Welcome! If you already have an activation code, click your activation link or use /activate <code>.', 'wctlgm-subscriber-manager-lite' );
			return $this->build_response( $message );
		}

		// Accept either plain token ("ztS63PRL") or namespaced ("activate_ztS63PRL" or "activate.ztS63PRL")
		if ( preg_match( '/^(?:activate[._])?([A-Za-z0-9_-]{4,64})$/', $args, $m ) ) {
			$token = $m[1];
			return $this->handle_activation_command( $token );
		}

		// Fallback: invalid payload
		return $this->build_response( __( 'That activation link looks invalid or expired. Please request a new one or use /help.', 'wctlgm-subscriber-manager-lite' ) );
	}

	protected function handle_help_command() {
		$site_name = get_bloginfo( 'name' );
		$message   = sprintf(
			// translators: %s is the site name
			__( 'Welcome! This bot is used to help verify your Telegram User ID and link it to your subscription with %s.', 'wctlgm-subscriber-manager-lite' ),
			$site_name
		);
		$message .= "\n" . __( 'If required, use the /activate <code> command to start the activation process for your subscription.', 'wctlgm-subscriber-manager-lite' );
		$message .= "\n" . __( 'For example, /activate Aq371Do4, and hit enter.', 'wctlgm-subscriber-manager-lite' );
		$message .= "\n" . __( 'This is only required in some cases. If you see an "Activation successful!" message in this chat, you can skip this command.', 'wctlgm-subscriber-manager-lite' );
		return $this->build_response( $message );
	}

	protected function handle_activation_command( $activation_code ) {
		if ( empty( $activation_code ) ) {
			$message = __( 'Please include your activation code. Try the /help command for more information.', 'wctlgm-subscriber-manager-lite' );
			return $this->build_response( $message );
		}

		$code = sanitize_text_field( $activation_code );

		$subscriptions_handler = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
		$results               = $subscriptions_handler->process_activation_code( $code, $this->user_id );

		if ( ! $results ) {
			$message = __( 'Activation failed. Please check your code and try again.', 'wctlgm-subscriber-manager-lite' );
			return $this->build_response( $message );
		}

		$result   = $results[0];
		$order_id = $results[1];

		if ( $result['success'] ) {
			$message = __( 'Activation successful!', 'wctlgm-subscriber-manager-lite' );
			if ( ! empty( $result['channels'] ) ) {
				$message .= "\n" . __( 'Use the following link to access the private channel:', 'wctlgm-subscriber-manager-lite' );
				foreach ( $result['channels'] as $channel ) {
					// translators: %1$s is the channel name, %2$s is the invite link
					$message .= "\n" . sprintf( __( 'Channel: %1$s - %2$s', 'wctlgm-subscriber-manager-lite' ), $channel['name'], $channel['invite_link'] );
				}
			}
			as_schedule_single_action(
				time(),
				'wctglm_send_activation_email',
				array( array( $order_id, $result['channels'] ) ),
			);
		} else {
			$message = __( 'Activation failed. Please check your code and try again.', 'wctlgm-subscriber-manager-lite' );
		}
		return $this->build_response( $message );
	}

	protected function build_response( $message ) {
		return array(
			'action'  => 'sendMessage',
			'chat_id' => $this->chat_id,
			'text'    => $message,
		);
	}

	public static function send_activation_email( $args ) {
		$order_id = $args[0];
		$invites  = $args[1];

		// Get our custom email class instance
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['wctlgm_activation'] ) ) {
			$email = $emails['wctlgm_activation'];
			$email->trigger( $order_id, $invites );
		}
	}
}
