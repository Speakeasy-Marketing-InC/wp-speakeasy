<?php
/**
 * Shared test case for the legacy_v1 LAP meta endpoint
 *
 * @package WP_Speakeasy
 * @since   1.7.0
 */

/**
 * Base case holding fixtures shared by the legacy_v1 endpoint suites
 *
 * Abstract, so PHPUnit does not collect it as a suite of its own.
 *
 * @since 1.7.0
 */
abstract class Legacy_V1_TestCase extends WP_UnitTestCase {


	/**
	 * Test API key
	 *
	 * @var string
	 */
	protected $api_key = 'test_api_key_lap_legacy_v1';

	/**
	 * Legacy LAP page post ID
	 *
	 * @var int
	 */
	protected $legacy_page_id;

	/**
	 * Route prefix under test
	 *
	 * @var string
	 */
	protected $route = '/speakeasy/v1/lap-meta/legacy_v1/';

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
	 * @param  string|null $variant Variant whose marker to seed, or null for none.
	 * @return int Post ID.
	 */
	protected function create_lap_page( ?string $variant = null ): int {
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
	 * Build an authenticated request against the legacy route
	 *
	 * @param  string $method  HTTP method.
	 * @param  int    $page_id Target page ID.
	 * @return WP_REST_Request
	 */
	protected function request( string $method, int $page_id ): WP_REST_Request {
		$request = new WP_REST_Request( $method, $this->route . $page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );

		return $request;
	}
}
