<?php

namespace Subscriber_Manager_Lite_for_WooCommerce_and_Telegram;

/**
 * Class Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler
 *
 * The class that sets up the required API endpoint for handling Telegram bot interaction requests.
 *
 * @package Subscriber_Manager_Lite_for_WooCommerce_and_Telegram
 */
class Subscriber_Manager_Lite_WCTLGM_Bot_Interaction_Handler {

	private $api_handler;
	private $text;
	private $chat_id;
	private $user_id;

	public function __construct() {
		$this->api_handler = new \Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_API_Handler();
	}

	public static function init() {
		add_action( 'wctglm_send_activation_email', array( __CLASS__, 'send_activation_email' ) );
	}

	public function process_telegram_request( $data ) {
		if ( isset( $data['chat_join_request'] ) ) {
			return $this->process_join_request( $data );
		}

		if ( isset( $data['edited_channel_post'] ) ) {
			$chat_id = $data['edited_channel_post']['chat']['id'];

			if ( $this->is_action_initiated_from_settings() ) {
				// Optionally check if the chat ID matches expected channels
				return $this->save_channel_id( $chat_id );
			}
			return array( 'action' => 'none' );
		}

		if ( ! isset( $data['message'] ) ) {
			return array( 'action' => 'none' );
		}

		$this->text    = $data['message']['text'];
		$this->chat_id = $data['message']['chat']['id'];
		$this->user_id = $data['message']['from']['id'];

		if ( strpos( $this->text, '/start' ) === 0 ) {
			return $this->handle_start_command();
		} elseif ( strpos( $this->text, '/activate' ) === 0 ) {
			return $this->handle_activation_command();
		} elseif ( strpos( $this->text, '/help' ) === 0 ) {
			return $this->handle_help_command();
		} else {
			return $this->build_response( 'Invalid. Please use the /help command for more information.' );
		}
	}

	private function is_action_initiated_from_settings() {
		return get_transient( 'telegram_fetch_channel_id_active' ) === true;
	}

	private function save_channel_id( $chat_id ) {
		set_transient( 'channel_id_temp_store', $chat_id, HOUR_IN_SECONDS );
		delete_transient( 'telegram_fetch_channel_id_active' );
		return array( 'action' => 'none' );
	}

	protected function process_join_request( $data ) {
		$chat_id     = $data['chat_join_request']['chat']['id'];
		$user_id     = $data['chat_join_request']['from']['id'];
		$invite_link = $data['chat_join_request']['invite_link']['invite_link'];

		$subscriptions_handler = new \Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();

		if ( $subscriptions_handler->is_join_request_valid( $user_id, $invite_link ) ) {
			$response_approval = $this->api_handler->approve_join_request( $chat_id, $user_id );
			$response_revoke   = $this->api_handler->revoke_invite_link( $chat_id, $invite_link );
		} else {
			$response_deny = $this->api_handler->deny_join_request( $chat_id, $user_id );
		}

		return array( 'action' => 'none' );
	}

	protected function handle_start_command() {
		$message = __( 'Welcome! Please use the /activate <code> command to start the activation process for your subscription.', 'wctlgm-subscriber-manager-lite' );
		return $this->build_response( $message );
	}

	protected function handle_help_command() {
		$site_name = get_bloginfo( 'name' );
		$message   = sprintf(
			// translators: %s is the site name
			__( 'Welcome! This bot is used to help verify your Telegram User ID and link it to your subscription with %s.', 'wctlgm-subscriber-manager-lite' ),
			$site_name
		);
		$message .= "\n" . __( 'Please use the /activate <code> command to start the activation process for your subscription.', 'wctlgm-subscriber-manager-lite' );
		$message .= "\n" . __( 'For example, /activate Aq371Do4, and hit enter.', 'wctlgm-subscriber-manager-lite' );
		return $this->build_response( $message );
	}

	protected function handle_activation_command() {
		$parts = explode( ' ', $this->text );
		if ( count( $parts ) < 2 ) {
			$message = __( 'Please include your activation code. Try the /help command for more information.', 'wctlgm-subscriber-manager-lite' );
			return $this->build_response( $message );
		}

		$code = $parts[1];

		$subscriptions_handler = new \Subscriber_Manager_Lite_for_WooCommerce_and_Telegram\Subscriber_Manager_Lite_WCTLGM_Subscriptions_Handler();
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
				$message .= "\n" . __( 'Use the following links to access the private channels:', 'wctlgm-subscriber-manager-lite' );
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

		$order      = wc_get_order( $order_id );
		$user_email = $order->get_billing_email();

		ob_start();
		include WCTLGM_SM_PLUGIN_DIR . 'assets/email-templates/email-invites.php';
		$message_body = ob_get_clean();

		wp_mail( $user_email, 'Your Subscription Activation Details', $message_body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}
