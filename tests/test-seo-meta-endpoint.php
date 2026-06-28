<?php
/**
 * Tests for Speakeasy_SEO_Meta_Endpoint
 *
 * @package WP_Speakeasy
 * @since   1.4.0
 */

/**
 * Test case for SEO Meta Fields REST endpoint
 *
 * @since 1.4.0
 */
class Test_SEO_Meta_Endpoint extends WP_UnitTestCase {


	/**
	 * Test API key
	 *
	 * @var string
	 */
	private $api_key = 'test_api_key_seo_meta';

	/**
	 * Test page post ID
	 *
	 * @var int
	 */
	private $page_id;

	/**
	 * Test post post ID
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up test environment
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( 'speakeasy_api_key', $this->api_key );

		// Create a test page.
		$this->page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page',
			)
		);

		// Create a test post.
		$this->post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			)
		);

		// Register routes.
		$endpoint = new Speakeasy_SEO_Meta_Endpoint( $this->api_key );
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
	 * Test POST returns 401 with missing API key
	 *
	 * @return void
	 */
	public function test_post_missing_api_key_returns_401() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_body_params( array( 'seo_title' => 'Test' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'missing_api_key', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 401 with invalid API key
	 *
	 * @return void
	 */
	public function test_post_invalid_api_key_returns_401() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', 'wrong_key' );
		$request->set_body_params( array( 'seo_title' => 'Test' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'invalid_api_key', $response->get_data()['code'] );
	}

	/**
	 * Test POST returns 500 when API key not configured
	 *
	 * @return void
	 */
	public function test_post_no_api_key_configured_returns_500() {
		delete_option( 'speakeasy_api_key' );

		// Re-register routes with no API key.
		$endpoint = new Speakeasy_SEO_Meta_Endpoint( null );
		$endpoint->register_routes();

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', 'any_key' );
		$request->set_body_params( array( 'seo_title' => 'Test' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 500, $response->get_status() );
		$this->assertEquals( 'api_key_not_configured', $response->get_data()['code'] );

		// Restore API key for subsequent tests.
		update_option( 'speakeasy_api_key', $this->api_key );
	}

	// -------------------------------------------------------------------
	// Page validation tests
	// -------------------------------------------------------------------

	/**
	 * Test POST returns 404 for non-existent page
	 *
	 * @return void
	 */
	public function test_post_nonexistent_page_returns_404() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/99999' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'seo_title' => 'Test' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'page_not_found', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// Input validation tests
	// -------------------------------------------------------------------

	/**
	 * Test POST returns 400 when neither seo_title nor seo_description provided
	 *
	 * @return void
	 */
	public function test_post_missing_both_fields_returns_400() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array() );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'missing_fields', $response->get_data()['code'] );
	}

	// -------------------------------------------------------------------
	// Happy path tests
	// -------------------------------------------------------------------

	/**
	 * Test POST with both title and description returns 200
	 *
	 * @return void
	 */
	public function test_post_both_fields_returns_200() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'seo_title'       => 'Test SEO Title',
				'seo_description' => 'Test SEO description',
			)
		);
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $this->page_id, $data['page_id'] );
		$this->assertContains( 'seo_title', $data['updated'] );
		$this->assertContains( 'seo_description', $data['updated'] );
	}

	/**
	 * Test POST with only seo_title returns 200
	 *
	 * @return void
	 */
	public function test_post_only_title_returns_200() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'seo_title' => 'Only Title' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertContains( 'seo_title', $data['updated'] );
		$this->assertNotContains( 'seo_description', $data['updated'] );
	}

	/**
	 * Test POST with only seo_description returns 200
	 *
	 * @return void
	 */
	public function test_post_only_description_returns_200() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'seo_description' => 'Only description' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertContains( 'seo_description', $data['updated'] );
		$this->assertNotContains( 'seo_title', $data['updated'] );
	}

	/**
	 * Test POST works on page post type
	 *
	 * @return void
	 */
	public function test_post_works_on_pages() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'seo_title' => 'Page Title' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test POST works on post post type
	 *
	 * @return void
	 */
	public function test_post_works_on_posts() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->post_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'seo_title' => 'Post Title' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	// -------------------------------------------------------------------
	// Data persistence tests
	// -------------------------------------------------------------------

	/**
	 * Test POST writes to all Yoast SEO meta keys
	 *
	 * @return void
	 */
	public function test_post_writes_yoast_meta_keys() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'seo_title'       => 'Yoast Title',
				'seo_description' => 'Yoast Description',
			)
		);
		rest_do_request( $request );

		$this->assertEquals( 'Yoast Title', get_post_meta( $this->page_id, '_yoast_wpseo_title', true ) );
		$this->assertEquals( 'Yoast Description', get_post_meta( $this->page_id, '_yoast_wpseo_metadesc', true ) );
	}

	/**
	 * Test POST writes to all RankMath meta keys
	 *
	 * @return void
	 */
	public function test_post_writes_rankmath_meta_keys() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'seo_title'       => 'RankMath Title',
				'seo_description' => 'RankMath Description',
			)
		);
		rest_do_request( $request );

		$this->assertEquals( 'RankMath Title', get_post_meta( $this->page_id, 'rank_math_title', true ) );
		$this->assertEquals( 'RankMath Description', get_post_meta( $this->page_id, 'rank_math_description', true ) );
	}

	/**
	 * Test POST writes to all AIOSEO meta keys in JSON format
	 *
	 * @return void
	 */
	public function test_post_writes_aioseo_meta_keys_as_json() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'seo_title'       => 'AIOSEO Title',
				'seo_description' => 'AIOSEO Description',
			)
		);
		rest_do_request( $request );

		$title_json = get_post_meta( $this->page_id, '_aioseo_title', true );
		$desc_json  = get_post_meta( $this->page_id, '_aioseo_description', true );

		// AIOSEO stores as JSON objects.
		$title_decoded = json_decode( $title_json, true );
		$desc_decoded  = json_decode( $desc_json, true );

		$this->assertEquals( 'AIOSEO Title', $title_decoded['title'] );
		$this->assertEquals( 'AIOSEO Description', $desc_decoded['description'] );
	}

	/**
	 * Test POST writes to all SEOPress meta keys
	 *
	 * @return void
	 */
	public function test_post_writes_seopress_meta_keys() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'seo_title'       => 'SEOPress Title',
				'seo_description' => 'SEOPress Description',
			)
		);
		rest_do_request( $request );

		$this->assertEquals( 'SEOPress Title', get_post_meta( $this->page_id, '_seopress_titles_title', true ) );
		$this->assertEquals( 'SEOPress Description', get_post_meta( $this->page_id, '_seopress_titles_desc', true ) );
	}

	/**
	 * Test POST writes to all 8 meta keys (4 plugins × 2 fields)
	 *
	 * @return void
	 */
	public function test_post_writes_all_eight_meta_keys() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'seo_title'       => 'All Plugins Title',
				'seo_description' => 'All Plugins Description',
			)
		);
		rest_do_request( $request );

		// Yoast.
		$this->assertNotEmpty( get_post_meta( $this->page_id, '_yoast_wpseo_title', true ) );
		$this->assertNotEmpty( get_post_meta( $this->page_id, '_yoast_wpseo_metadesc', true ) );

		// RankMath.
		$this->assertNotEmpty( get_post_meta( $this->page_id, 'rank_math_title', true ) );
		$this->assertNotEmpty( get_post_meta( $this->page_id, 'rank_math_description', true ) );

		// AIOSEO.
		$this->assertNotEmpty( get_post_meta( $this->page_id, '_aioseo_title', true ) );
		$this->assertNotEmpty( get_post_meta( $this->page_id, '_aioseo_description', true ) );

		// SEOPress.
		$this->assertNotEmpty( get_post_meta( $this->page_id, '_seopress_titles_title', true ) );
		$this->assertNotEmpty( get_post_meta( $this->page_id, '_seopress_titles_desc', true ) );
	}

	// -------------------------------------------------------------------
	// Sanitization tests
	// -------------------------------------------------------------------

	/**
	 * Test POST sanitizes HTML in seo_title
	 *
	 * @return void
	 */
	public function test_post_sanitizes_title_html() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'seo_title' => '<script>alert("xss")</script>Title' ) );
		rest_do_request( $request );

		$yoast_title = get_post_meta( $this->page_id, '_yoast_wpseo_title', true );

		// sanitize_text_field() strips all HTML tags.
		$this->assertStringNotContainsString( '<script>', $yoast_title );
		$this->assertStringNotContainsString( '</script>', $yoast_title );
	}

	/**
	 * Test POST sanitizes HTML in seo_description
	 *
	 * @return void
	 */
	public function test_post_sanitizes_description_html() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/seo-meta/' . $this->page_id );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array( 'seo_description' => '<strong>Bold</strong> description' ) );
		rest_do_request( $request );

		$yoast_desc = get_post_meta( $this->page_id, '_yoast_wpseo_metadesc', true );

		// sanitize_textarea_field() strips tags but preserves newlines.
		$this->assertStringNotContainsString( '<strong>', $yoast_desc );
		$this->assertStringNotContainsString( '</strong>', $yoast_desc );
	}
}
