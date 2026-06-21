<?php
/**
 * Auto-Updater for WP Speakeasy
 *
 * Handles automatic updates from GitHub releases.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Class Speakeasy_Auto_Updater
 *
 * Integrates with Plugin Update Checker library to enable
 * automatic updates from GitHub releases.
 *
 * Configuration via wp-config.php:
 * - SPEAKEASY_GITHUB_REPO: GitHub repository (e.g., 'speakeasy/wp-speakeasy')
 * - SPEAKEASY_GITHUB_TOKEN: GitHub personal access token (optional, for private repos)
 *
 * @since 1.0.0
 */
class Speakeasy_Auto_Updater {

	/**
	 * Plugin Update Checker instance
	 *
	 * @var object|null
	 */
	private $update_checker = null;

	/**
	 * Constructor
	 *
	 * Initializes the GitHub updater and auto-update mechanism.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Only initialize if GitHub repo is configured.
		if ( ! defined( 'SPEAKEASY_GITHUB_REPO' ) ) {
			$error_msg = 'SPEAKEASY_GITHUB_REPO not defined in wp-config.php. Auto-updates disabled.';
			error_log( 'WP Speakeasy: ' . $error_msg );

			// Log to Error Logger if available.
			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_error(
					'warning',
					$error_msg,
					__FILE__,
					__LINE__,
					array( 'component' => 'Auto Updater' )
				);
			}
			return;
		}

		// Check if Plugin Update Checker is available.
		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			$error_msg = 'Plugin Update Checker library not found. Run composer install.';
			error_log( 'WP Speakeasy: ' . $error_msg );

			// Log to Error Logger if available.
			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_error(
					'error',
					$error_msg,
					__FILE__,
					__LINE__,
					array( 'component' => 'Auto Updater' )
				);
			}
			return;
		}

		$this->init_github_updater();
		$this->init_auto_update();
		$this->init_reporting();
	}

	/**
	 * Initialize GitHub update checker
	 *
	 * Configures the Plugin Update Checker library to check GitHub for new releases.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_github_updater(): void {
		try {
			$this->update_checker = PucFactory::buildUpdateChecker(
				'https://github.com/' . SPEAKEASY_GITHUB_REPO . '/',
				SPEAKEASY_PATH . 'wp-speakeasy.php',
				'wp-speakeasy'
			);

			// Set branch to check for updates.
			$this->update_checker->setBranch( 'main' );

			// Set GitHub authentication token if configured.
			if ( defined( 'SPEAKEASY_GITHUB_TOKEN' ) ) {
				$this->update_checker->setAuthentication( SPEAKEASY_GITHUB_TOKEN );
			}
		} catch ( Exception $e ) {
			error_log( 'WP Speakeasy: Failed to initialize GitHub updater: ' . $e->getMessage() );

			// Log to Error Logger if available.
			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_exception(
					$e,
					array( 'component' => 'Auto Updater' )
				);
			}
		}
	}

	/**
	 * Initialize auto-update
	 *
	 * Enables automatic updates for this plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_auto_update(): void {
		add_filter( 'auto_update_plugin', array( $this, 'enable_auto_update' ), 10, 2 );
	}

	/**
	 * Initialize update reporting
	 *
	 * Hooks into the update process to report status.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_reporting(): void {
		add_action( 'upgrader_process_complete', array( $this, 'report_update' ), 10, 2 );
	}

	/**
	 * Enable auto-update for this plugin
	 *
	 * Filter callback for 'auto_update_plugin'.
	 *
	 * @since 1.0.0
	 * @param bool   $update Whether to auto-update.
	 * @param object $item   Plugin update data.
	 * @return bool True to enable auto-update for this plugin.
	 */
	public function enable_auto_update( $update, $item ) {
		// Enable auto-update only for this plugin.
		if ( isset( $item->plugin ) && strpos( $item->plugin, 'wp-speakeasy' ) !== false ) {
			return true;
		}
		return $update;
	}

	/**
	 * Report update completion
	 *
	 * Action callback for 'upgrader_process_complete'.
	 * Reports successful updates to the API.
	 *
	 * @since 1.0.0
	 * @param object $upgrader WP_Upgrader instance.
	 * @param array  $options  Update options.
	 * @return void
	 */
	public function report_update( $upgrader, $options ) {
		// Check if this was a plugin update.
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		// Check if our plugin was updated.
		if ( ! isset( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
			return;
		}

		foreach ( $options['plugins'] as $plugin ) {
			if ( strpos( $plugin, 'wp-speakeasy' ) !== false ) {
				$this->send_update_report( 'success' );
				break;
			}
		}
	}

	/**
	 * Manually trigger plugin update
	 *
	 * Forces an immediate check for updates and installs if available.
	 * Called from admin AJAX handler when user clicks "Update Now" button.
	 *
	 * @since 1.1.0
	 * @return array|WP_Error Update result with success/error details.
	 */
	public function trigger_manual_update() {
		if ( ! $this->update_checker ) {
			$error_msg = 'Update checker not initialized. Check if Plugin Update Checker library is installed.';
			error_log( 'WP Speakeasy: ' . $error_msg );

			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_error(
					'error',
					$error_msg,
					__FILE__,
					__LINE__,
					array( 'component' => 'Auto Updater' )
				);
			}

			return new WP_Error( 'updater_not_initialized', $error_msg );
		}

		// Force refresh update information from GitHub.
		$update_info = $this->update_checker->requestInfo();

		if ( ! $update_info ) {
			$error_msg = 'Failed to retrieve update information from GitHub. Check repository configuration.';
			error_log( 'WP Speakeasy: ' . $error_msg );

			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_error(
					'error',
					$error_msg,
					__FILE__,
					__LINE__,
					array(
						'component'       => 'Auto Updater',
						'github_repo'     => SPEAKEASY_GITHUB_REPO,
						'current_version' => SPEAKEASY_VERSION,
					)
				);
			}

			return new WP_Error( 'update_check_failed', $error_msg );
		}

		// Check if update is available.
		if ( ! $update_info || version_compare( $update_info->version, SPEAKEASY_VERSION, '<=' ) ) {
			return array(
				'updated'         => false,
				'message'         => 'No update available. You are running the latest version.',
				'current_version' => SPEAKEASY_VERSION,
				'latest_version'  => $update_info ? $update_info->version : SPEAKEASY_VERSION,
			);
		}

		// Load WordPress upgrader classes.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Set up upgrader with a silent skin.
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		// Get plugin file path.
		$plugin_file = plugin_basename( SPEAKEASY_PATH . 'wp-speakeasy.php' );

		// Perform the update.
		$result = $upgrader->upgrade( $plugin_file );

		if ( is_wp_error( $result ) ) {
			$error_msg = 'Plugin update failed: ' . $result->get_error_message();
			error_log( 'WP Speakeasy: ' . $error_msg );

			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_error(
					'error',
					$error_msg,
					__FILE__,
					__LINE__,
					array(
						'component'      => 'Auto Updater',
						'error_code'     => $result->get_error_code(),
						'latest_version' => $update_info->version,
					)
				);
			}

			return $result;
		}

		if ( false === $result ) {
			$error_msg = 'Plugin update failed for unknown reason.';
			error_log( 'WP Speakeasy: ' . $error_msg );

			if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
				Speakeasy_Error_Logger::instance()->log_error(
					'error',
					$error_msg,
					__FILE__,
					__LINE__,
					array(
						'component'      => 'Auto Updater',
						'latest_version' => $update_info->version,
					)
				);
			}

			return new WP_Error( 'update_failed', $error_msg );
		}

		// Update successful.
		$success_msg = 'Plugin successfully updated to version ' . $update_info->version;
		error_log( 'WP Speakeasy: ' . $success_msg );

		// Send success report to API.
		$this->send_update_report( 'success' );

		return array(
			'updated'          => true,
			'message'          => $success_msg,
			'previous_version' => SPEAKEASY_VERSION,
			'new_version'      => $update_info->version,
		);
	}

	/**
	 * Get update checker instance
	 *
	 * Returns the Plugin Update Checker instance for external use.
	 *
	 * @since 1.1.0
	 * @return object|null Update checker instance or null if not initialized.
	 */
	public function get_update_checker() {
		return $this->update_checker;
	}

	/**
	 * Send update report to API
	 *
	 * Reports update status to the Speakeasy API.
	 *
	 * This method sends a non-blocking POST request to your backend monitoring
	 * system to track plugin updates across multiple WordPress sites.
	 *
	 * Configuration:
	 * - SPEAKEASY_API_ENDPOINT: Defaults to https://api.speakeasy.com/wp-plugin
	 *   (can be overridden in wp-config.php)
	 * - API key: Auto-generated on plugin activation, stored in speakeasy_api_key option
	 *
	 * API Request:
	 * - Method: POST
	 * - URL: {SPEAKEASY_API_ENDPOINT}/update
	 * - Auth: Bearer {auto-generated-api-key}
	 * - Body: { site, plugin_version, status, timestamp }
	 *
	 * Example use case:
	 * If managing hundreds of WordPress sites, this allows you to build a centralized
	 * dashboard showing which sites successfully updated and which failed.
	 *
	 * Note: This is automatic. The plugin sends update reports using the API key
	 * that was auto-registered during plugin activation.
	 *
	 * @since 1.0.0
	 * @param string $status Update status ('success' or 'failed').
	 * @return void
	 */
	private function send_update_report( string $status ): void {
		// Send non-blocking POST request to centralized monitoring API.
		// This allows tracking update status across multiple WordPress sites.
		// No authentication required - endpoint is publicly accessible.
		wp_remote_post(
			SPEAKEASY_API_ENDPOINT . '/update',
			array(
				'body'     => wp_json_encode(
					array(
						'siteUrl'       => home_url(),
						'pluginVersion' => SPEAKEASY_VERSION,
						'status'        => $status,
					)
				),
				'headers'  => array(
					'Content-Type' => 'application/json',
				),
				'timeout'  => 5,
				'blocking' => false, // Non-blocking: don't wait for response or slow down site.
			)
		);
	}
}
