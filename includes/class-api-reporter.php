<?php
/**
 * API Reporter for WP Speakeasy
 *
 * Reports plugin status, health checks, and errors to the Speakeasy API.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

/**
 * Class Speakeasy_API_Reporter
 *
 * Handles communication with the Speakeasy backend API for:
 * - Activation reports
 * - Update reports
 * - Daily health checks
 * - Error reporting
 *
 * Configuration via wp-config.php:
 * - SPEAKEASY_API_ENDPOINT: API base URL (e.g., 'https://api.speakeasy.com/wp-plugin')
 * - SPEAKEASY_API_TOKEN: API authentication token
 *
 * All API calls are non-blocking and fail silently to prevent breaking the site.
 *
 * @since 1.0.0
 */
class Speakeasy_API_Reporter {

	/**
	 * API endpoint base URL
	 *
	 * @var string|null
	 */
	private $api_endpoint = null;

	/**
	 * API authentication token
	 *
	 * @var string|null
	 */
	private $api_token = null;

	/**
	 * Constructor
	 *
	 * Initializes the API reporter and schedules health checks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Load configuration.
		$this->api_endpoint = defined( 'SPEAKEASY_API_ENDPOINT' ) ? SPEAKEASY_API_ENDPOINT : null;
		$this->api_token    = defined( 'SPEAKEASY_API_TOKEN' ) ? SPEAKEASY_API_TOKEN : null;

		// Only initialize if API is configured.
		if ( ! $this->api_endpoint || ! $this->api_token ) {
			error_log( 'WP Speakeasy: API endpoint or token not configured. Reporting disabled.' );
			return;
		}

		$this->init_health_check();
	}

	/**
	 * Initialize daily health check
	 *
	 * Schedules a daily cron event to send health status to the API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_health_check(): void {
		// Schedule daily health check if not already scheduled.
		if ( ! wp_next_scheduled( 'speakeasy_daily_health_check' ) ) {
			wp_schedule_event( time(), 'daily', 'speakeasy_daily_health_check' );
		}

		// Hook the health check callback.
		add_action( 'speakeasy_daily_health_check', array( $this, 'send_health_check' ) );
	}

	/**
	 * Send health check to API
	 *
	 * Reports plugin version, active modules, and system information.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function send_health_check(): void {
		if ( ! $this->api_endpoint || ! $this->api_token ) {
			return;
		}

		$module_status = $this->get_module_status();

		$this->send_request(
			'/health',
			array(
				'site'              => home_url(),
				'plugin_version'    => SPEAKEASY_VERSION,
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
				'active_modules'    => array_keys( array_filter( $module_status, function ( $m ) {
					return $m['enabled'];
				} ) ),
				'module_status'     => $module_status,
				'timestamp'         => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Send error report to API
	 *
	 * Reports errors and exceptions to the API for monitoring.
	 *
	 * @since 1.0.0
	 * @param string $error_type    Error type (e.g., 'module_error', 'update_failed').
	 * @param string $error_message Error message.
	 * @param string $stack_trace   Optional stack trace.
	 * @return void
	 */
	public function send_error_report( string $error_type, string $error_message, string $stack_trace = '' ): void {
		if ( ! $this->api_endpoint || ! $this->api_token ) {
			return;
		}

		$this->send_request(
			'/error',
			array(
				'site'           => home_url(),
				'plugin_version' => SPEAKEASY_VERSION,
				'error_type'     => $error_type,
				'error_message'  => $error_message,
				'stack_trace'    => $stack_trace,
				'timestamp'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get module status
	 *
	 * Returns status information for all registered modules.
	 *
	 * @since 1.0.0
	 * @return array<string, array> Module status data.
	 */
	private function get_module_status(): array {
		$manager = Speakeasy_Module_Manager::instance();
		$modules = $manager->get_all_modules();
		$status  = array();

		foreach ( $modules as $id => $module ) {
			$status[ $id ] = array(
				'enabled' => $module->is_enabled(),
				'version' => $module->get_version(),
				'name'    => $module->get_name(),
			);

			// Add module-specific data.
			if ( 'lap-meta' === $id && method_exists( $module, 'get_field_schemas' ) ) {
				$status[ $id ]['fields'] = count( $module->get_field_schemas() );
			}
		}

		return $status;
	}

	/**
	 * Send API request
	 *
	 * Helper method to send requests to the Speakeasy API.
	 * All requests are non-blocking and fail silently.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint path (e.g., '/health').
	 * @param array  $data     Request body data.
	 * @return void
	 */
	private function send_request( string $endpoint, array $data ): void {
		$url = rtrim( $this->api_endpoint, '/' ) . $endpoint;

		$response = wp_remote_post(
			$url,
			array(
				'body'     => wp_json_encode( $data ),
				'headers'  => array(
					'Authorization' => 'Bearer ' . $this->api_token,
					'Content-Type'  => 'application/json',
				),
				'timeout'  => 5,
				'blocking' => false, // Non-blocking: don't wait for response.
			)
		);

		// Log errors only (don't break site functionality).
		if ( is_wp_error( $response ) ) {
			error_log( 'WP Speakeasy: API request failed: ' . $response->get_error_message() );
		}
	}
}
