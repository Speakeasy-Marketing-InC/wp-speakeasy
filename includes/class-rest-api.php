<?php
/**
 * REST API Handler for WP Speakeasy
 *
 * Provides REST API endpoints for remote management of Application Passwords
 * and other plugin features.
 *
 * @package WP_Speakeasy
 * @since 1.1.0
 */

/**
 * Class Speakeasy_REST_API
 *
 * Handles REST API endpoints for the WP Speakeasy plugin.
 * Provides secure, API-key authenticated endpoints for:
 * - Creating Application Passwords programmatically
 * - Triggering plugin updates remotely
 * - Checking for available updates
 *
 * Authentication:
 * - Uses plugin API key (stored in speakeasy_api_key option)
 * - API key passed via X-Speakeasy-API-Key header
 * - Uses hash_equals() for timing attack protection
 *
 * @since 1.1.0
 */
class Speakeasy_REST_API {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'speakeasy/v1';

	/**
	 * Plugin API key
	 *
	 * @var string|null
	 */
	private $api_key = null;

	/**
	 * Constructor
	 *
	 * Loads API key and registers REST routes.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->api_key = get_option( 'speakeasy_api_key' );

		// Register routes on rest_api_init.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/application-passwords',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_application_password' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
				'args'                => array(
					'username' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_user',
						'validate_callback' => function ( $param ) {
							return ! empty( $param ) && strlen( $param ) <= 60;
						},
					),
					'name'     => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return strlen( $param ) <= 255;
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'trigger_update' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/update/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_for_update' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
			)
		);
	}

	/**
	 * Verify API key
	 *
	 * Permission callback that validates the API key from request header.
	 * Uses hash_equals() for timing attack protection.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function verify_api_key( $request ) {
		$provided_key = $request->get_header( 'X-Speakeasy-API-Key' );

		// Check if API key header is present.
		if ( empty( $provided_key ) ) {
			$this->log_error( 'warning', 'API request missing API key header', $request );

			return new WP_Error(
				'missing_api_key',
				'API key is required',
				array( 'status' => 401 )
			);
		}

		// Check if API key is configured.
		if ( empty( $this->api_key ) ) {
			$this->log_error( 'error', 'API key not configured in WordPress', $request );

			return new WP_Error(
				'api_key_not_configured',
				'API key not configured on this site',
				array( 'status' => 500 )
			);
		}

		// Verify API key using timing-safe comparison.
		if ( ! hash_equals( $this->api_key, $provided_key ) ) {
			$this->log_error( 'warning', 'API request with invalid API key', $request );

			return new WP_Error(
				'invalid_api_key',
				'Invalid API key',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Create Application Password
	 *
	 * REST API endpoint handler for creating Application Passwords.
	 * Revokes any existing password with the same name before creating new one.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_application_password( $request ) {
		$username = $request->get_param( 'username' );
		$name     = $request->get_param( 'name' );

		// Validate username is provided (should be caught by args validation).
		if ( empty( $username ) ) {
			return new WP_Error(
				'missing_username',
				'Username is required',
				array( 'status' => 400 )
			);
		}

		// Get user by username.
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$this->log_error( 'warning', "User not found: {$username}", $request );

			return new WP_Error(
				'user_not_found',
				'User not found',
				array( 'status' => 404 )
			);
		}

		// Check if Application Passwords are available globally.
		if ( ! wp_is_application_passwords_available() ) {
			$this->log_error( 'error', 'Application Passwords not available globally', $request );

			return new WP_Error(
				'app_passwords_unavailable',
				'Application Passwords are not available on this site',
				array( 'status' => 503 )
			);
		}

		// Check if Application Passwords are available for this user.
		if ( ! wp_is_application_passwords_available_for_user( $user ) ) {
			$this->log_error( 'warning', "Application Passwords disabled for user: {$username}", $request );

			return new WP_Error(
				'app_passwords_disabled',
				'Application Passwords are not available for this user',
				array( 'status' => 403 )
			);
		}

		// Generate default name if not provided.
		if ( empty( $name ) ) {
			$name = 'Speakeasy Automation - ' . current_time( 'Y-m-d H:i:s' );
		}

		// Revoke existing password with same name.
		$this->revoke_password_by_name( $user->ID, $name );

		// Create new Application Password.
		$created = WP_Application_Passwords::create_new_application_password( $user->ID, array( 'name' => $name ) );

		if ( is_wp_error( $created ) ) {
			$this->log_error( 'error', 'Failed to create Application Password: ' . $created->get_error_message(), $request );

			return new WP_Error(
				'creation_failed',
				'Failed to create Application Password',
				array( 'status' => 500 )
			);
		}

		// Log successful creation.
		$this->log_success( $user, $name, $request );

		// Return success response with password.
		return rest_ensure_response(
			array(
				'success'  => true,
				'password' => WP_Application_Passwords::chunk_password( $created[0] ),
				'username' => $user->user_login,
				'user_id'  => $user->ID,
				'name'     => $name,
			)
		);
	}

	/**
	 * Revoke password by name
	 *
	 * Revokes any existing Application Password with the specified name.
	 *
	 * @since 1.1.0
	 * @param int    $user_id User ID.
	 * @param string $name    Application Password name.
	 * @return void
	 */
	private function revoke_password_by_name( int $user_id, string $name ): void {
		$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );

		foreach ( $passwords as $password ) {
			if ( $password['name'] === $name ) {
				WP_Application_Passwords::delete_application_password( $user_id, $password['uuid'] );
				error_log( "WP Speakeasy: Revoked existing Application Password '{$name}' for user ID {$user_id}" );
			}
		}
	}

	/**
	 * Log error
	 *
	 * Logs errors using error_log() and Speakeasy_Error_Logger if available.
	 *
	 * @since 1.1.0
	 * @param string          $severity Error severity (error, warning, notice).
	 * @param string          $message  Error message.
	 * @param WP_REST_Request $request  Request object.
	 * @return void
	 */
	private function log_error( string $severity, string $message, $request ): void {
		error_log( "WP Speakeasy REST API: {$message}" );

		// Log to Error Logger if available.
		if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
			Speakeasy_Error_Logger::instance()->log_error(
				$severity,
				$message,
				__FILE__,
				__LINE__,
				array(
					'component' => 'REST API',
					'endpoint'  => $request->get_route(),
					'method'    => $request->get_method(),
					'ip'        => $this->get_client_ip(),
				)
			);
		}
	}

	/**
	 * Log success
	 *
	 * Logs successful Application Password creation for audit purposes.
	 *
	 * @since 1.1.0
	 * @param WP_User         $user    User object.
	 * @param string          $name    Application Password name.
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	private function log_success( $user, string $name, $request ): void {
		$message = "Application Password created for user '{$user->user_login}' (ID: {$user->ID}) with name '{$name}'";
		error_log( "WP Speakeasy: {$message}" );

		// Log to Error Logger if available (as 'notice' severity for audit trail).
		if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
			Speakeasy_Error_Logger::instance()->log_error(
				'notice',
				$message,
				__FILE__,
				__LINE__,
				array(
					'component' => 'REST API',
					'endpoint'  => $request->get_route(),
					'method'    => $request->get_method(),
					'ip'        => $this->get_client_ip(),
					'username'  => $user->user_login,
					'user_id'   => $user->ID,
					'app_name'  => $name,
				)
			);
		}
	}

	/**
	 * Trigger plugin update
	 *
	 * REST API endpoint handler for triggering plugin updates.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function trigger_update( $request ) {
		if ( ! class_exists( 'Speakeasy_Simple_Updater' ) ) {
			return new WP_Error(
				'updater_unavailable',
				'Simple Updater not available',
				array( 'status' => 503 )
			);
		}

		$updater = new Speakeasy_Simple_Updater();
		$result  = $updater->update();

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'error', 'Update failed: ' . $result->get_error_message(), $request );

			return new WP_Error(
				'update_failed',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		error_log( 'WP Speakeasy: Update triggered via API - ' . $result['message'] );

		return rest_ensure_response(
			array(
				'success'      => $result['success'],
				'message'      => $result['message'],
				'version'      => $result['version'],
				'method'       => isset( $result['method'] ) ? $result['method'] : null,
				'download_url' => isset( $result['download_url'] ) ? $result['download_url'] : null,
			)
		);
	}

	/**
	 * Check for plugin updates
	 *
	 * REST API endpoint handler for checking if updates are available.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function check_for_update( $request ) {
		if ( ! class_exists( 'Speakeasy_Simple_Updater' ) ) {
			return new WP_Error(
				'updater_unavailable',
				'Simple Updater not available',
				array( 'status' => 503 )
			);
		}

		$updater = new Speakeasy_Simple_Updater();
		$info    = $updater->check_for_updates();

		if ( is_wp_error( $info ) ) {
			$this->log_error( 'error', 'Update check failed: ' . $info->get_error_message(), $request );

			return new WP_Error(
				'check_failed',
				$info->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$update_available = version_compare( $info['version'], SPEAKEASY_VERSION, '>' );

		return rest_ensure_response(
			array(
				'current_version'  => SPEAKEASY_VERSION,
				'latest_version'   => $info['version'],
				'update_available' => $update_available,
				'download_url'     => $info['download_url'],
				'changelog'        => $info['changelog'],
				'published_at'     => $info['published_at'],
			)
		);
	}

	/**
	 * Get client IP address
	 *
	 * Returns the client's IP address for logging purposes.
	 * Handles proxied requests.
	 *
	 * @since 1.1.0
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ip = explode( ',', $ip )[0]; // First IP in list.
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
