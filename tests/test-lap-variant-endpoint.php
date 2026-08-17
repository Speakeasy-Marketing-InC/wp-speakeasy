<?php
/**
 * Tests for Speakeasy_LAP_Variant_Detector and Speakeasy_LAP_Variant_Endpoint
 *
 * @package WP_Speakeasy
 * @since   1.6.0
 */

/**
 * Test case for LAP variant detection and its REST endpoint
 *
 * @since 1.6.0
 */
class Test_LAP_Variant_Endpoint extends WP_UnitTestCase {


	/**
	 * Test API key
	 *
	 * @var string
	 */
	private $api_key = 'test_api_key_lap_variant';

	/**
	 * Variant detector under test
	 *
	 * @var Speakeasy_LAP_Variant_Detector
	 */
	private $detector;

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( 'speakeasy_api_key', $this->api_key );

		$this->detector = new Speakeasy_LAP_Variant_Detector();

		$endpoint = new Speakeasy_LAP_Variant_Endpoint( $this->api_key );
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
	 * Create a LAP page, optionally seeded with a variant's marker key
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

	// -------------------------------------------------------------------
	// Per-page detection
	// -------------------------------------------------------------------

	/**
	 * Test a page with only legacy markers is detected as legacy_v1
	 *
	 * @return void
	 */
	public function test_page_with_legacy_markers_is_legacy_v1() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$this->assertEquals(
			Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1,
			$this->detector->detect_page( $page_id )
		);
	}

	/**
	 * Test a page with only modern markers is detected as modern
	 *
	 * @return void
	 */
	public function test_page_with_modern_markers_is_modern() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN );

		$this->assertEquals(
			Speakeasy_LAP_Variant_Detector::VARIANT_MODERN,
			$this->detector->detect_page( $page_id )
		);
	}

	/**
	 * Test a page carrying both key styles is detected as ambiguous
	 *
	 * @return void
	 */
	public function test_page_with_both_styles_is_ambiguous() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		update_post_meta( $page_id, 'spk_main_heading', 'Modern Heading' );

		$this->assertEquals(
			Speakeasy_LAP_Variant_Detector::VARIANT_AMBIGUOUS,
			$this->detector->detect_page( $page_id )
		);
	}

	/**
	 * Test a page with no LAP meta is detected as undetermined
	 *
	 * @return void
	 */
	public function test_page_with_no_markers_is_undetermined() {
		$page_id = $this->create_lap_page();

		$this->assertEquals(
			Speakeasy_LAP_Variant_Detector::VARIANT_UNDETERMINED,
			$this->detector->detect_page( $page_id )
		);
	}

	/**
	 * Test an empty marker value does not count as present
	 *
	 * A field that exists but was never filled in must not decide the variant.
	 *
	 * @return void
	 */
	public function test_empty_marker_value_does_not_determine_variant() {
		$page_id = $this->create_lap_page();
		update_post_meta( $page_id, 'spk_mainheading', '' );

		$this->assertEquals(
			Speakeasy_LAP_Variant_Detector::VARIANT_UNDETERMINED,
			$this->detector->detect_page( $page_id )
		);
	}

	// -------------------------------------------------------------------
	// Site-level detection
	// -------------------------------------------------------------------

	/**
	 * Test an all-legacy site reports legacy_v1 and is not mixed
	 *
	 * @return void
	 */
	public function test_all_legacy_site_is_not_mixed() {
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$result = $this->detector->detect_site();

		$this->assertEquals( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1, $result['variant'] );
		$this->assertFalse( $result['mixed'] );
		$this->assertEquals( 2, $result['counts'][ Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 ] );
		$this->assertEquals( 0, $result['counts'][ Speakeasy_LAP_Variant_Detector::VARIANT_MODERN ] );
		$this->assertEquals( 2, $result['total_lap_pages'] );
	}

	/**
	 * Test a site holding both variants is flagged as mixed
	 *
	 * @return void
	 */
	public function test_site_with_both_variants_is_mixed() {
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN );

		$result = $this->detector->detect_site();

		$this->assertTrue( $result['mixed'] );
		// Dominant variant wins the top-level verdict.
		$this->assertEquals( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1, $result['variant'] );
		$this->assertEquals( 2, $result['counts'][ Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 ] );
		$this->assertEquals( 1, $result['counts'][ Speakeasy_LAP_Variant_Detector::VARIANT_MODERN ] );
	}

	/**
	 * Test pages with no markers are counted as undetermined
	 *
	 * @return void
	 */
	public function test_site_counts_undetermined_pages() {
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		$this->create_lap_page();
		$this->create_lap_page();

		$result = $this->detector->detect_site();

		$this->assertEquals( 2, $result['counts'][ Speakeasy_LAP_Variant_Detector::VARIANT_UNDETERMINED ] );
		$this->assertEquals( 3, $result['total_lap_pages'] );
	}

	/**
	 * Test a site with no LAP pages returns undetermined without error
	 *
	 * @return void
	 */
	public function test_site_with_no_lap_pages() {
		$result = $this->detector->detect_site();

		$this->assertEquals( Speakeasy_LAP_Variant_Detector::VARIANT_UNDETERMINED, $result['variant'] );
		$this->assertFalse( $result['mixed'] );
		$this->assertEquals( 0, $result['total_lap_pages'] );
	}

	/**
	 * Test site detection cost does not scale with the number of LAP pages
	 *
	 * A per-page loop would degrade badly on sites with hundreds of local area
	 * pages, which is this plugin's normal case.
	 *
	 * @return void
	 */
	public function test_site_detection_query_count_is_constant() {
		global $wpdb;

		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$before_small = $wpdb->num_queries;
		$this->detector->detect_site();
		$small_site_queries = $wpdb->num_queries - $before_small;

		for ( $i = 0; $i < 8; $i++ ) {
			$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );
		}

		$before_large = $wpdb->num_queries;
		$this->detector->detect_site();
		$large_site_queries = $wpdb->num_queries - $before_large;

		$this->assertEquals( $small_site_queries, $large_site_queries );
	}

	// -------------------------------------------------------------------
	// Endpoint: auth
	// -------------------------------------------------------------------

	/**
	 * Test site-level route requires an API key
	 *
	 * @return void
	 */
	public function test_site_variant_missing_api_key_returns_401() {
		$request  = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-variant' );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'missing_api_key', $response->get_data()['code'] );
	}

	/**
	 * Test per-page route requires an API key
	 *
	 * @return void
	 */
	public function test_page_variant_invalid_api_key_returns_401() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-variant/' . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', 'wrong_key' );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'invalid_api_key', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// Endpoint: responses
	// -------------------------------------------------------------------

	/**
	 * Test site-level route returns the verdict payload
	 *
	 * @return void
	 */
	public function test_site_variant_returns_payload() {
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-variant' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1, $data['variant'] );
		$this->assertArrayHasKey( 'mixed', $data );
		$this->assertArrayHasKey( 'counts', $data );
		$this->assertEquals( 1, $data['total_lap_pages'] );
	}

	/**
	 * Test per-page route returns the verdict and the markers behind it
	 *
	 * @return void
	 */
	public function test_page_variant_returns_verdict_and_markers() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 );

		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-variant/' . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $page_id, $data['page_id'] );
		$this->assertEquals( Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1, $data['variant'] );
		$this->assertContains(
			'spk_mainheading',
			$data['markers'][ Speakeasy_LAP_Variant_Detector::VARIANT_LEGACY_V1 ]
		);
	}

	/**
	 * Test per-page route rejects a page that is not a LAP page
	 *
	 * @return void
	 */
	public function test_page_variant_rejects_non_lap_page() {
		$page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-variant/' . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'not_lap_page', $response->get_data()['code'] );
	}

	/**
	 * Test per-page route returns 404 for a missing page
	 *
	 * @return void
	 */
	public function test_page_variant_missing_page_returns_404() {
		$request = new WP_REST_Request( 'GET', '/speakeasy/v1/lap-variant/999999' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$response = rest_do_request( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'page_not_found', $response->get_data()['code'] );
	}
}
