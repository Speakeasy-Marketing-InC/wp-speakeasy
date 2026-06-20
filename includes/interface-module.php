<?php
/**
 * Module interface for WP Speakeasy
 *
 * Defines the contract that all modules must implement.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

/**
 * Interface Speakeasy_Module
 *
 * All modules must implement this interface to be registered
 * with the Module Manager.
 *
 * @since 1.0.0
 */
interface Speakeasy_Module {

	/**
	 * Get module name (human-readable)
	 *
	 * @since 1.0.0
	 * @return string Module name for display.
	 */
	public function get_name(): string;

	/**
	 * Get module description
	 *
	 * @since 1.0.0
	 * @return string Module description for display.
	 */
	public function get_description(): string;

	/**
	 * Get module version
	 *
	 * @since 1.0.0
	 * @return string Semantic version (e.g., "1.0.0").
	 */
	public function get_version(): string;

	/**
	 * Initialize module (register hooks)
	 *
	 * Called by Module Manager when the module should activate.
	 * This is where the module registers WordPress actions and filters.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void;

	/**
	 * Check if module is enabled
	 *
	 * @since 1.0.0
	 * @return bool True if module should be active, false otherwise.
	 */
	public function is_enabled(): bool;

	/**
	 * Module priority (lower = earlier execution)
	 *
	 * Determines the order in which modules are initialized.
	 * Lower numbers execute first.
	 *
	 * @since 1.0.0
	 * @return int Priority value (default: 100).
	 */
	public function get_priority(): int;
}
