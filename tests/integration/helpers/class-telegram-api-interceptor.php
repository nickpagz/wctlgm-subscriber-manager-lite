<?php
/**
 * Intercepts HTTP calls to the Telegram Bot API during integration tests.
 *
 * Uses WordPress's 'pre_http_request' filter to short-circuit wp_remote_post()
 * calls destined for api.telegram.org. Records all requests for assertion.
 */
class WCTLGM_Lite_Telegram_API_Interceptor {

	/** @var array Recorded requests. */
	private $requests = array();

	/** @var array Custom responses keyed by Telegram API method name. */
	private $custom_responses = array();

	/**
	 * Install the interceptor filter.
	 */
	public function install() {
		$this->requests         = array();
		$this->custom_responses = array();
		add_filter( 'pre_http_request', array( $this, 'intercept' ), 10, 3 );
	}

	/**
	 * Remove the interceptor filter.
	 */
	public function uninstall() {
		remove_filter( 'pre_http_request', array( $this, 'intercept' ), 10 );
	}

	/**
	 * Filter callback for 'pre_http_request'.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $args    Request arguments.
	 * @param string      $url     The request URL.
	 * @return array|false Fake response or false to continue normally.
	 */
	public function intercept( $preempt, $args, $url ) {
		if ( strpos( $url, 'api.telegram.org' ) === false ) {
			return $preempt;
		}

		// Extract the Telegram API method name from the URL.
		$method_name = basename( wp_parse_url( $url, PHP_URL_PATH ) );

		// Record the request.
		$body = isset( $args['body'] ) ? $args['body'] : '';
		$this->requests[] = array(
			'url'         => $url,
			'method_name' => $method_name,
			'body'        => is_string( $body ) ? json_decode( $body, true ) : $body,
			'raw_body'    => $body,
		);

		// Return custom response if configured.
		if ( isset( $this->custom_responses[ $method_name ] ) ) {
			return $this->custom_responses[ $method_name ];
		}

		return $this->get_default_response( $method_name );
	}

	/**
	 * Configure a custom response for a specific Telegram API method.
	 *
	 * @param string    $method_name   Telegram API method (e.g. 'createChatInviteLink').
	 * @param array     $response_body Response body array or string.
	 * @param int       $status_code   HTTP status code.
	 */
	public function set_response( $method_name, $response_body, $status_code = 200 ) {
		$this->custom_responses[ $method_name ] = array(
			'response' => array( 'code' => $status_code ),
			'body'     => is_string( $response_body ) ? $response_body : wp_json_encode( $response_body ),
		);
	}

	/**
	 * Configure a WP_Error response for a method (simulates network failure).
	 *
	 * @param string $method_name  Telegram API method.
	 * @param string $error_code   WP_Error code.
	 * @param string $error_message WP_Error message.
	 */
	public function set_error_response( $method_name, $error_code, $error_message ) {
		$this->custom_responses[ $method_name ] = new WP_Error( $error_code, $error_message );
	}

	/**
	 * Get all recorded requests.
	 *
	 * @return array
	 */
	public function get_requests() {
		return $this->requests;
	}

	/**
	 * Get recorded requests for a specific Telegram API method.
	 *
	 * @param string $method_name Telegram API method.
	 * @return array
	 */
	public function get_requests_for( $method_name ) {
		return array_values(
			array_filter(
				$this->requests,
				function ( $r ) use ( $method_name ) {
					return $r['method_name'] === $method_name;
				}
			)
		);
	}

	/**
	 * Assert that a specific API method was called N times.
	 *
	 * @param WP_UnitTestCase $test_case     The test instance.
	 * @param string          $method_name   Telegram API method.
	 * @param int             $expected_count Expected call count.
	 */
	public function assert_call_count( $test_case, $method_name, $expected_count ) {
		$calls = $this->get_requests_for( $method_name );
		$test_case->assertCount(
			$expected_count,
			$calls,
			sprintf(
				'Expected %d call(s) to Telegram API method "%s", got %d.',
				$expected_count,
				$method_name,
				count( $calls )
			)
		);
	}

	/**
	 * Assert that a specific API method was called at least once.
	 *
	 * @param WP_UnitTestCase $test_case   The test instance.
	 * @param string          $method_name Telegram API method.
	 */
	public function assert_called( $test_case, $method_name ) {
		$calls = $this->get_requests_for( $method_name );
		$test_case->assertNotEmpty(
			$calls,
			sprintf( 'Expected Telegram API method "%s" to be called, but it was not.', $method_name )
		);
	}

	/**
	 * Assert that a specific API method was never called.
	 *
	 * @param WP_UnitTestCase $test_case   The test instance.
	 * @param string          $method_name Telegram API method.
	 */
	public function assert_not_called( $test_case, $method_name ) {
		$calls = $this->get_requests_for( $method_name );
		$test_case->assertEmpty(
			$calls,
			sprintf(
				'Expected Telegram API method "%s" NOT to be called, but it was called %d time(s).',
				$method_name,
				count( $calls )
			)
		);
	}

	/**
	 * Reset recorded requests (useful between sub-steps within a test).
	 */
	public function reset() {
		$this->requests = array();
	}

	/**
	 * Default mock responses for each Telegram API method.
	 *
	 * @param string $method_name Telegram API method.
	 * @return array
	 */
	private function get_default_response( $method_name ) {
		$defaults = array(
			'createChatInviteLink'    => array(
				'ok'     => true,
				'result' => array(
					'invite_link' => 'https://t.me/+test_invite_' . uniqid(),
				),
			),
			'approveChatJoinRequest'  => array( 'ok' => true, 'result' => true ),
			'declineChatJoinRequest'  => array( 'ok' => true, 'result' => true ),
			'revokeChatInviteLink'    => array( 'ok' => true, 'result' => true ),
			'setWebhook'              => array( 'ok' => true, 'result' => true ),
			'setMyCommands'           => array( 'ok' => true, 'result' => true ),
			'sendMessage'             => array(
				'ok'     => true,
				'result' => array( 'message_id' => 1 ),
			),
		);

		$body = isset( $defaults[ $method_name ] )
			? $defaults[ $method_name ]
			: array( 'ok' => true );

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $body ),
		);
	}
}
