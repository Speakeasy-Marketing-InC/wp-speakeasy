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
	 * Variant detector
	 *
	 * @var Speakeasy_LAP_Variant_Detector
	 */
	protected $detector;

	/**
	 * Constructor
	 *
	 * @since 1.6.0
	 * @param string|null                         $api_key  Plugin API key. Loaded from options when null.
	 * @param Speakeasy_LAP_Variant_Detector|null $detector Detector instance. Created when null.
	 */
	public function __construct( $api_key = null, $detector = null ) {
		$this->api_key  = $api_key ?? get_option( 'speakeasy_api_key' );
		$this->detector = $detector ?? new Speakeasy_LAP_Variant_Detector();
	}

	/**
	 * Register this endpoint's REST routes
	 *
	 * @since  1.6.0
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * The LAP plugin variant this endpoint's route serves
	 *
	 * @since  1.7.0
	 * @return string One of the Speakeasy_LAP_Variant_Detector::VARIANT_* constants.
	 */
	abstract protected function get_route_variant(): string;

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

	/**
	 * Run the preconditions every LAP variant route enforces
	 *
	 * Every variant route applies this identically — if one route guards its
	 * variant, they all do. A legacy page addressed on the modern route is the
	 * same mistake as the reverse, and both fail silently if unguarded: the
	 * write persists under keys that template never reads and returns success.
	 *
	 * The route is treated as the caller's declaration of variant. A page
	 * carrying no LAP meta has no variant of its own to contradict that
	 * declaration, so the write is allowed — this is what lets a page be created
	 * and populated in one pass. The site's own variant is the only check
	 * applied in that case, and only when it is unambiguous: if the rest of the
	 * site is plainly one variant and the caller asked for the other, the caller
	 * is on the wrong route and is told so.
	 *
	 * Reads are always permitted on a page with no meta — there is nothing to
	 * get wrong, and returning empty fields is honest.
	 *
	 * @since  1.7.0
	 * @param  int  $page_id  Post ID being addressed.
	 * @param  bool $is_write Whether the request will write.
	 * @return true|WP_Error True when the request may proceed.
	 */
	protected function guard_request( int $page_id, bool $is_write ) {
		$validation = $this->validate_lap_page( $page_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! $this->is_metabox_available() ) {
			return $this->metabox_unavailable_error();
		}

		$route_variant = $this->get_route_variant();
		$page_variant  = $this->detector->detect_page( $page_id );

		if ( Speakeasy_LAP_Variant_Detector::VARIANT_AMBIGUOUS === $page_variant ) {
			return $this->ambiguous_variant_error( $page_id );
		}

		if ( Speakeasy_LAP_Variant_Detector::VARIANT_UNDETERMINED === $page_variant ) {
			if ( ! $is_write ) {
				return true;
			}

			return $this->guard_undetermined_write( $page_id, $route_variant );
		}

		if ( $page_variant !== $route_variant ) {
			return $this->variant_mismatch_error( $page_id, $page_variant, 'page' );
		}

		return true;
	}

	/**
	 * Decide whether a write to a page with no LAP meta may proceed
	 *
	 * Refuses only when the site's variant is unambiguous and contradicts the
	 * route. A mixed site provides no majority evidence either way, and a site
	 * with no other identifiable LAP pages provides none at all — in both cases
	 * the route's declaration stands, since refusing would make it impossible to
	 * populate the first LAP page on a site.
	 *
	 * @since  1.7.0
	 * @param  int    $page_id       Post ID being written to.
	 * @param  string $route_variant Variant this route serves.
	 * @return true|WP_Error True when the write may proceed.
	 */
	private function guard_undetermined_write( int $page_id, string $route_variant ) {
		$site = $this->detector->detect_site();

		$site_is_determinate = in_array(
			$site['variant'],
			array(
				Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1,
				Speakeasy_LAP_Variant_Detector::VARIANT_MODERN,
			),
			true
		);

		if ( $site_is_determinate && ! $site['mixed'] && $site['variant'] !== $route_variant ) {
			return $this->variant_mismatch_error( $page_id, $site['variant'], 'site' );
		}

		return true;
	}

	/**
	 * Build a variant mismatch error naming the route the caller should use
	 *
	 * @since  1.7.0
	 * @param  int    $page_id          Post ID being addressed.
	 * @param  string $detected_variant Variant that contradicts the route.
	 * @param  string $source           'page' when the page's own meta decided it,
	 *                                  'site' when the surrounding site did.
	 * @return WP_Error
	 */
	protected function variant_mismatch_error( int $page_id, string $detected_variant, string $source ): WP_Error {
		$message = 'page' === $source
			? 'This page uses the %1$s LAP field set. Use %2$s instead.'
			: 'This page has no LAP meta yet and this site uses the %1$s LAP field set. Use %2$s instead.';

		return new WP_Error(
			'variant_mismatch',
			sprintf(
				$message,
				$detected_variant,
				$this->route_for_variant( $detected_variant, $page_id )
			),
			array(
				'status'           => 400,
				'detected_variant' => $detected_variant,
				'route_variant'    => $this->get_route_variant(),
				'detected_from'    => $source,
			)
		);
	}

	/**
	 * Build the ambiguous-variant error, naming the actual conflict
	 *
	 * @since  1.7.0
	 * @param  int $page_id Post ID being addressed.
	 * @return WP_Error
	 */
	protected function ambiguous_variant_error( int $page_id ): WP_Error {
		return new WP_Error(
			'ambiguous_field_variant',
			'This page carries both legacy and modern LAP meta. Resolve it manually before writing via the API.',
			array(
				'status'  => 400,
				'markers' => $this->detector->get_present_markers( $page_id ),
			)
		);
	}

	/**
	 * Map a variant to the route that serves it
	 *
	 * @since  1.7.0
	 * @param  string $variant Variant identifier.
	 * @param  int    $page_id Post ID being addressed.
	 * @return string Route path, for use in error messages.
	 */
	private function route_for_variant( string $variant, int $page_id ): string {
		if ( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 === $variant ) {
			return self::NAMESPACE . '/lap-meta/legacy_v1/' . $page_id;
		}

		return self::NAMESPACE . '/lap-meta/' . $page_id;
	}
}
