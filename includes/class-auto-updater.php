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
			error_log( 'WP Speakeasy: SPEAKEASY_GITHUB_REPO not defined in wp-config.php. Auto-updates disabled.' );
			return;
		}

		// Check if Plugin Update Checker is available.
		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			error_log( 'WP Speakeasy: Plugin Update Checker library not found. Run composer install.' );
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
	 * Send update report to API
	 *
	 * Reports update status to the Speakeasy API if configured.
	 *
	 * This method sends a non-blocking POST request to your backend monitoring
	 * system to track plugin updates across multiple WordPress sites.
	 *
	 * Configuration (in wp-config.php):
	 * ```php
	 * define( 'SPEAKEASY_API_ENDPOINT', 'https://api.speakeasy.com/wp-plugin' );
	 * define( 'SPEAKEASY_API_TOKEN', 'spk_xxxxxxxxxxxx' );
	 * ```
	 *
	 * API Request:
	 * - Method: POST
	 * - URL: {SPEAKEASY_API_ENDPOINT}/update
	 * - Auth: Bearer {SPEAKEASY_API_TOKEN}
	 * - Body: { site, plugin_version, status, timestamp }
	 *
	 * Example use case:
	 * If managing 38 law firm websites, this allows you to build a centralized
	 * dashboard showing which sites successfully updated and which failed.
	 *
	 * Note: This is OPTIONAL. The plugin works perfectly without API reporting.
	 * If not configured, this method silently returns without sending anything.
	 *
	 * @since 1.0.0
	 * @param string $status Update status ('success' or 'failed').
	 * @return void
	 */
	private function send_update_report( string $status ): void {
		// Only send report if API is configured in wp-config.php.
		// If not defined, skip reporting (plugin still works normally).
		if ( ! defined( 'SPEAKEASY_API_ENDPOINT' ) || ! defined( 'SPEAKEASY_API_TOKEN' ) ) {
			return;
		}

		// Send non-blocking POST request to centralized monitoring API.
		// This allows tracking update status across multiple WordPress sites.
		wp_remote_post(
			SPEAKEASY_API_ENDPOINT . '/update',
			array(
				'body'    => array(
					'site'           => home_url(),
					'plugin_version' => SPEAKEASY_VERSION,
					'status'         => $status,
					'timestamp'      => current_time( 'mysql' ),
				),
				'headers' => array(
					'Authorization' => 'Bearer ' . SPEAKEASY_API_TOKEN,
				),
				'timeout'  => 5,
				'blocking' => false, // Non-blocking: don't wait for response or slow down site.
			)
		);
	}
}
