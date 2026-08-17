<?php
/**
 * Tests for Speakeasy_LAP_Meta_Legacy_V1_Endpoint
 *
 * @package WP_Speakeasy
 * @since   1.6.0
 */

/**
 * Test case for the legacy_v1 LAP meta REST endpoint
 *
 * @since 1.6.0
 */
class Test_LAP_Meta_Legacy_V1_Endpoint extends WP_UnitTestCase {


	/**
	 * Test API key
	 *
	 * @var string
	 */
	private $api_key = 'test_api_key_lap_legacy_v1';

	/**
	 * Legacy LAP page post ID
	 *
	 * @var int
	 */
	private $legacy_page_id;

	/**
	 * Route prefix under test
	 *
	 * @var string
	 */
	private $route = '/speakeasy/v1/lap-meta/legacy_v1/';

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( 'speakeasy_api_key', $this->api_key );

		$this->legacy_page_id = $this->create_lap_page();
		// Marker that makes the page detectably legacy_v1.
		update_post_meta( $this->legacy_page_id, 'spk_mainheading', 'Legacy Heading' );

		$endpoint = new Speakeasy_LAP_Meta_Legacy_V1_Endpoint( $this->api_key );
		$endpoint->register_routes();
	}

	/**
	 * Tear down test environment
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'speakeasy_api_key' );

		parent::tearDown();
	}

	/**
	 * Create a page using the LAP template
	 *
	 * @return int Post ID.
	 */
	private function create_lap_page(): int {
		$page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $page_id, '_wp_page_template', 'localareapage.php' );

		return $page_id;
	}

	/**
	 * Build an authenticated request against the legacy route
	 *
	 * @param  string $method  HTTP method.
	 * @param  int    $page_id Target page ID.
	 * @return WP_REST_Request
	 */
	private function request( string $method, int $page_id ): WP_REST_Request {
		$request = new WP_REST_Request( $method, $this->route . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );

		return $request;
	}

	// -------------------------------------------------------------------
	// Auth
	// -------------------------------------------------------------------

	/**
	 * Test GET rejects a missing API key
	 *
	 * @return void
	 */
	public function test_get_missing_api_key_returns_401() {
		$request  = new WP_REST_Request( 'GET', $this->route . $this->legacy_page_id );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'missing_api_key', $response->get_data()['code'] );
	}

	/**
	 * Test POST rejects an invalid API key
	 *
	 * @return void
	 */
	public function test_post_invalid_api_key_returns_401() {
		$request = new WP_REST_Request( 'POST', $this->route . $this->legacy_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', 'wrong_key' );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'invalid_api_key', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// Page validation
	// -------------------------------------------------------------------

	/**
	 * Test GET returns 404 for a missing page
	 *
	 * @return void
	 */
	public function test_get_missing_page_returns_404() {
		$response = rest_do_request( $this->request( 'GET', 999999 ) );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'page_not_found', $response->get_data()['code'] );
	}

	/**
	 * Test GET rejects a page that does not use the LAP template
	 *
	 * @return void
	 */
	public function test_get_non_lap_page_returns_not_lap_page() {
		$page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$response = rest_do_request( $this->request( 'GET', $page_id ) );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'not_lap_page', $response->get_data()['code'] );
	}

	/**
	 * Test both methods return 503 when Meta Box is unavailable
	 *
	 * @return void
	 */
	public function test_returns_503_when_metabox_unavailable() {
		add_filter( 'speakeasy_metabox_available', '__return_false' );

		$response = rest_do_request( $this->request( 'GET', $this->legacy_page_id ) );

		remove_filter( 'speakeasy_metabox_available', '__return_false' );

		$this->assertEquals( 503, $response->get_status() );
		$this->assertEquals( 'metabox_unavailable', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// Variant guards
	// -------------------------------------------------------------------

	/**
	 * Test GET rejects a modern page addressed on the legacy route
	 *
	 * @return void
	 */
	public function test_get_modern_page_returns_variant_mismatch() {
		$page_id = $this->create_lap_page();
		update_post_meta( $page_id, 'spk_main_heading', 'Modern Heading' );

		$response = rest_do_request( $this->request( 'GET', $page_id ) );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'variant_mismatch', $response->get_data()['code'] );
	}

	/**
	 * Test POST refuses to write to a modern page on the legacy route
	 *
	 * @return void
	 */
	public function test_post_modern_page_writes_nothing() {
		$page_id = $this->create_lap_page();
		update_post_meta( $page_id, 'spk_main_heading', 'Modern Heading' );

		$request = $this->request( 'POST', $page_id );
		$request->set_body_params( array( 'spk_mainheading' => 'Should Not Land' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'variant_mismatch', $response->get_data()['code'] );
		$this->assertEmpty( get_post_meta( $page_id, 'spk_mainheading', true ) );
	}

	/**
	 * Test a page carrying both key styles is rejected on GET
	 *
	 * @return void
	 */
	public function test_get_ambiguous_page_returns_error_naming_markers() {
		update_post_meta( $this->legacy_page_id, 'spk_main_heading', 'Modern Heading' );

		$response = rest_do_request( $this->request( 'GET', $this->legacy_page_id ) );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'ambiguous_field_variant', $data['code'] );
		// The error names the conflict rather than just reporting one exists.
		$this->assertContains(
			'spk_mainheading',
			$data['data']['markers'][ Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 ]
		);
		$this->assertContains(
			'spk_main_heading',
			$data['data']['markers'][ Speakeasy_LAP_Variant_Detector::VARIANT_MODERN ]
		);
	}

	/**
	 * Test POST refuses to write to an ambiguous page
	 *
	 * @return void
	 */
	public function test_post_ambiguous_page_writes_nothing() {
		update_post_meta( $this->legacy_page_id, 'spk_main_heading', 'Modern Heading' );

		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_calltoactiontext' => 'Should Not Land' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'ambiguous_field_variant', $response->get_data()['code'] );
		$this->assertEmpty( get_post_meta( $this->legacy_page_id, 'spk_calltoactiontext', true ) );
	}

	/**
	 * Test POST refuses to write to a page whose variant cannot be determined
	 *
	 * Guessing a key style for a blank page is exactly the silent failure this
	 * endpoint exists to prevent.
	 *
	 * @return void
	 */
	public function test_post_undetermined_page_writes_nothing() {
		$page_id = $this->create_lap_page();

		$request = $this->request( 'POST', $page_id );
		$request->set_body_params( array( 'spk_mainheading' => 'Should Not Land' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'variant_undetermined', $response->get_data()['code'] );
		$this->assertEmpty( get_post_meta( $page_id, 'spk_mainheading', true ) );
	}

	/**
	 * Test GET is allowed on a page whose variant cannot be determined
	 *
	 * An empty page has no markers by definition; returning empty fields is
	 * honest, and reading cannot damage anything.
	 *
	 * @return void
	 */
	public function test_get_undetermined_page_is_allowed() {
		$page_id = $this->create_lap_page();

		$response = rest_do_request( $this->request( 'GET', $page_id ) );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data()['fields']['spk_mainheading'] );
	}

	// -------------------------------------------------------------------
	// GET
	// -------------------------------------------------------------------

	/**
	 * Test GET returns the full legacy field set and identifies the variant
	 *
	 * @return void
	 */
	public function test_get_returns_all_legacy_fields() {
		$response = rest_do_request( $this->request( 'GET', $this->legacy_page_id ) );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'legacy_v1', $data['variant'] );
		$this->assertCount( 26, $data['fields'] );

		// Legacy key spellings, not modern ones.
		$this->assertArrayHasKey( 'spk_mainheading', $data['fields'] );
		$this->assertArrayHasKey( 'spk_calltoactionnumber', $data['fields'] );
		$this->assertArrayHasKey( 'spk_bottomsectioncontent3', $data['fields'] );
		$this->assertArrayNotHasKey( 'spk_main_heading', $data['fields'] );
		$this->assertArrayNotHasKey( 'spk_gridbox_repeater', $data['fields'] );
	}

	/**
	 * Test GET returns image fields as bare attachment IDs
	 *
	 * The legacy template reads these with get_post_meta() and passes the result
	 * straight to wp_get_attachment_url(), so anything other than a bare ID
	 * renders nothing.
	 *
	 * @return void
	 */
	public function test_get_returns_image_fields_as_bare_attachment_ids() {
		update_post_meta( $this->legacy_page_id, 'spk_bannerbgimg', 4321 );

		$response = rest_do_request( $this->request( 'GET', $this->legacy_page_id ) );
		$value    = $response->get_data()['fields']['spk_bannerbgimg'];

		$this->assertIsInt( $value );
		$this->assertEquals( 4321, $value );
		$this->assertIsNotArray( $value );
	}

	// -------------------------------------------------------------------
	// POST
	// -------------------------------------------------------------------

	/**
	 * Test POST rejects an unknown field without writing anything
	 *
	 * @return void
	 */
	public function test_post_unknown_field_is_rejected() {
		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params(
			array(
				// A modern key is "unknown" on this route by design.
				'spk_main_heading' => 'Wrong Variant Key',
			)
		);
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'unknown_field', $response->get_data()['code'] );
		$this->assertEmpty( get_post_meta( $this->legacy_page_id, 'spk_main_heading', true ) );
	}

	/**
	 * Test POST rejects an out-of-enum value for spk_selectvideo
	 *
	 * @return void
	 */
	public function test_post_invalid_enum_value_is_rejected() {
		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_selectvideo' => 'Dailymotion' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_field_value', $response->get_data()['code'] );
	}

	/**
	 * Test POST accepts a valid enum value
	 *
	 * @return void
	 */
	public function test_post_valid_enum_value_is_accepted() {
		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_selectvideo' => 'Vimeo' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( 'spk_selectvideo', $response->get_data()['updated'] );
	}

	/**
	 * Test POST persists a text field under its legacy key
	 *
	 * @return void
	 */
	public function test_post_persists_text_field() {
		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_calltoactiontext' => 'Call us now' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( 'spk_calltoactiontext', $response->get_data()['updated'] );
		$this->assertEquals(
			'Call us now',
			get_post_meta( $this->legacy_page_id, 'spk_calltoactiontext', true )
		);
	}

	/**
	 * Test POST stores an image field as a bare attachment ID
	 *
	 * This is the assertion that guards against repeating the session 6 bug: a
	 * value that persists in a shape the legacy template cannot read.
	 *
	 * @return void
	 */
	public function test_post_stores_image_field_as_bare_attachment_id() {
		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_calltoactionimg' => 987 ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$stored = get_post_meta( $this->legacy_page_id, 'spk_calltoactionimg', true );
		$this->assertIsNotArray( $stored );
		$this->assertEquals( 987, (int) $stored );
	}

	/**
	 * Test POST normalizes booleans to the 1/0 the template truthiness-checks
	 *
	 * @return void
	 */
	public function test_post_normalizes_boolean_field() {
		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_mapsection' => true ) );
		rest_do_request( $request );

		$this->assertEquals( 1, (int) get_post_meta( $this->legacy_page_id, 'spk_mapsection', true ) );
	}

	/**
	 * Test POST leaves fields absent from the body unchanged
	 *
	 * @return void
	 */
	public function test_post_partial_update_leaves_other_fields_untouched() {
		update_post_meta( $this->legacy_page_id, 'spk_videocode', 'original-code' );

		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_calltoactiontext' => 'Updated' ) );
		rest_do_request( $request );

		$this->assertEquals(
			'original-code',
			get_post_meta( $this->legacy_page_id, 'spk_videocode', true )
		);
	}

	/**
	 * Test POST reports a field as failed when the write does not persist
	 *
	 * Short-circuits the underlying meta write so it reports success without
	 * storing anything — the session 6 silent-failure scenario.
	 *
	 * @return void
	 */
	public function test_post_reports_failed_when_write_does_not_persist() {
		add_filter( 'update_post_metadata', '__return_true', 20 );

		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_calltoactiontext' => 'Never Lands' ) );
		$response = rest_do_request( $request );

		remove_filter( 'update_post_metadata', '__return_true', 20 );

		$data = $response->get_data();
		$this->assertContains( 'spk_calltoactiontext', $data['failed'] );
		$this->assertNotContains( 'spk_calltoactiontext', $data['updated'] );
	}

	/**
	 * Test an empty value is not reported as a failed write
	 *
	 * There is no round trip to verify when nothing was sent.
	 *
	 * @return void
	 */
	public function test_post_empty_value_is_not_reported_as_failed() {
		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_mapfax' => '' ) );
		$response = rest_do_request( $request );

		$data = $response->get_data();
		$this->assertContains( 'spk_mapfax', $data['updated'] );
		$this->assertEmpty( $data['failed'] );
	}

	/**
	 * Test GET reflects values written by POST
	 *
	 * @return void
	 */
	public function test_get_reflects_written_values() {
		$write = $this->request( 'POST', $this->legacy_page_id );
		$write->set_body_params( array( 'spk_mapheading' => 'Find Our Office' ) );
		rest_do_request( $write );

		$response = rest_do_request( $this->request( 'GET', $this->legacy_page_id ) );

		$this->assertEquals( 'Find Our Office', $response->get_data()['fields']['spk_mapheading'] );
	}
}
