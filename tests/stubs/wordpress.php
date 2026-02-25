<?php
/**
 * Minimal WordPress class stubs for unit testing.
 */

class WP_Error {
	public $errors = array();
	protected $error_data = array();
	protected $error_messages = array();

	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( ! empty( $code ) ) {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}
	}

	public function get_error_code() {
		$codes = array_keys( $this->errors );
		return ! empty( $codes ) ? $codes[0] : '';
	}

	public function get_error_message( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}
		if ( isset( $this->errors[ $code ] ) ) {
			return $this->errors[ $code ][0];
		}
		return '';
	}

	public function get_error_messages( $code = '' ) {
		if ( empty( $code ) ) {
			$all = array();
			foreach ( $this->errors as $messages ) {
				$all = array_merge( $all, $messages );
			}
			return $all;
		}
		return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : array();
	}
}

class WP_REST_Request {
	protected $headers = array();
	protected $params = array();
	protected $json_params = array();

	public function set_header( $key, $value ) {
		$this->headers[ strtolower( $key ) ] = $value;
	}

	public function get_header( $key ) {
		$key = strtolower( $key );
		return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : null;
	}

	public function set_json_params( $params ) {
		$this->json_params = $params;
	}

	public function get_json_params() {
		return $this->json_params;
	}

	public function set_param( $key, $value ) {
		$this->params[ $key ] = $value;
	}

	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}
}

class WP_REST_Response {
	public $data;
	public $status;

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status() {
		return $this->status;
	}
}
