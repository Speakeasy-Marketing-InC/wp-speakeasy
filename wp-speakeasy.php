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

	// Report activation to Speakeasy API if configured.
	if ( defined( 'SPEAKEASY_API_ENDPOINT' ) && defined( 'SPEAKEASY_API_TOKEN' ) ) {
		wp_remote_post(
			SPEAKEASY_API_ENDPOINT . '/activation',
			array(
				'body' => array(
					'site'              => home_url(),
					'plugin_version'    => SPEAKEASY_VERSION,
					'wordpress_version' => get_bloginfo( 'version' ),
					'php_version'       => PHP_VERSION,
					'timestamp'         => current_time( 'mysql' ),
				),
				'headers' => array(
					'Authorization' => 'Bearer ' . SPEAKEASY_API_TOKEN,
				),
				'timeout' => 5,
				'blocking' => false, // Don't wait for response.
			)
		);
	}
}
register_activation_hook( __FILE__, 'speakeasy_activation' );

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
