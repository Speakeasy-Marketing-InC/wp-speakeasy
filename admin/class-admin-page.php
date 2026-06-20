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
 * Manages the admin interface under Settings → WP Speakeasy.
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
	}

	/**
	 * Add admin menu
	 *
	 * Adds submenu under Settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_options_page(
			'WP Speakeasy',                         // Page title.
			'WP Speakeasy',                         // Menu title.
			'manage_options',                       // Capability.
			'wp-speakeasy',                         // Menu slug.
			array( $this, 'render_settings_page' )  // Callback.
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
		$manager       = Speakeasy_Module_Manager::instance();
		$modules       = $manager->get_all_modules();
		$system_info   = $this->get_system_info();
		$diagnostics   = $this->run_diagnostics();

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
		$rest_url                 = rest_url();
		$diagnostics['rest_api']  = ! empty( $rest_url );

		// Count registered meta fields.
		$manager                       = Speakeasy_Module_Manager::instance();
		$lap_module                    = $manager->get_module( 'lap-meta' );
		$diagnostics['meta_fields']    = 0;
		if ( $lap_module && method_exists( $lap_module, 'get_field_schemas' ) ) {
			$diagnostics['meta_fields'] = count( $lap_module->get_field_schemas() );
		}

		return $diagnostics;
	}
}
