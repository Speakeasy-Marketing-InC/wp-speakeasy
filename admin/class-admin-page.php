<?php
/**
 * Admin Page for WP Speakeasy
 *
 * Provides settings and diagnostics interface.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

/**
 * Class Speakeasy_Admin_Page
 *
 * Manages the admin interface as a top-level menu item.
 * Displays module status, diagnostics, and system information.
 *
 * @since 1.0.0
 */
class Speakeasy_Admin_Page {

	/**
	 * Constructor
	 *
	 * Registers admin menu and settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_speakeasy_check_update', array( $this, 'ajax_check_update' ) );
		add_action( 'wp_ajax_speakeasy_trigger_update', array( $this, 'ajax_trigger_update' ) );
		add_action( 'wp_ajax_speakeasy_send_activation', array( $this, 'ajax_send_activation' ) );
		add_action( 'wp_ajax_speakeasy_get_errors', array( $this, 'ajax_get_errors' ) );
		add_action( 'wp_ajax_speakeasy_clear_errors', array( $this, 'ajax_clear_errors' ) );
	}

	/**
	 * Add admin menu
	 *
	 * Adds top-level menu item in WordPress admin sidebar.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			'WP Speakeasy',                         // Page title.
			'WP Speakeasy',                         // Menu title.
			'manage_options',                       // Capability.
			'wp-speakeasy',                         // Menu slug.
			array( $this, 'render_settings_page' ), // Callback.
			'dashicons-admin-generic',              // Icon (WordPress dashicon).
			30                                      // Position (after Comments).
		);
	}

	/**
	 * Register settings
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'speakeasy_settings', 'speakeasy_enabled_modules' );
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get data for display.
		$manager           = Speakeasy_Module_Manager::instance();
		$modules           = $manager->get_all_modules();
		$system_info       = $this->get_system_info();
		$diagnostics       = $this->run_diagnostics();
		$update_info       = $this->get_update_info();
		$registration_info = $this->get_registration_info();
		$error_info        = $this->get_error_info();

		// Include the view template.
		include SPEAKEASY_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Get system information
	 *
	 * @since 1.0.0
	 * @return array System information.
	 */
	private function get_system_info(): array {
		return array(
			'plugin_version'    => SPEAKEASY_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'site_url'          => home_url(),
		);
	}

	/**
	 * Run diagnostics
	 *
	 * @since 1.0.0
	 * @return array Diagnostic results.
	 */
	private function run_diagnostics(): array {
		$diagnostics = array();

		// Check Application Passwords availability.
		$diagnostics['app_passwords'] = apply_filters( 'wp_is_application_passwords_available', false );

		// Check REST API accessibility.
		$rest_url                = rest_url();
		$diagnostics['rest_api'] = ! empty( $rest_url );

		// Count registered meta fields.
		$manager                    = Speakeasy_Module_Manager::instance();
		$lap_module                 = $manager->get_module( 'lap-meta' );
		$diagnostics['meta_fields'] = 0;
		if ( $lap_module && method_exists( $lap_module, 'get_field_schemas' ) ) {
			$diagnostics['meta_fields'] = count( $lap_module->get_field_schemas() );
		}

		return $diagnostics;
	}

	/**
	 * Get update information
	 *
	 * Checks GitHub for the latest version using Simple Updater.
	 *
	 * @since 1.0.0
	 * @return array Update information.
	 */
	private function get_update_info(): array {
		$update_info = array(
			'current_version'   => SPEAKEASY_VERSION,
			'latest_version'    => null,
			'update_available'  => false,
			'github_configured' => defined( 'SPEAKEASY_GITHUB_REPO' ) && SPEAKEASY_GITHUB_REPO,
			'github_repo'       => defined( 'SPEAKEASY_GITHUB_REPO' ) ? SPEAKEASY_GITHUB_REPO : null,
		);

		// Only check for updates if GitHub repo is configured.
		if ( ! $update_info['github_configured'] ) {
			return $update_info;
		}

		// Check using Simple Updater.
		if ( ! class_exists( 'Speakeasy_Simple_Updater' ) ) {
			return $update_info;
		}

		$updater = new Speakeasy_Simple_Updater();
		$info    = $updater->check_for_updates();

		if ( ! is_wp_error( $info ) && isset( $info['version'] ) ) {
			$update_info['latest_version']   = $info['version'];
			$update_info['update_available'] = version_compare( $info['version'], SPEAKEASY_VERSION, '>' );
		}

		return $update_info;
	}

	/**
	 * AJAX handler for checking updates
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_check_update(): void {
		check_ajax_referer( 'speakeasy_update', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Clear cache and check for updates.
		delete_transient( 'speakeasy_latest_version_info' );
		$update_info = $this->get_update_info();

		wp_send_json_success( $update_info );
	}

	/**
	 * AJAX handler for triggering manual update
	 *
	 * Uses Simple Updater (WP-CLI or direct download).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_trigger_update(): void {
		check_ajax_referer( 'speakeasy_update', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! class_exists( 'Speakeasy_Simple_Updater' ) ) {
			wp_send_json_error( array( 'message' => 'Updater class not available' ) );
		}

		// Create updater instance.
		$updater = new Speakeasy_Simple_Updater();

		// Trigger update.
		$result = $updater->update();

		// Check result.
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				)
			);
		}

		// Success!
		wp_send_json_success(
			array(
				'message' => $result['message'],
				'version' => $result['version'],
				'method'  => isset( $result['method'] ) ? $result['method'] : 'unknown',
			)
		);
	}

	/**
	 * Get registration information
	 *
	 * @since 1.0.0
	 * @return array Registration information.
	 */
	private function get_registration_info(): array {
		return array(
			'registered'   => get_option( 'speakeasy_activation_reported' ) === 'yes',
			'api_key'      => get_option( 'speakeasy_api_key' ),
			'api_endpoint' => defined( 'SPEAKEASY_API_ENDPOINT' ) ? SPEAKEASY_API_ENDPOINT : null,
		);
	}

	/**
	 * AJAX handler for sending activation report
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_send_activation(): void {
		check_ajax_referer( 'speakeasy_activation', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Clear the registration flag to force re-sending.
		delete_option( 'speakeasy_activation_reported' );

		// Send activation report.
		if ( function_exists( 'speakeasy_send_activation_report' ) ) {
			speakeasy_send_activation_report();
		} else {
			wp_send_json_error( array( 'message' => 'Activation function not available' ) );
			return;
		}

		// Check if it was successful.
		$registered = get_option( 'speakeasy_activation_reported' ) === 'yes';

		if ( $registered ) {
			wp_send_json_success(
				array(
					'message'    => 'Successfully registered with Speakeasy backend!',
					'registered' => true,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message'    => 'Registration failed. Check error logs for details.',
					'registered' => false,
				)
			);
		}
	}

	/**
	 * Get error information
	 *
	 * Retrieves errors from Error Logger if available.
	 *
	 * @since 1.1.0
	 * @return array Error information.
	 */
	private function get_error_info(): array {
		if ( ! class_exists( 'Speakeasy_Error_Logger' ) ) {
			return array(
				'available' => false,
				'errors'    => array(),
				'count'     => 0,
			);
		}

		$logger = Speakeasy_Error_Logger::instance();

		return array(
			'available' => true,
			'errors'    => $logger->get_errors(),
			'count'     => $logger->get_error_count(),
		);
	}

	/**
	 * AJAX handler for getting errors
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function ajax_get_errors(): void {
		check_ajax_referer( 'speakeasy_errors', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! class_exists( 'Speakeasy_Error_Logger' ) ) {
			wp_send_json_error( array( 'message' => 'Error logger not available' ) );
		}

		$logger = Speakeasy_Error_Logger::instance();

		wp_send_json_success(
			array(
				'errors' => $logger->get_errors(),
				'count'  => $logger->get_error_count(),
			)
		);
	}

	/**
	 * AJAX handler for clearing errors
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function ajax_clear_errors(): void {
		check_ajax_referer( 'speakeasy_errors', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! class_exists( 'Speakeasy_Error_Logger' ) ) {
			wp_send_json_error( array( 'message' => 'Error logger not available' ) );
		}

		$logger = Speakeasy_Error_Logger::instance();
		$result = $logger->clear_errors();

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => 'Error log cleared successfully',
					'count'   => 0,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => 'Failed to clear error log',
				)
			);
		}
	}
}
