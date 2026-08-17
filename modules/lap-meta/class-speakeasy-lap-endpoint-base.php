<?php
/**
 * Shared base for LAP REST endpoints
 *
 * Holds the request-handling concerns every LAP variant endpoint needs:
 * API key verification, LAP page validation, and Meta Box availability.
 *
 * @package WP_Speakeasy
 * @since   1.6.0
 */

/**
 * Class Speakeasy_LAP_Endpoint_Base
 *
 * Extracted from Speakeasy_LAP_Meta_Endpoint when the legacy_v1 variant was
 * added. The LAP plugin exists in multiple versions with incompatible meta key
 * sets, each served by its own route; auth and page validation are identical
 * across all of them and are inherited rather than copied per variant.
 *
 * Behavior is intentionally identical to the pre-extraction implementation —
 * see DECISIONS.md, session 7.
 *
 * @since 1.6.0
 */
abstract class Speakeasy_LAP_Endpoint_Base {


	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'speakeasy/v1';

	/**
	 * Template slug that identifies a Local Area Page
	 *
	 * @var string
	 */
	const LAP_TEMPLATE = 'localareapage.php';

	/**
	 * Plugin API key
	 *
	 * @var string|null
	 */
	protected $api_key;

	/**
	 * Constructor
	 *
	 * @since 1.6.0
	 * @param string|null $api_key Plugin API key. Loaded from options when null.
	 */
	public function __construct( $api_key = null ) {
		$this->api_key = $api_key ?? get_option( 'speakeasy_api_key' );
	}

	/**
	 * Register this endpoint's REST routes
	 *
	 * @since  1.6.0
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Verify API key from request header
	 *
	 * Uses timing-safe comparison to prevent timing attacks.
	 *
	 * @since  1.3.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function verify_api_key( $request ) {
		$provided_key = $request->get_header( 'X-Speakeasy-API-Key' );

		if ( empty( $provided_key ) ) {
			return new WP_Error(
				'missing_api_key',
				'API key is required',
				array( 'status' => 401 )
			);
		}

		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'api_key_not_configured',
				'API key not configured on this site',
				array( 'status' => 500 )
			);
		}

		if ( ! hash_equals( $this->api_key, $provided_key ) ) {
			return new WP_Error(
				'invalid_api_key',
				'Invalid API key',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Standard page_id route argument definition
	 *
	 * @since  1.6.0
	 * @return array<string, array>
	 */
	protected function page_id_arg(): array {
		return array(
			'page_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Validate that a page exists and uses the LAP template
	 *
	 * @since  1.3.0
	 * @param  int $page_id Post ID to validate.
	 * @return true|WP_Error True on success, WP_Error with 'page_not_found' or 'not_lap_page' on failure.
	 */
	protected function validate_lap_page( int $page_id ) {
		$post = get_post( $page_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error(
				'page_not_found',
				'Page not found',
				array( 'status' => 404 )
			);
		}

		$template = get_post_meta( $page_id, '_wp_page_template', true );

		if ( self::LAP_TEMPLATE !== $template ) {
			return new WP_Error(
				'not_lap_page',
				'This page does not use the localareapage.php template',
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Check whether Meta Box API functions are available
	 *
	 * Filterable via speakeasy_metabox_available for testing.
	 *
	 * @since  1.3.0
	 * @return bool
	 */
	protected function is_metabox_available(): bool {
		$available = function_exists( 'rwmb_meta' ) && function_exists( 'rwmb_set_meta' );

		/**
		 * Filters whether the Meta Box API is considered available.
		 *
		 * @since 1.3.0
		 * @param bool $available Whether rwmb_meta() and rwmb_set_meta() both exist.
		 */
		return (bool) apply_filters( 'speakeasy_metabox_available', $available );
	}

	/**
	 * Build the standard Meta Box unavailable error
	 *
	 * @since  1.6.0
	 * @return WP_Error
	 */
	protected function metabox_unavailable_error(): WP_Error {
		return new WP_Error(
			'metabox_unavailable',
			'Meta Box plugin is not active on this site',
			array( 'status' => 503 )
		);
	}
}
