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
 * - Update reports
 * - Daily health checks
 * - Error reporting
 *
 * Note: Activation reports are handled separately in wp-speakeasy.php with retry logic.
 *
 * Configuration:
 * - SPEAKEASY_API_ENDPOINT: API base URL (defaults to https://api.speakeasy.com/wp-plugin)
 * - API key: Auto-generated and stored in speakeasy_api_key option
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
		$this->api_endpoint = SPEAKEASY_API_ENDPOINT;
		$this->api_token    = get_option( 'speakeasy_api_key' );

		// Only initialize if API key exists.
		if ( ! $this->api_token ) {
			error_log( 'WP Speakeasy: API key not generated yet. Reporting disabled.' );

			// Log to Error Logger if available.
			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_error(
					'warning',
					'API key not generated yet. Reporting disabled.',
					__FILE__,
					__LINE__,
					array( 'component' => 'API Reporter' )
				);
			}
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
	 * Matches Speakeasy backend /wordpress-sites/heartbeat endpoint.
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
			'/heartbeat',
			array(
				'siteUrl'          => home_url(),
				'pluginVersion'    => SPEAKEASY_VERSION,
				'wordpressVersion' => get_bloginfo( 'version' ),
				'phpVersion'       => PHP_VERSION,
				'activeModules'    => array_keys(
					array_filter(
						$module_status,
						function ( $m ) {
							return $m['enabled'];
						}
					)
				),
				'moduleStatus'     => $module_status,
			)
		);
	}

	/**
	 * Send error report to API
	 *
	 * Reports errors and exceptions to the API for monitoring.
	 * Matches Speakeasy backend /wordpress-sites/error endpoint.
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
				'siteUrl'       => home_url(),
				'pluginVersion' => SPEAKEASY_VERSION,
				'errorType'     => $error_type,
				'errorMessage'  => $error_message,
				'stackTrace'    => $stack_trace,
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
	 * No authentication required - endpoints are publicly accessible.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint path (e.g., '/heartbeat').
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
					'Content-Type' => 'application/json',
				),
				'timeout'  => 5,
				'blocking' => false, // Non-blocking: don't wait for response.
			)
		);

		// Log errors only (don't break site functionality).
		if ( is_wp_error( $response ) ) {
			error_log( 'WP Speakeasy: API request failed: ' . $response->get_error_message() );

			// Log to Error Logger if available.
			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_wp_error(
					$response,
					array(
						'component' => 'API Reporter',
						'endpoint'  => $endpoint,
						'url'       => $url,
					)
				);
			}
		}
	}
}
