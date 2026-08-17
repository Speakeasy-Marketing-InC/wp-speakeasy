<?php
/**
 * LAP Meta Fields REST Endpoint — legacy_v1 variant
 *
 * Reads and writes Local Area Page meta for sites running the legacy LAP
 * plugin, using that plugin's own meta keys and value shapes.
 *
 * @package WP_Speakeasy
 * @since   1.6.0
 */

/**
 * Class Speakeasy_LAP_Meta_Legacy_V1_Endpoint
 *
 * Registers GET and POST at speakeasy/v1/lap-meta/legacy_v1/{page_id}.
 *
 * Deliberately does not translate legacy keys into modern ones. The two
 * variants differ in shape as well as spelling — the legacy phone number is a
 * string where the modern one is a repeater, and legacy's three fixed content
 * blocks have no counterpart to the modern gridbox repeater — so a normalizing
 * layer would need per-field conversion with gaps in both directions. Each
 * route speaks its own variant's native vocabulary instead. See MEMORY.md § 6.
 *
 * @since 1.6.0
 */
class Speakeasy_LAP_Meta_Legacy_V1_Endpoint extends Speakeasy_LAP_Endpoint_Base {


	/**
	 * Variant this endpoint serves
	 *
	 * @var string
	 */
	const VARIANT = Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1;

	/**
	 * Allowed field keys, their types and access paths
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $fields;

	/**
	 * Constructor
	 *
	 * @since 1.6.0
	 * @param string|null                         $api_key  Plugin API key. Loaded from options when null.
	 * @param Speakeasy_LAP_Variant_Detector|null $detector Detector instance. Created when null.
	 */
	public function __construct( $api_key = null, $detector = null ) {
		parent::__construct( $api_key, $detector );

		$this->fields = require __DIR__ . '/schemas/localareapage-legacy-v1.php';
	}

	/**
	 * The LAP plugin variant this endpoint's route serves
	 *
	 * @since  1.7.0
	 * @return string
	 */
	protected function get_route_variant(): string {
		return self::VARIANT;
	}

	/**
	 * Register REST routes
	 *
	 * Nested under lap-meta rather than suffixed onto it. The modern route's
	 * page_id pattern requires digits, so 'legacy_v1' cannot collide with it.
	 *
	 * @since  1.6.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lap-meta/legacy_v1/(?P<page_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_fields' ),
					'permission_callback' => array( $this, 'verify_api_key' ),
					'args'                => $this->page_id_arg(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_fields' ),
					'permission_callback' => array( $this, 'verify_api_key' ),
					'args'                => $this->page_id_arg(),
				),
			)
		);
	}

	/**
	 * Handle GET request — return all legacy_v1 field values for the page
	 *
	 * @since  1.6.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Response on success, WP_Error on failure.
	 */
	public function get_fields( $request ) {
		$page_id = absint( $request->get_param( 'page_id' ) );

		$guard = $this->guard_request( $page_id, false );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$fields = array();
		foreach ( $this->fields as $field_key => $definition ) {
			$fields[ $field_key ] = $this->read_field( $page_id, $field_key, $definition );
		}

		return rest_ensure_response(
			array(
				'page_id' => $page_id,
				'variant' => self::VARIANT,
				'fields'  => $fields,
			)
		);
	}

	/**
	 * Handle POST request — partially update legacy_v1 field values
	 *
	 * Only fields present in the request body are written. Omitted fields are
	 * left unchanged.
	 *
	 * @since  1.6.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Response on success, WP_Error on failure.
	 */
	public function update_fields( $request ) {
		$page_id = absint( $request->get_param( 'page_id' ) );

		$guard = $this->guard_request( $page_id, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		// get_json_params() handles Content-Type: application/json bodies.
		// Fall back to get_body_params() for form-encoded requests.
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			$body = $request->get_body_params();
		}

		$validation = $this->validate_body( $body );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$updated = array();
		$failed  = array();

		foreach ( $body as $field_key => $value ) {
			$definition = $this->fields[ $field_key ];
			$value      = $this->normalize_value( $value, $definition );

			$this->write_field( $page_id, $field_key, $value, $definition );

			if ( $this->write_failed_to_persist( $page_id, $field_key, $value, $definition ) ) {
				$failed[] = $field_key;
				continue;
			}

			$updated[] = $field_key;
		}

		return rest_ensure_response(
			array(
				'page_id' => $page_id,
				'variant' => self::VARIANT,
				'updated' => $updated,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * Reject unknown keys and invalid enum values before writing anything
	 *
	 * @since  1.6.0
	 * @param  array<string, mixed> $body Request body.
	 * @return true|WP_Error True when the body is acceptable.
	 */
	private function validate_body( array $body ) {
		foreach ( array_keys( $body ) as $key ) {
			if ( ! array_key_exists( $key, $this->fields ) ) {
				return new WP_Error(
					'unknown_field',
					sprintf( 'Unknown field: %s', sanitize_key( $key ) ),
					array( 'status' => 400 )
				);
			}
		}

		foreach ( $body as $key => $value ) {
			$definition = $this->fields[ $key ];

			if ( isset( $definition['enum'] ) && ! in_array( $value, $definition['enum'], true ) ) {
				return new WP_Error(
					'invalid_field_value',
					sprintf(
						'Invalid value for %s. Allowed: %s',
						sanitize_key( $key ),
						implode( ', ', $definition['enum'] )
					),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Read one field through the access path the legacy template uses
	 *
	 * @since  1.6.0
	 * @param  int                  $page_id    Post ID to read from.
	 * @param  string               $field_key  Meta key.
	 * @param  array<string, mixed> $definition Field definition from the schema file.
	 * @return mixed
	 */
	private function read_field( int $page_id, string $field_key, array $definition ) {
		if ( 'post_meta' === $definition['read'] ) {
			// Bare attachment ID, exactly as the template reads it before
			// handing it to wp_get_attachment_url(). Unset reads back as 0.
			return (int) get_post_meta( $page_id, $field_key, true );
		}

		return rwmb_meta( $field_key, array( 'object_type' => 'post' ), $page_id );
	}

	/**
	 * Write one field through the access path the legacy template reads
	 *
	 * Image fields go through update_post_meta rather than rwmb_set_meta so the
	 * stored value is a bare attachment ID. Writing them through Meta Box risks
	 * storing a shape the legacy template cannot read — a write that persists
	 * but renders nothing.
	 *
	 * @since  1.6.0
	 * @param  int                  $page_id    Post ID to write to.
	 * @param  string               $field_key  Meta key.
	 * @param  mixed                $value      Normalized value.
	 * @param  array<string, mixed> $definition Field definition from the schema file.
	 * @return void
	 */
	private function write_field( int $page_id, string $field_key, $value, array $definition ): void {
		if ( 'post_meta' === $definition['read'] ) {
			update_post_meta( $page_id, $field_key, $value );
			return;
		}

		rwmb_set_meta( $page_id, $field_key, $value );
	}

	/**
	 * Coerce an incoming value to the shape the legacy template expects
	 *
	 * Booleans become 1/0 because the template truthiness-checks them
	 * (`if ($mapsection)`) and Meta Box checkboxes store them that way; a JSON
	 * `false` written verbatim would persist as an empty string and read back
	 * ambiguously. Image fields become integers because the template feeds them
	 * straight to wp_get_attachment_url().
	 *
	 * String fields pass through unchanged: several hold HTML the site relies on
	 * — the WYSIWYG content blocks, and spk_mapiframe which holds a map embed —
	 * and running them through wp_kses_post() here would strip that markup and
	 * silently damage live pages. This matches the modern endpoint's behavior;
	 * both are gated on the API key.
	 *
	 * @since  1.6.0
	 * @param  mixed                $value      Raw value from the request body.
	 * @param  array<string, mixed> $definition Field definition from the schema file.
	 * @return mixed
	 */
	private function normalize_value( $value, array $definition ) {
		if ( 'boolean' === $definition['type'] ) {
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		}

		if ( 'integer' === $definition['type'] ) {
			return absint( $value );
		}

		return $value;
	}

	/**
	 * Check whether a write did not actually persist
	 *
	 * Neither rwmb_set_meta() nor update_post_meta() reliably reports a failed
	 * write — update_post_meta() also returns false when the value was already
	 * identical. Reading the value back through the same path the template uses
	 * is the check that matters: a non-empty write that reads back empty did not
	 * land. Empty writes are never flagged, since there is no round trip to
	 * verify against.
	 *
	 * @since  1.6.0
	 * @param  int                  $page_id    Post ID the field was written to.
	 * @param  string               $field_key  Meta key.
	 * @param  mixed                $value      Value that was requested to be written.
	 * @param  array<string, mixed> $definition Field definition from the schema file.
	 * @return bool True if the value failed to persist.
	 */
	private function write_failed_to_persist( int $page_id, string $field_key, $value, array $definition ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		$persisted = $this->read_field( $page_id, $field_key, $definition );

		return empty( $persisted );
	}
}
