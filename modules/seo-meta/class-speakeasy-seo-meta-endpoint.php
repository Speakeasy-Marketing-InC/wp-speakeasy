<?php
/**
 * SEO Meta Fields REST Endpoint
 *
 * Provides REST API endpoint to write SEO meta fields (title, description)
 * for all major SEO plugins (Yoast SEO, RankMath, AIOSEO, SEOPress).
 * Works on any WordPress page or post, regardless of template.
 *
 * @package WP_Speakeasy
 * @since   1.4.0
 */

/**
 * Class Speakeasy_SEO_Meta_Endpoint
 *
 * Registers POST endpoint at speakeasy/v1/seo-meta/{page_id}.
 * Writes SEO meta to all four major SEO plugins simultaneously to ensure
 * compatibility regardless of which plugin is active.
 *
 * Authentication uses the same X-Speakeasy-API-Key header as all other
 * speakeasy/v1 endpoints.
 *
 * @since 1.4.0
 */
class Speakeasy_SEO_Meta_Endpoint {


	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'speakeasy/v1';

	/**
	 * Plugin API key
	 *
	 * @var string|null
	 */
	private $api_key;

	/**
	 * Constructor
	 *
	 * @since 1.4.0
	 * @param string|null $api_key Plugin API key. Loaded from options when null.
	 */
	public function __construct( $api_key = null ) {
		$this->api_key = $api_key ?? get_option( 'speakeasy_api_key' );
	}

	/**
	 * Register REST routes
	 *
	 * @since  1.4.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/seo-meta/(?P<page_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_seo_meta' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
				'args'                => array(
					'page_id'         => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'seo_title'       => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'seo_description' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Verify API key from request header
	 *
	 * Uses timing-safe comparison to prevent timing attacks.
	 *
	 * @since  1.4.0
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
	 * Handle POST request — update SEO meta fields for the page/post
	 *
	 * Writes meta for all four major SEO plugins (Yoast, RankMath, AIOSEO, SEOPress).
	 * Only fields present in the request body are written.
	 *
	 * @since  1.4.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Response on success, WP_Error on failure.
	 */
	public function update_seo_meta( $request ) {
		$page_id         = absint( $request->get_param( 'page_id' ) );
		$seo_title       = $request->get_param( 'seo_title' );
		$seo_description = $request->get_param( 'seo_description' );

		// Validate page exists.
		$validation = $this->validate_post_exists( $page_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Require at least one field.
		if ( empty( $seo_title ) && empty( $seo_description ) ) {
			return new WP_Error(
				'missing_fields',
				'At least one of seo_title or seo_description is required',
				array( 'status' => 400 )
			);
		}

		$updated = array();

		// Update SEO title for all major plugins.
		if ( ! empty( $seo_title ) ) {
			$this->update_seo_title( $page_id, $seo_title );
			$updated[] = 'seo_title';
		}

		// Update SEO description for all major plugins.
		if ( ! empty( $seo_description ) ) {
			$this->update_seo_description( $page_id, $seo_description );
			$updated[] = 'seo_description';
		}

		return rest_ensure_response(
			array(
				'page_id' => $page_id,
				'updated' => $updated,
			)
		);
	}

	/**
	 * Validate that a post exists
	 *
	 * @since  1.4.0
	 * @param  int $page_id Post ID to validate.
	 * @return true|WP_Error True on success, WP_Error with 'page_not_found' on failure.
	 */
	private function validate_post_exists( int $page_id ) {
		$post = get_post( $page_id );

		if ( ! $post ) {
			return new WP_Error(
				'page_not_found',
				'Page not found',
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/**
	 * Update SEO title meta for all major SEO plugins
	 *
	 * Writes to Yoast SEO, RankMath, AIOSEO, and SEOPress.
	 * AIOSEO stores title as JSON object, others store as plain string.
	 *
	 * @since  1.4.0
	 * @param  int    $page_id   Post ID.
	 * @param  string $seo_title Sanitized SEO title.
	 * @return void
	 */
	private function update_seo_title( int $page_id, string $seo_title ): void {
		// Yoast SEO.
		update_post_meta( $page_id, '_yoast_wpseo_title', $seo_title );

		// RankMath.
		update_post_meta( $page_id, 'rank_math_title', $seo_title );

		// AIOSEO stores as JSON object.
		update_post_meta(
			$page_id,
			'_aioseo_title',
			wp_json_encode( array( 'title' => $seo_title ) )
		);

		// SEOPress.
		update_post_meta( $page_id, '_seopress_titles_title', $seo_title );
	}

	/**
	 * Update SEO description meta for all major SEO plugins
	 *
	 * Writes to Yoast SEO, RankMath, AIOSEO, and SEOPress.
	 * AIOSEO stores description as JSON object, others store as plain string.
	 *
	 * @since  1.4.0
	 * @param  int    $page_id         Post ID.
	 * @param  string $seo_description Sanitized SEO description.
	 * @return void
	 */
	private function update_seo_description( int $page_id, string $seo_description ): void {
		// Yoast SEO.
		update_post_meta( $page_id, '_yoast_wpseo_metadesc', $seo_description );

		// RankMath.
		update_post_meta( $page_id, 'rank_math_description', $seo_description );

		// AIOSEO stores as JSON object.
		update_post_meta(
			$page_id,
			'_aioseo_description',
			wp_json_encode( array( 'description' => $seo_description ) )
		);

		// SEOPress.
		update_post_meta( $page_id, '_seopress_titles_desc', $seo_description );
	}
}
