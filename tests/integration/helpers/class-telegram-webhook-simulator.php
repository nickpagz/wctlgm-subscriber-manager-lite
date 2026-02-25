<?php
/**
 * Simulates incoming Telegram webhook requests to the plugin's REST endpoint.
 *
 * Constructs WP_REST_Request objects and dispatches them through
 * the endpoint handler, testing the full chain: endpoint validation →
 * bot interaction handler → subscription handler.
 */
class WCTLGM_Lite_Telegram_Webhook_Simulator {

	/**
	 * Simulate a /start command from a Telegram user.
	 *
	 * @param string $user_id Telegram user ID.
	 * @param string $chat_id Chat ID (defaults to user_id for private chats).
	 * @param string $payload Optional deep-link payload.
	 * @return WP_REST_Response
	 */
	public function send_start_command( $user_id, $chat_id = null, $payload = '' ) {
		$chat_id = $chat_id ?: $user_id;
		$text    = '/start' . ( $payload ? ' ' . $payload : '' );
		return $this->send_message( $text, $user_id, $chat_id );
	}

	/**
	 * Simulate an /activate command from a Telegram user.
	 *
	 * @param string $user_id         Telegram user ID.
	 * @param string $activation_code The activation code.
	 * @param string $chat_id         Chat ID (defaults to user_id).
	 * @return WP_REST_Response
	 */
	public function send_activate_command( $user_id, $activation_code, $chat_id = null ) {
		$chat_id = $chat_id ?: $user_id;
		return $this->send_message( '/activate ' . $activation_code, $user_id, $chat_id );
	}

	/**
	 * Simulate a /help command from a Telegram user.
	 *
	 * @param string $user_id Telegram user ID.
	 * @param string $chat_id Chat ID (defaults to user_id).
	 * @return WP_REST_Response
	 */
	public function send_help_command( $user_id, $chat_id = null ) {
		$chat_id = $chat_id ?: $user_id;
		return $this->send_message( '/help', $user_id, $chat_id );
	}

	/**
	 * Simulate a chat_join_request event.
	 *
	 * @param string $user_id     Telegram user ID.
	 * @param string $chat_id     Channel/group ID.
	 * @param string $invite_link The invite link used.
	 * @return WP_REST_Response
	 */
	public function send_join_request( $user_id, $chat_id, $invite_link ) {
		$data = array(
			'chat_join_request' => array(
				'chat'        => array(
					'id'   => $chat_id,
					'type' => 'supergroup',
				),
				'from'        => array(
					'id'         => $user_id,
					'first_name' => 'TestUser',
				),
				'invite_link' => array(
					'invite_link' => $invite_link,
				),
			),
		);

		return $this->dispatch_request( $data );
	}

	/**
	 * Simulate an edited_channel_post event (for channel ID capture).
	 *
	 * @param string $chat_id Channel ID.
	 * @return WP_REST_Response
	 */
	public function send_edited_channel_post( $chat_id ) {
		$data = array(
			'edited_channel_post' => array(
				'chat' => array(
					'id'    => $chat_id,
					'type'  => 'channel',
					'title' => 'Test Channel',
				),
				'text' => 'edited message',
			),
		);

		return $this->dispatch_request( $data );
	}

	/**
	 * Send a private message to the bot.
	 *
	 * @param string $text    Message text.
	 * @param string $user_id Telegram user ID.
	 * @param string $chat_id Chat ID.
	 * @return WP_REST_Response
	 */
	private function send_message( $text, $user_id, $chat_id ) {
		$data = array(
			'message' => array(
				'chat' => array(
					'id'   => $chat_id,
					'type' => 'private',
				),
				'from' => array(
					'id'         => $user_id,
					'first_name' => 'TestUser',
				),
				'text' => $text,
			),
		);

		// Add bot_command entity if text starts with '/'.
		if ( strpos( $text, '/' ) === 0 ) {
			$parts  = explode( ' ', $text, 2 );
			$length = strlen( $parts[0] );

			$data['message']['entities'] = array(
				array(
					'type'   => 'bot_command',
					'offset' => 0,
					'length' => $length,
				),
			);
		}

		return $this->dispatch_request( $data );
	}

	/**
	 * Dispatch the request through the plugin's endpoint handler.
	 *
	 * Creates a real WP_REST_Request, sets the secret token header,
	 * and calls the endpoint handler directly.
	 *
	 * @param array $data Telegram webhook payload.
	 * @return WP_REST_Response
	 */
	private function dispatch_request( $data ) {
		$request = new WP_REST_Request( 'POST', '/wctlgm/v1/telegram-bot/' );
		$request->set_header( 'X-Telegram-Bot-Api-Secret-Token', get_option( 'wctlgm_secret_token' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );

		$endpoint_handler = new \Subscriber_Manager_Lite_for_Telegram\Subscriber_Manager_Lite_WCTLGM_Endpoint_Handler();

		$permission = $endpoint_handler->check_telegram_token_permission( $request );
		if ( ! $permission ) {
			return new WP_REST_Response( 'Unauthorized', 401 );
		}

		return $endpoint_handler->handle_telegram_requests( $request );
	}
}
