<?php
/**
 * Module Manager for WP Speakeasy
 *
 * Manages registration, initialization, and lifecycle of all modules.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

/**
 * Class Speakeasy_Module_Manager
 *
 * Singleton pattern for managing all plugin modules.
 * Handles module registration, initialization, enabling/disabling.
 *
 * @since 1.0.0
 */
class Speakeasy_Module_Manager {

	/**
	 * Singleton instance
	 *
	 * @var Speakeasy_Module_Manager|null
	 */
	private static $instance = null;

	/**
	 * Registered modules
	 *
	 * @var array<string, Speakeasy_Module>
	 */
	private $modules = array();

	/**
	 * Enabled module IDs (stored in wp_options)
	 *
	 * @var array<string>
	 */
	private $enabled_modules = array();

	/**
	 * Whether modules have been initialized
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Private constructor (singleton pattern)
	 */
	private function __construct() {
		$this->load_enabled_modules();
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return Speakeasy_Module_Manager
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a module
	 *
	 * @since 1.0.0
	 * @param string            $id     Module ID (alphanumeric with hyphens).
	 * @param Speakeasy_Module $module Module instance.
	 * @return void
	 */
	public function register_module( string $id, Speakeasy_Module $module ): void {
		// Validate module ID format.
		if ( ! preg_match( '/^[a-z0-9-]+$/', $id ) ) {
			error_log( "WP Speakeasy: Invalid module ID format: {$id}" );
			return;
		}

		$this->modules[ $id ] = $module;
	}

	/**
	 * Initialize all enabled modules
	 *
	 * Modules are initialized in priority order (lower priority = first).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_modules(): void {
		if ( $this->initialized ) {
			return;
		}

		// Sort modules by priority.
		$sorted_modules = $this->modules;
		uasort(
			$sorted_modules,
			function ( $a, $b ) {
				return $a->get_priority() <=> $b->get_priority();
			}
		);

		// Initialize enabled modules in priority order.
		foreach ( $sorted_modules as $id => $module ) {
			if ( $module->is_enabled() ) {
				$module->init();
			}
		}

		$this->initialized = true;
	}

	/**
	 * Get a registered module
	 *
	 * @since 1.0.0
	 * @param string $id Module ID.
	 * @return Speakeasy_Module|null Module instance or null if not found.
	 */
	public function get_module( string $id ): ?Speakeasy_Module {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Get all registered modules
	 *
	 * @since 1.0.0
	 * @return array<string, Speakeasy_Module>
	 */
	public function get_all_modules(): array {
		return $this->modules;
	}

	/**
	 * Check if a module is enabled
	 *
	 * @since 1.0.0
	 * @param string $id Module ID.
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_module_enabled( string $id ): bool {
		$module = $this->get_module( $id );
		if ( null === $module ) {
			return false;
		}
		return $module->is_enabled();
	}

	/**
	 * Enable a module
	 *
	 * @since 1.0.0
	 * @param string $id Module ID.
	 * @return bool True on success, false on failure.
	 */
	public function enable_module( string $id ): bool {
		$module = $this->get_module( $id );
		if ( null === $module ) {
			return false;
		}

		if ( ! in_array( $id, $this->enabled_modules, true ) ) {
			$this->enabled_modules[] = $id;
			$this->save_enabled_modules();
		}

		return true;
	}

	/**
	 * Disable a module
	 *
	 * @since 1.0.0
	 * @param string $id Module ID.
	 * @return bool True on success, false on failure.
	 */
	public function disable_module( string $id ): bool {
		$module = $this->get_module( $id );
		if ( null === $module ) {
			return false;
		}

		$key = array_search( $id, $this->enabled_modules, true );
		if ( false !== $key ) {
			unset( $this->enabled_modules[ $key ] );
			$this->enabled_modules = array_values( $this->enabled_modules ); // Re-index.
			$this->save_enabled_modules();
		}

		return true;
	}

	/**
	 * Load enabled modules from wp_options
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_enabled_modules(): void {
		$enabled = get_option( 'speakeasy_enabled_modules', array() );
		if ( is_array( $enabled ) ) {
			$this->enabled_modules = $enabled;
		}
	}

	/**
	 * Save enabled modules to wp_options
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_enabled_modules(): void {
		update_option( 'speakeasy_enabled_modules', $this->enabled_modules );
	}
}
