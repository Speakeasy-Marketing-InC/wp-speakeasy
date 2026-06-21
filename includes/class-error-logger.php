<?php
/**
 * Error Logger for WP Speakeasy
 *
 * Captures and stores plugin-specific errors for dashboard display.
 *
 * @package WP_Speakeasy
 * @since 1.1.0
 */

/**
 * Class Speakeasy_Error_Logger
 *
 * Singleton class that captures errors, warnings, and exceptions from the
 * WP Speakeasy plugin and stores them for display in the admin dashboard.
 *
 * Features:
 * - Stores up to 50 most recent errors in wp_options
 * - Sanitizes sensitive data (API keys, tokens, passwords)
 * - Strips absolute file paths for security
 * - Never throws exceptions (fails silently)
 * - Scoped to plugin directory only
 *
 * @since 1.1.0
 */
class Speakeasy_Error_Logger {

	/**
	 * Singleton instance
	 *
	 * @var Speakeasy_Error_Logger|null
	 */
	private static $instance = null;

	/**
	 * Maximum number of errors to store
	 *
	 * @var int
	 */
	const MAX_ERRORS = 50;

	/**
	 * Maximum message length
	 *
	 * @var int
	 */
	const MAX_MESSAGE_LENGTH = 500;

	/**
	 * WordPress option name for storing errors
	 *
	 * @var string
	 */
	const OPTION_NAME = 'speakeasy_error_log';

	/**
	 * Get singleton instance
	 *
	 * @since 1.1.0
	 * @return Speakeasy_Error_Logger Singleton instance.
	 */
	public static function instance(): Speakeasy_Error_Logger {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor.
	}

	/**
	 * Log an error
	 *
	 * Stores error information in WordPress options for dashboard display.
	 * Never throws exceptions - fails silently if storage fails.
	 *
	 * @since 1.1.0
	 * @param string $type    Error type: 'error', 'warning', 'notice', 'exception'.
	 * @param string $message Error message.
	 * @param string $file    File where error occurred.
	 * @param int    $line    Line number where error occurred.
	 * @param array  $context Optional context data (default: empty array).
	 * @param string $trace   Optional stack trace (default: empty string).
	 * @return void
	 */
	public function log_error( string $type, string $message, string $file = '', int $line = 0, array $context = array(), string $trace = '' ): void {
		// Return early if WordPress is not loaded.
		if ( ! defined( 'ABSPATH' ) ) {
			return;
		}

		// Validate and normalize error type.
		$valid_types = array( 'error', 'warning', 'notice', 'exception' );
		if ( ! in_array( $type, $valid_types, true ) ) {
			$type = 'error';
		}

		// Sanitize message.
		$message = $this->sanitize_message( $message );

		// Truncate message if too long.
		if ( strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			$message = substr( $message, 0, self::MAX_MESSAGE_LENGTH ) . '...';
		}

		// Strip ABSPATH from file path.
		$file = $this->sanitize_file_path( $file );

		// Build error entry.
		$error = array(
			'type'      => $type,
			'message'   => $message,
			'file'      => $file,
			'line'      => $line,
			'timestamp' => current_time( 'mysql' ),
			'context'   => $context,
			'trace'     => $trace,
		);

		// Get existing errors.
		$errors = $this->get_errors();

		// Add new error at the beginning (most recent first).
		array_unshift( $errors, $error );

		// Prune to max limit.
		if ( count( $errors ) > self::MAX_ERRORS ) {
			$errors = array_slice( $errors, 0, self::MAX_ERRORS );
		}

		// Store errors.
		try {
			$result = update_option( self::OPTION_NAME, $errors, false );

			// Fallback to error_log if update_option fails.
			if ( ! $result && count( $errors ) === 1 ) {
				// Only log to error_log if this was the first error (not an update).
				error_log( "WP Speakeasy Error Logger: Failed to store error - $type: $message in $file:$line" );
			}
		} catch ( Exception $e ) {
			// Fail silently - never break the site.
			error_log( 'WP Speakeasy Error Logger: Exception during error storage - ' . $e->getMessage() );
		}
	}

	/**
	 * Get all logged errors
	 *
	 * Retrieves errors from WordPress options.
	 *
	 * @since 1.1.0
	 * @return array Array of error entries (most recent first).
	 */
	public function get_errors(): array {
		$errors = get_option( self::OPTION_NAME, array() );

		// Ensure we always return an array.
		if ( ! is_array( $errors ) ) {
			return array();
		}

		return $errors;
	}

	/**
	 * Get error count
	 *
	 * Returns the number of logged errors.
	 *
	 * @since 1.1.0
	 * @return int Number of errors.
	 */
	public function get_error_count(): int {
		return count( $this->get_errors() );
	}

	/**
	 * Clear all errors
	 *
	 * Deletes all stored errors from WordPress options.
	 *
	 * @since 1.1.0
	 * @return bool True on success, false on failure.
	 */
	public function clear_errors(): bool {
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Sanitize error message
	 *
	 * Removes sensitive data like API keys, tokens, and passwords.
	 *
	 * @since 1.1.0
	 * @param string $message Original error message.
	 * @return string Sanitized message.
	 */
	private function sanitize_message( string $message ): string {
		// Strip HTML tags.
		$message = wp_strip_all_tags( $message );

		// Patterns for sensitive data.
		$patterns = array(
			'/api[_-]?key["\']?\s*[:=]\s*["\']?[\w\-]+/i',      // API keys.
			'/token["\']?\s*[:=]\s*["\']?[\w\-]+/i',            // Tokens.
			'/password["\']?\s*[:=]\s*["\']?[\w\-]+/i',         // Passwords.
			'/sk_live_[\w]+/i',                                  // Stripe live keys.
			'/sk_test_[\w]+/i',                                  // Stripe test keys.
			'/ghp_[\w]+/i',                                      // GitHub tokens.
			'/[\w\-\.]+@[\w\-\.]+\.\w+/',                       // Email addresses.
			'/\d{13,19}/',                                       // Credit card numbers.
		);

		// Replace sensitive data with [REDACTED].
		foreach ( $patterns as $pattern ) {
			$message = preg_replace( $pattern, '[REDACTED]', $message );
		}

		return $message;
	}

	/**
	 * Sanitize file path
	 *
	 * Strips ABSPATH from file paths for security.
	 *
	 * @since 1.1.0
	 * @param string $file Original file path.
	 * @return string Sanitized file path (relative).
	 */
	private function sanitize_file_path( string $file ): string {
		if ( defined( 'ABSPATH' ) && ! empty( $file ) ) {
			$file = str_replace( ABSPATH, '', $file );
		}

		return $file;
	}

	/**
	 * Log exception
	 *
	 * Helper method to log exceptions with stack trace.
	 *
	 * @since 1.1.0
	 * @param Exception|Throwable $exception Exception to log.
	 * @param array               $context   Optional context data.
	 * @return void
	 */
	public function log_exception( $exception, array $context = array() ): void {
		$this->log_error(
			'exception',
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$context,
			$exception->getTraceAsString()
		);
	}

	/**
	 * Log WP_Error
	 *
	 * Helper method to log WP_Error instances.
	 *
	 * @since 1.1.0
	 * @param WP_Error $error   WP_Error instance.
	 * @param array    $context Optional context data.
	 * @return void
	 */
	public function log_wp_error( WP_Error $error, array $context = array() ): void {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
		$caller    = isset( $backtrace[1] ) ? $backtrace[1] : array();

		$file = isset( $caller['file'] ) ? $caller['file'] : '';
		$line = isset( $caller['line'] ) ? $caller['line'] : 0;

		$this->log_error(
			'error',
			$error->get_error_message(),
			$file,
			$line,
			array_merge(
				$context,
				array(
					'error_code' => $error->get_error_code(),
					'error_data' => $error->get_error_data(),
				)
			)
		);
	}
}
