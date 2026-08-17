<?php
/**
 * Variant guard tests for Speakeasy_LAP_Meta_Endpoint
 *
 * @package WP_Speakeasy
 * @since   1.7.0
 */

/**
 * Test case for the modern LAP route's variant guards
 *
 * @since 1.7.0
 */
class Test_LAP_Meta_Guards extends WP_UnitTestCase {


	/**
	 * Test API key
	 *
	 * @var string
	 */
	private $api_key = 'test_api_key_lap_meta_guards';

	/**
	 * Blank LAP page post ID
	 *
	 * @var int
	 */
	private $lap_page_id;

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( 'speakeasy_api_key', $this->api_key );

		$this->lap_page_id = $this->create_lap_page();

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
	// Variant guards
	//
	// The modern route enforces the same guard as every other variant route:
	// a page whose own meta identifies a different variant is refused, and a
	// page with no meta is written on the route's say-so unless the site's
	// variant contradicts it. Without this, a legacy page addressed here
	// accepts writes that persist under keys its template never reads.
	// -------------------------------------------------------------------

	/**
	 * Create an additional page using the LAP template
	 *
	 * @param  string|null $variant Variant whose marker to seed, or null for none.
	 * @return int Post ID.
	 */
	private function create_lap_page( ?string $variant = null ): int {
		$page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $page_id, '_wp_page_template', 'localareapage.php' );

		if ( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 === $variant ) {
			update_post_meta( $page_id, 'spk_mainheading', 'Legacy Heading' );
		}

		if ( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN === $variant ) {
			update_post_meta( $page_id, 'spk_main_heading', 'Modern Heading' );
		}

		return $page_id;
	}

	/**
	 * Test GET rejects a legacy page addressed on the modern route
	 *
	 * @return void
	 */
	public function test_get_legacy_page_returns_variant_mismatch() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'variant_mismatch', $data['code'] );
		$this->assertEquals( 'page', $data['data']['detected_from'] );
	}

	/**
	 * Test POST refuses to write to a legacy page on the modern route
	 *
	 * Previously this returned 200 and wrote modern keys the legacy template
	 * never reads — a successful-looking write that changed nothing.
	 *
	 * @return void
	 */
	public function test_post_legacy_page_writes_nothing() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Should Not Land' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'variant_mismatch', $response->get_data()['code'] );
		$this->assertEmpty( get_post_meta( $page_id, 'spk_main_heading', true ) );
	}

	/**
	 * Test a page carrying both key styles is rejected on the modern route
	 *
	 * @return void
	 */
	public function test_post_ambiguous_page_writes_nothing() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		update_post_meta( $page_id, 'spk_main_heading', 'Modern Heading' );

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_cta_bg_color' => '#000000' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'ambiguous_field_variant', $response->get_data()['code'] );
		$this->assertEmpty( get_post_meta( $page_id, 'spk_cta_bg_color', true ) );
	}

	/**
	 * Test a fresh page on a modern site can be populated in one pass
	 *
	 * @return void
	 */
	public function test_post_undetermined_page_on_modern_site_proceeds() {
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN );

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Brand New Page' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			'Brand New Page',
			get_post_meta( $this->lap_page_id, 'spk_main_heading', true )
		);
	}

	/**
	 * Test a fresh page is refused when the site is plainly legacy
	 *
	 * @return void
	 */
	public function test_post_undetermined_page_on_legacy_site_is_refused() {
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'spk_main_heading' => 'Should Not Land' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'variant_mismatch', $data['code'] );
		$this->assertEquals( 'site', $data['data']['detected_from'] );
		$this->assertEmpty( get_post_meta( $this->lap_page_id, 'spk_main_heading', true ) );
	}

	/**
	 * Test the response identifies the variant the route serves
	 *
	 * @return void
	 */
	public function test_response_carries_variant() {
		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-meta/' . $this->lap_page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 'modern', $response->get_data()['variant'] );
	}
}
