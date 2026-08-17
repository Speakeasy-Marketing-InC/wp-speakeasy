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
class Speakeasy_LAP_Meta_Endpoint extends Speakeasy_LAP_Endpoint_Base {


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
		parent::__construct( $api_key );

		$this->fields = $this->define_fields();
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
			return $this->metabox_unavailable_error();
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
			return $this->metabox_unavailable_error();
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
		$failed  = array();
		foreach ( $body as $field_key => $value ) {
			rwmb_set_meta( $page_id, $field_key, $value );

			if ( $this->write_failed_to_persist( $page_id, $field_key, $value ) ) {
				$failed[] = $field_key;
				continue;
			}

			$updated[] = $field_key;
		}

		return rest_ensure_response(
			array(
				'page_id' => $page_id,
				'updated' => $updated,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * Check whether a write did not actually persist
	 *
	 * Rwmb_set_meta() gives no return value to check, so a Meta Box field-config
	 * mismatch (e.g. an image sub-field that isn't configured as array-producing)
	 * can silently drop a value while the endpoint still reports success. Reading
	 * the value back via rwmb_meta() catches that: a non-empty write that reads
	 * back empty did not persist. Legitimately empty input values are never
	 * flagged, since there's nothing to verify a round trip against.
	 *
	 * @since  1.5.0
	 * @param  int    $page_id   Post ID the field was written to.
	 * @param  string $field_key Meta Box field key.
	 * @param  mixed  $value     Value that was requested to be written.
	 * @return bool True if the value failed to persist.
	 */
	private function write_failed_to_persist( int $page_id, string $field_key, $value ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		$persisted = rwmb_meta( $field_key, array( 'object_type' => 'post' ), $page_id );

		return empty( $persisted );
	}
}
