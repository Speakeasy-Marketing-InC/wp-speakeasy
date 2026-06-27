<?php
/**
 * LAP Meta Fields REST Endpoint
 *
 * Provides REST API endpoints to read and write Local Area Page meta fields
 * using the Meta Box API, bypassing register_meta schema concerns.
 *
 * @package WP_Speakeasy
 * @since   1.3.0
 */

/**
 * Class Speakeasy_LAP_Meta_Endpoint
 *
 * Registers GET and POST endpoints at speakeasy/v1/lap-meta/{page_id}.
 * Reads and writes via rwmb_meta() / rwmb_set_meta() so Meta Box handles
 * its own serialization for group/clone fields.
 *
 * Authentication uses the same X-Speakeasy-API-Key header as all other
 * speakeasy/v1 endpoints.
 *
 * @since 1.3.0
 */
class Speakeasy_LAP_Meta_Endpoint {


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
	private $api_key;

	/**
	 * Allowed field keys and their validation rules
	 *
	 * @var array<string, array>
	 */
	private $fields;

	/**
	 * Constructor
	 *
	 * @since 1.3.0
	 * @param string|null $api_key Plugin API key. Loaded from options when null.
	 */
	public function __construct( $api_key = null ) {
		$this->api_key = $api_key ?? get_option( 'speakeasy_api_key' );
		$this->fields  = $this->define_fields();
	}

	/**
	 * Define the allowed field keys and their validation rules
	 *
	 * @since  1.3.0
	 * @return array<string, array>
	 */
	private function define_fields(): array {
		return array(
			'spk_main_heading'                      => array( 'type' => 'string' ),
			'spk_upload_video_image'                => array( 'type' => 'array' ),
			'spk_hide_video_image'                  => array( 'type' => 'boolean' ),
			'spk_video_section_left_text'           => array( 'type' => 'string' ),
			'spk_video_code'                        => array( 'type' => 'string' ),
			'spk_select_video'                      => array(
				'type' => 'string',
				'enum' => array( 'Youtube', 'Vimeo', 'Image' ),
			),
			'spk_gridbox_repeater'                  => array( 'type' => 'array' ),
			'spk_upload_call_to_action_phone_image' => array( 'type' => 'array' ),
			'spk_call_to_action_box_text'           => array( 'type' => 'string' ),
			'spk_add_phone_number'                  => array( 'type' => 'array' ),
			'spk_show_map_section'                  => array( 'type' => 'boolean' ),
			'spk_cta_bg_color'                      => array( 'type' => 'string' ),
			'spk_cta_bg_hvr_color'                  => array( 'type' => 'string' ),
			'spk_heading_hide'                      => array( 'type' => 'boolean' ),
			'spk_hide_banner_image'                 => array( 'type' => 'boolean' ),
		);
	}

	/**
	 * Register REST routes
	 *
	 * @since  1.3.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lap-meta/(?P<page_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_fields' ),
					'permission_callback' => array( $this, 'verify_api_key' ),
					'args'                => array(
						'page_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_fields' ),
					'permission_callback' => array( $this, 'verify_api_key' ),
					'args'                => array(
						'page_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
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
	 * Handle GET request — return all LAP field values for the page
	 *
	 * @since  1.3.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Response on success, WP_Error on failure.
	 */
	public function get_fields( $request ) {
		$page_id = absint( $request->get_param( 'page_id' ) );

		$validation = $this->validate_lap_page( $page_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! $this->is_metabox_available() ) {
			return new WP_Error(
				'metabox_unavailable',
				'Meta Box plugin is not active on this site',
				array( 'status' => 503 )
			);
		}

		$fields = array();
		foreach ( array_keys( $this->fields ) as $field_key ) {
			$fields[ $field_key ] = rwmb_meta( $field_key, array( 'object_type' => 'post' ), $page_id );
		}

		return rest_ensure_response(
			array(
				'page_id' => $page_id,
				'fields'  => $fields,
			)
		);
	}

	/**
	 * Handle POST request — partially update LAP field values for the page
	 *
	 * Only fields present in the request body are written.
	 * Omitted fields are left unchanged.
	 *
	 * @since  1.3.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Response on success, WP_Error on failure.
	 */
	public function update_fields( $request ) {
		$page_id = absint( $request->get_param( 'page_id' ) );

		$validation = $this->validate_lap_page( $page_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! $this->is_metabox_available() ) {
			return new WP_Error(
				'metabox_unavailable',
				'Meta Box plugin is not active on this site',
				array( 'status' => 503 )
			);
		}

		// get_json_params() handles Content-Type: application/json bodies.
		// Fall back to get_body_params() for form-encoded requests.
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			$body = $request->get_body_params();
		}

		// Reject unknown keys before writing anything.
		foreach ( array_keys( $body ) as $key ) {
			if ( ! array_key_exists( $key, $this->fields ) ) {
				return new WP_Error(
					'unknown_field',
					sprintf( 'Unknown field: %s', sanitize_key( $key ) ),
					array( 'status' => 400 )
				);
			}
		}

		// Validate enum fields.
		foreach ( $body as $key => $value ) {
			$rule = $this->fields[ $key ];
			if ( isset( $rule['enum'] ) && ! in_array( $value, $rule['enum'], true ) ) {
				return new WP_Error(
					'invalid_field_value',
					sprintf(
						'Invalid value for %s. Allowed: %s',
						sanitize_key( $key ),
						implode( ', ', $rule['enum'] )
					),
					array( 'status' => 400 )
				);
			}
		}

		$updated = array();
		foreach ( $body as $field_key => $value ) {
			rwmb_set_meta( $page_id, $field_key, $value );
			$updated[] = $field_key;
		}

		return rest_ensure_response(
			array(
				'page_id' => $page_id,
				'updated' => $updated,
			)
		);
	}

	/**
	 * Validate that a page exists and uses the LAP template
	 *
	 * @since  1.3.0
	 * @param  int $page_id Post ID to validate.
	 * @return true|WP_Error True on success, WP_Error with 'page_not_found' or 'not_lap_page' on failure.
	 */
	private function validate_lap_page( int $page_id ) {
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
	private function is_metabox_available(): bool {
		$available = function_exists( 'rwmb_meta' ) && function_exists( 'rwmb_set_meta' );

		/**
	* This filter is documented in modules/lap-meta/class-lap-meta-endpoint.php
*/
		return (bool) apply_filters( 'speakeasy_metabox_available', $available );
	}
}
