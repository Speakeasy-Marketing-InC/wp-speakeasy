<?php
/**
 * Tests for Speakeasy_LAP_Meta_Endpoint
 *
 * @package WP_Speakeasy
 * @since   1.3.0
 */

/**
 * Test case for LAP Meta Fields REST endpoint
 *
 * @since 1.3.0
 */
class Test_LAP_Meta_Endpoint extends WP_UnitTestCase {


	/**
	 * Test API key
	 *
	 * @var string
	 */
	private $api_key = 'test_api_key_lap_meta';

	/**
	 * LAP page post ID
	 *
	 * @var int
	 */
	private $lap_page_id;

	/**
	 * Non-LAP page post ID
	 *
	 * @var int
	 */
	private $regular_page_id;

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( 'speakeasy_api_key', $this->api_key );

		// Create a LAP page with the correct template.
		$this->lap_page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test LAP Page',
			)
		);
		update_post_meta( $this->lap_page_id, '_wp_page_template', 'localareapage.php' );

		// Create a regular page without the template.
		$this->regular_page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Regular Page',
			)
		);

		// Register routes.
		$endpoint = new Speakeasy_LAP_Meta_Endpoint( $this->api_key );
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

	// -------------------------------------------------------------------
	// Auth tests
	// -------------------------------------------------------------------

	/**
	 * Test GET returns 401 with missing API key
	 *
	 * @return void
	 */
	public function test_get_missing_api_key_returns_401() {
		$request  = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'missing_api_key', $response->get_data()['code'] );
	}

	/**
	 * Test GET returns 401 with invalid API key
	 *
	 * @return void
	 */
	public function test_get_invalid_api_key_returns_401() {
		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', 'wrong_key' );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'invalid_api_key', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 401 with missing API key
	 *
	 * @return void
	 */
	public function test_post_missing_api_key_returns_401() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_body_params( array( 'spk_main_heading' => 'Test' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'missing_api_key', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// Page validation tests
	// -------------------------------------------------------------------

	/**
	 * Test GET returns 404 for non-existent page
	 *
	 * @return void
	 */
	public function test_get_nonexistent_page_returns_404() {
		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/99999' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'page_not_found', $response->get_data()['code'] );
	}

	/**
	 * Test GET returns 400 for page not using localareapage.php template
	 *
	 * @return void
	 */
	public function test_get_non_lap_page_returns_400() {
		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $this->regular_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'not_lap_page', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 404 for non-existent page
	 *
	 * @return void
	 */
	public function test_post_nonexistent_page_returns_404() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/99999' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Test' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'page_not_found', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 400 for page not using localareapage.php template
	 *
	 * @return void
	 */
	public function test_post_non_lap_page_returns_400() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->regular_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Test' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'not_lap_page', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// Meta Box availability tests
	// -------------------------------------------------------------------

	/**
	 * Test GET returns 503 when Meta Box is unavailable
	 *
	 * @return void
	 */
	public function test_get_returns_503_when_metabox_unavailable() {
		// Simulate Meta Box being absent by using a page with meta box check disabled.
		// We test this by temporarily filtering the endpoint's metabox check.
		add_filter( 'speakeasy_metabox_available', '__return_false' );

		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		remove_filter( 'speakeasy_metabox_available', '__return_false' );

		$this->assertEquals( 503, $response->get_status() );
		$this->assertEquals( 'metabox_unavailable', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 503 when Meta Box is unavailable
	 *
	 * @return void
	 */
	public function test_post_returns_503_when_metabox_unavailable() {
		add_filter( 'speakeasy_metabox_available', '__return_false' );

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Test' ) );
		$response = rest_do_request( $request );

		remove_filter( 'speakeasy_metabox_available', '__return_false' );

		$this->assertEquals( 503, $response->get_status() );
		$this->assertEquals( 'metabox_unavailable', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// GET happy path tests
	// -------------------------------------------------------------------

	/**
	 * Test GET returns 200 with all field keys present for valid LAP page
	 *
	 * @return void
	 */
	public function test_get_returns_all_field_keys() {
		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data   = $response->get_data();
		$fields = $data['fields'];

		$expected_keys = array(
			'spk_main_heading',
			'spk_upload_video_image',
			'spk_hide_video_image',
			'spk_video_section_left_text',
			'spk_video_code',
			'spk_select_video',
			'spk_gridbox_repeater',
			'spk_upload_call_to_action_phone_image',
			'spk_call_to_action_box_text',
			'spk_add_phone_number',
			'spk_show_map_section',
			'spk_cta_bg_color',
			'spk_cta_bg_hvr_color',
			'spk_heading_hide',
			'spk_hide_banner_image',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $fields, "Missing field: {$key}" );
		}

		$this->assertEquals( $this->lap_page_id, $data['page_id'] );
	}

	// -------------------------------------------------------------------
	// POST validation tests
	// -------------------------------------------------------------------

	/**
	 * Test POST returns 400 for unknown field key
	 *
	 * @return void
	 */
	public function test_post_unknown_field_returns_400() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_nonexistent_field' => 'value' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'unknown_field', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 400 for invalid spk_select_video value
	 *
	 * @return void
	 */
	public function test_post_invalid_select_video_returns_400() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_select_video' => 'TikTok' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_field_value', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 400 for valid enum values
	 *
	 * @return void
	 */
	public function test_post_valid_select_video_values_accepted() {
		foreach ( array( 'Youtube', 'Vimeo', 'Image' ) as $value ) {
			$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
			$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
			$request->set_body_params( array( 'spk_select_video' => $value ) );
			$response = rest_do_request( $request );

			$this->assertEquals( 200, $response->get_status(), "Value '{$value}' should be accepted" );
		}
	}

	// -------------------------------------------------------------------
	// POST happy path tests
	// -------------------------------------------------------------------

	/**
	 * Test POST returns 200 and reports updated fields
	 *
	 * @return void
	 */
	public function test_post_returns_updated_field_list() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'spk_main_heading' => 'Hello World',
				'spk_cta_bg_color' => '#ff0000',
			)
		);
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $this->lap_page_id, $data['page_id'] );
		$this->assertContains( 'spk_main_heading', $data['updated'] );
		$this->assertContains( 'spk_cta_bg_color', $data['updated'] );
	}

	/**
	 * Test POST only updates provided fields and leaves others unchanged
	 *
	 * @return void
	 */
	public function test_post_partial_update_does_not_modify_other_fields() {
		// Pre-set a value on a field we will NOT include in the update.
		update_post_meta( $this->lap_page_id, 'spk_video_code', 'original-video-code' );

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Updated Heading' ) );
		rest_do_request( $request );

		// The untouched field should still have its original value.
		$this->assertEquals( 'original-video-code', get_post_meta( $this->lap_page_id, 'spk_video_code', true ) );
	}

	/**
	 * Test POST actually persists field values
	 *
	 * @return void
	 */
	public function test_post_persists_text_field_value() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Persisted Heading' ) );
		rest_do_request( $request );

		$this->assertEquals( 'Persisted Heading', get_post_meta( $this->lap_page_id, 'spk_main_heading', true ) );
	}

	/**
	 * Test GET reflects values written by POST
	 *
	 * @return void
	 */
	public function test_get_reflects_written_values() {
		// Write a value.
		$write_request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$write_request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$write_request->set_body_params( array( 'spk_main_heading' => 'Round Trip Value' ) );
		rest_do_request( $write_request );

		// Read it back.
		$read_request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$read_request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $read_request );

		$this->assertEquals( 'Round Trip Value', $response->get_data()['fields']['spk_main_heading'] );
	}
}
