<?php
/**
 * WordPress function mocks for testing environments without WordPress test suite
 *
 * @package WP_Speakeasy
 */

// Mock WordPress functions needed for testing.
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// Mock implementation.
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// Mock implementation.
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $hook, $callback, $priority = 10 ) {
		// Mock implementation.
		return true;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $hook, $priority = false ) {
		// Mock implementation.
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		// Mock implementation.
		return null;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	function __return_true() {
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * WordPress Error class mock
	 */
	class WP_Error {
		/**
		 * Error code
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Error data
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Constructor
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Get error code
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Get error message
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * Get error data
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}
