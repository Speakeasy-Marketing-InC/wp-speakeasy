<?php
/**
 * LAP Variant Discovery REST Endpoint
 *
 * Reports which LAP plugin variant a site or an individual page uses, so
 * callers know which lap-meta route to address.
 *
 * @package WP_Speakeasy
 * @since   1.6.0
 */

/**
 * Class Speakeasy_LAP_Variant_Endpoint
 *
 * Registers:
 *   GET speakeasy/v1/lap-variant            — site-level verdict
 *   GET speakeasy/v1/lap-variant/{page_id}  — per-page verdict
 *
 * The site-level call answers the common case in one request. The per-page call
 * exists because a site part-way through a plugin migration can hold both
 * variants at once, which the site-level response flags via `mixed`.
 *
 * @since 1.6.0
 */
class Speakeasy_LAP_Variant_Endpoint extends Speakeasy_LAP_Endpoint_Base {


	/**
	 * The LAP plugin variant this endpoint's route serves
	 *
	 * Discovery reports on every variant rather than serving one, and never
	 * calls guard_request(). It reports no variant of its own.
	 *
	 * @since  1.7.0
	 * @return string
	 */
	protected function get_route_variant(): string {
		return Speakeasy_LAP_Variant_Detector::VARIANT_UNDETERMINED;
	}

	/**
	 * Register REST routes
	 *
	 * @since  1.6.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lap-variant',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site_variant' ),
					'permission_callback' => array( $this, 'verify_api_key' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/lap-variant/(?P<page_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_page_variant' ),
					'permission_callback' => array( $this, 'verify_api_key' ),
					'args'                => $this->page_id_arg(),
				),
			)
		);
	}

	/**
	 * Handle GET request — site-level variant verdict
	 *
	 * @since  1.6.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function get_site_variant( $request ) {
		unset( $request );

		return rest_ensure_response( $this->detector->detect_site() );
	}

	/**
	 * Handle GET request — per-page variant verdict
	 *
	 * @since  1.6.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Response on success, WP_Error on failure.
	 */
	public function get_page_variant( $request ) {
		$page_id = absint( $request->get_param( 'page_id' ) );

		$validation = $this->validate_lap_page( $page_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$variant = $this->detector->detect_page( $page_id );

		return rest_ensure_response(
			array(
				'page_id' => $page_id,
				'variant' => $variant,
				// Which marker keys drove the verdict — the difference between
				// "ambiguous" and an actionable report of what to clean up.
				'markers' => $this->detector->get_present_markers( $page_id ),
			)
		);
	}
}
