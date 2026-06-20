<?php
/**
 * Application Passwords Enabler Module
 *
 * Forces WordPress Application Passwords to be available,
 * overriding any theme or plugin restrictions.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

/**
 * Class Speakeasy_App_Passwords_Module
 *
 * This module ensures Application Passwords are always available
 * by adding high-priority filters that return true, overriding
 * any restrictions imposed by themes, security plugins, or other code.
 *
 * @since 1.0.0
 */
class Speakeasy_App_Passwords_Module implements Speakeasy_Module {

	/**
	 * Get module name
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'Application Passwords Enabler';
	}

	/**
	 * Get module description
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Force-enables WordPress Application Passwords, overriding theme and plugin restrictions.';
	}

	/**
	 * Get module version
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Get module priority
	 *
	 * Uses priority 999 to ensure we run after other filters
	 * that might try to disable Application Passwords.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_priority(): int {
		return 999;
	}

	/**
	 * Check if module is enabled
	 *
	 * This module is always enabled to ensure REST API automation works.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Initialize module
	 *
	 * Registers high-priority filters to force-enable Application Passwords.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		// Force Application Passwords to be available globally.
		add_filter( 'wp_is_application_passwords_available', '__return_true', 999 );

		// Force Application Passwords to be available for all users.
		add_filter( 'wp_is_application_passwords_available_for_user', '__return_true', 999 );

		// Remove known blockers from common plugins.
		$this->remove_known_blockers();
	}

	/**
	 * Remove known blockers
	 *
	 * Removes filters from known plugins that disable Application Passwords.
	 * This method is called during init to ensure our filters take precedence.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function remove_known_blockers(): void {
		// Wordfence blocks Application Passwords - remove their filter.
		remove_filter( 'wp_is_application_passwords_available', 'wordfence_disable_app_passwords', 10 );

		// Some themes add filters to disable Application Passwords.
		// We use priority 999 on our filters which should override most restrictions.
		// If specific blockers are discovered, add them here.

		// Example of removing all filters at a specific priority:
		// remove_all_filters( 'wp_is_application_passwords_available_for_user', 10 );
		// Then re-add our filter at 999 (already done in init()).
	}
}
