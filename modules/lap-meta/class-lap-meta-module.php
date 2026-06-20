<?php
/**
 * LAP Meta Fields Module
 *
 * Exposes Local Area Page (LAP) custom meta fields to the WordPress REST API.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

/**
 * Class Speakeasy_LAP_Meta_Module
 *
 * This module registers custom meta fields from LAP page templates
 * to make them accessible via the WordPress REST API.
 *
 * Schema files define the field structure and are loaded automatically
 * based on detected LAP templates in the site.
 *
 * @since 1.0.0
 */
class Speakeasy_LAP_Meta_Module implements Speakeasy_Module {

	/**
	 * Field schemas loaded from schema files
	 *
	 * @var array<string, array>
	 */
	private $field_schemas = array();

	/**
	 * Constructor
	 *
	 * Loads schema files on instantiation.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_schemas();
	}

	/**
	 * Get module name
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'LAP Meta Fields';
	}

	/**
	 * Get module description
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Exposes Local Area Page meta fields to WordPress REST API.';
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
	 * @since 1.0.0
	 * @return int
	 */
	public function get_priority(): int {
		return 100;
	}

	/**
	 * Check if module is enabled
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
	 * Registers the rest_api_init action to register meta fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_meta_fields' ) );
	}

	/**
	 * Load schema files
	 *
	 * Detects which LAP templates are used on this site
	 * and loads the corresponding schema files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load_schemas(): void {
		// Detect LAP templates from database.
		$templates = $this->detect_lap_templates();

		// Load schema for each detected template.
		foreach ( $templates as $template ) {
			$schema                = $this->load_schema_file( $template );
			$this->field_schemas = array_merge( $this->field_schemas, $schema );
		}

		// Fallback: Load default schema if no templates detected.
		if ( empty( $this->field_schemas ) ) {
			$default_schema      = $this->load_schema_file( 'localareapage' );
			$this->field_schemas = $default_schema;
		}
	}

	/**
	 * Detect LAP templates used on this site
	 *
	 * Queries the database for pages using LAP templates.
	 * Returns array of template basenames (without .php extension).
	 *
	 * @since 1.0.0
	 * @return array<string> Template basenames.
	 */
	private function detect_lap_templates(): array {
		global $wpdb;

		// Query pages using templates with "local" and "area" in the name.
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND meta_value LIKE %s",
				'_wp_page_template',
				'%local%area%'
			)
		);

		if ( empty( $results ) || ! is_array( $results ) ) {
			return array();
		}

		// Convert template filenames to schema file basenames.
		return array_map(
			function ( $template ) {
				// Remove .php extension and get basename.
				return basename( $template, '.php' );
			},
			$results
		);
	}

	/**
	 * Load schema from file
	 *
	 * @since 1.0.0
	 * @param string $template_name Template basename (without .php).
	 * @return array<string, array> Schema array or empty array if file not found.
	 */
	private function load_schema_file( string $template_name ): array {
		$schema_file = SPEAKEASY_PATH . "modules/lap-meta/schemas/{$template_name}.php";

		if ( ! file_exists( $schema_file ) ) {
			error_log( "WP Speakeasy: Schema file not found: {$schema_file}" );
			return array();
		}

		$schema = require $schema_file;

		if ( ! is_array( $schema ) ) {
			error_log( "WP Speakeasy: Invalid schema format in: {$schema_file}" );
			return array();
		}

		return $schema;
	}

	/**
	 * Register meta fields with REST API
	 *
	 * Called on rest_api_init action.
	 * Registers all fields from loaded schemas.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta_fields(): void {
		foreach ( $this->field_schemas as $field_key => $args ) {
			register_meta( 'post', $field_key, $args );
		}
	}

	/**
	 * Get loaded field schemas
	 *
	 * For testing and debugging purposes.
	 *
	 * @since 1.0.0
	 * @return array<string, array>
	 */
	public function get_field_schemas(): array {
		return $this->field_schemas;
	}
}
