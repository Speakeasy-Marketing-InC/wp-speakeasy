<?php
/**
 * Plugin Name: WP Speakeasy
 * Plugin URI: https://github.com/speakeasy/wp-speakeasy
 * Description: WordPress automation toolkit for REST API enhancements. Enables Application Passwords, exposes custom meta fields to REST API, and provides auto-update capability.
 * Version: 1.0.0
 * Author: Speakeasy
 * Author URI: https://speakeasy.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Text Domain: wp-speakeasy
 *
 * @package WP_Speakeasy
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'SPEAKEASY_VERSION', '1.0.0' );
define( 'SPEAKEASY_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPEAKEASY_URL', plugin_dir_url( __FILE__ ) );

// Default API endpoint (can be overridden via wp-config.php).
if ( ! defined( 'SPEAKEASY_API_ENDPOINT' ) ) {
	define( 'SPEAKEASY_API_ENDPOINT', 'https://server.speakeasymarketinginc.com/api/wordpress-sites' );
}

// Default GitHub repository for auto-updates (can be overridden via wp-config.php).
if ( ! defined( 'SPEAKEASY_GITHUB_REPO' ) ) {
	define( 'SPEAKEASY_GITHUB_REPO', 'Speakeasy-Marketing-InC/wp-speakeasy' );
}

// Load Composer autoloader.
if ( file_exists( SPEAKEASY_PATH . 'vendor/autoload.php' ) ) {
	require_once SPEAKEASY_PATH . 'vendor/autoload.php';
}

// Load core classes.
require_once SPEAKEASY_PATH . 'includes/interface-module.php';
require_once SPEAKEASY_PATH . 'includes/class-module-manager.php';

// Load modules.
require_once SPEAKEASY_PATH . 'modules/app-passwords/class-app-passwords-module.php';
require_once SPEAKEASY_PATH . 'modules/lap-meta/class-lap-meta-module.php';

// Load optional components if they exist.
if ( file_exists( SPEAKEASY_PATH . 'includes/class-error-logger.php' ) ) {
	require_once SPEAKEASY_PATH . 'includes/class-error-logger.php';
}

if ( file_exists( SPEAKEASY_PATH . 'includes/class-auto-updater.php' ) ) {
	require_once SPEAKEASY_PATH . 'includes/class-auto-updater.php';
}

if ( file_exists( SPEAKEASY_PATH . 'includes/class-api-reporter.php' ) ) {
	require_once SPEAKEASY_PATH . 'includes/class-api-reporter.php';
}

// Load admin page.
if ( is_admin() && file_exists( SPEAKEASY_PATH . 'admin/class-admin-page.php' ) ) {
	require_once SPEAKEASY_PATH . 'admin/class-admin-page.php';
}

/**
 * Initialize Error Logger early
 *
 * Initialize the error logger before other components so it can capture
 * errors during initialization.
 *
 * @since 1.1.0
 * @return void
 */
function speakeasy_init_error_logger() {
	if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
		Speakeasy_Error_Logger::instance();
	}
}
add_action( 'plugins_loaded', 'speakeasy_init_error_logger', 5 );

/**
 * Initialize WP Speakeasy plugin
 *
 * Registers all modules and initializes the plugin system.
 *
 * @since 1.0.0
 * @return void
 */
function speakeasy_automation_init() {
	$manager = Speakeasy_Module_Manager::instance();

	// Register modules.
	$manager->register_module( 'app-passwords', new Speakeasy_App_Passwords_Module() );
	$manager->register_module( 'lap-meta', new Speakeasy_LAP_Meta_Module() );

	// Initialize enabled modules.
	$manager->init_modules();

	// Initialize auto-updater if available.
	if ( class_exists( 'Speakeasy_Auto_Updater' ) ) {
		new Speakeasy_Auto_Updater();
	}

	// Initialize API reporter if available.
	if ( class_exists( 'Speakeasy_API_Reporter' ) ) {
		new Speakeasy_API_Reporter();
	}

	// Initialize admin page if in admin area.
	if ( is_admin() && class_exists( 'Speakeasy_Admin_Page' ) ) {
		new Speakeasy_Admin_Page();
	}
}
add_action( 'plugins_loaded', 'speakeasy_automation_init' );

/**
 * Plugin activation hook
 *
 * Runs when the plugin is activated.
 * Generates a unique API key and schedules activation report retries.
 *
 * @since 1.0.0
 * @return void
 */
function speakeasy_activation() {
	// Enable all modules by default on first activation.
	$enabled = get_option( 'speakeasy_enabled_modules', false );
	if ( false === $enabled ) {
		update_option( 'speakeasy_enabled_modules', array( 'app-passwords', 'lap-meta' ) );
	}

	// Generate API key on first activation.
	$api_key = get_option( 'speakeasy_api_key' );
	if ( ! $api_key ) {
		// Generate a secure 64-character API key.
		$api_key = bin2hex( random_bytes( 32 ) );
		update_option( 'speakeasy_api_key', $api_key );
	}

	// Schedule hourly activation report retries until confirmed.
	if ( ! wp_next_scheduled( 'speakeasy_retry_activation_report' ) ) {
		wp_schedule_event( time(), 'hourly', 'speakeasy_retry_activation_report' );
	}

	// Send initial activation report immediately.
	speakeasy_send_activation_report();
}
register_activation_hook( __FILE__, 'speakeasy_activation' );

/**
 * Send activation report to central server
 *
 * Registers this WordPress site with the Speakeasy backend.
 * Retries hourly until server returns success response.
 *
 * No authentication required - endpoint is publicly accessible.
 *
 * @since 1.0.0
 * @return void
 */
function speakeasy_send_activation_report() {
	// Already confirmed? Stop trying.
	if ( get_option( 'speakeasy_activation_reported' ) === 'yes' ) {
		wp_clear_scheduled_hook( 'speakeasy_retry_activation_report' );
		return;
	}

	$plugin_api_key = get_option( 'speakeasy_api_key' );
	if ( ! $plugin_api_key ) {
		return;
	}

	// Get module status for registration.
	$manager        = Speakeasy_Module_Manager::instance();
	$modules        = $manager->get_all_modules();
	$active_modules = array();
	$module_status  = array();

	foreach ( $modules as $id => $module ) {
		if ( $module->is_enabled() ) {
			$active_modules[]     = $id;
			$module_status[ $id ] = array(
				'enabled' => true,
				'version' => $module->get_version(),
			);
		}
	}

	$response = wp_remote_post(
		SPEAKEASY_API_ENDPOINT . '/register',
		array(
			'body'     => wp_json_encode(
				array(
					'siteUrl'          => home_url(),
					'pluginApiKey'     => $plugin_api_key,
					'pluginVersion'    => SPEAKEASY_VERSION,
					'wordpressVersion' => get_bloginfo( 'version' ),
					'phpVersion'       => PHP_VERSION,
					'activeModules'    => $active_modules,
					'moduleStatus'     => $module_status,
				)
			),
			'headers'  => array(
				'Content-Type' => 'application/json',
			),
			'timeout'  => 15,
			'blocking' => true, // Must wait to check for confirmation.
		)
	);

	// Check if server confirmed receipt.
	if ( ! is_wp_error( $response ) ) {
		$status_code = wp_remote_retrieve_response_code( $response );

		// 200 or 201 means success.
		if ( 200 === $status_code || 201 === $status_code ) {
			update_option( 'speakeasy_activation_reported', 'yes' );
			wp_clear_scheduled_hook( 'speakeasy_retry_activation_report' );
			error_log( 'WP Speakeasy: Successfully registered with central server' );
			return;
		}
	}

	// Log the failure (will retry next hour).
	if ( is_wp_error( $response ) ) {
		error_log( 'WP Speakeasy: Registration failed - ' . $response->get_error_message() );
	} else {
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		error_log( "WP Speakeasy: Registration failed with status $status_code - $body" );
	}
}

// Hook the retry function to the scheduled event.
add_action( 'speakeasy_retry_activation_report', 'speakeasy_send_activation_report' );

/**
 * Plugin deactivation hook
 *
 * Runs when the plugin is deactivated.
 *
 * @since 1.0.0
 * @return void
 */
function speakeasy_deactivation() {
	// Optional: Clean up transients or scheduled events.
	// Note: We don't delete options here in case user reactivates.
}
register_deactivation_hook( __FILE__, 'speakeasy_deactivation' );
