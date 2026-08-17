<?php
/**
 * Variant guard tests for Speakeasy_LAP_Meta_Legacy_V1_Endpoint
 *
 * @package WP_Speakeasy
 * @since   1.7.0
 */

/**
 * Test case for the legacy_v1 route's variant guards
 *
 * @since 1.7.0
 */
class Test_LAP_Meta_Legacy_V1_Guards extends Legacy_V1_TestCase {


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
	// Page variant contradicts the route
	// -------------------------------------------------------------------

	/**
	 * Test GET rejects a modern page addressed on the legacy route
	 *
	 * @return void
	 */
	public function test_get_modern_page_returns_variant_mismatch() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN );

		$response = rest_do_request( $this->request( 'GET', $page_id ) );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'variant_mismatch', $data['code'] );
		$this->assertEquals( 'page', $data['data']['detected_from'] );
	}

	/**
	 * Test POST refuses to write to a modern page on the legacy route
	 *
	 * @return void
	 */
	public function test_post_modern_page_writes_nothing() {
		$page_id = $this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN );

		$request = $this->request( 'POST', $page_id );
		$request->set_body_params( array( 'spk_mainheading' => 'Should Not Land' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'variant_mismatch', $response->get_data()['code'] );
		$this->assertEmpty( get_post_meta( $page_id, 'spk_mainheading', true ) );
	}

	// -------------------------------------------------------------------
	// Ambiguous pages
	// -------------------------------------------------------------------

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

	// -------------------------------------------------------------------
	// Pages with no LAP meta — the create-and-populate case
	// -------------------------------------------------------------------

	/**
	 * Test GET is allowed on a page with no LAP meta
	 *
	 * Nothing can be got wrong by reading, and returning empty fields is honest.
	 *
	 * @return void
	 */
	public function test_get_undetermined_page_is_allowed() {
		$page_id = $this->create_lap_page();

		$response = rest_do_request( $this->request( 'GET', $page_id ) );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data()['fields']['spk_mainheading'] );
	}

	/**
	 * Test a fresh page on a legacy site can be populated in one pass
	 *
	 * The route declares the variant; the site agrees. This is the create flow.
	 *
	 * @return void
	 */
	public function test_post_undetermined_page_on_legacy_site_proceeds() {
		// setUp's page already makes this site legacy-dominant.
		$page_id = $this->create_lap_page();

		$request = $this->request( 'POST', $page_id );
		$request->set_body_params( array( 'spk_mainheading' => 'Brand New Page' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( 'spk_mainheading', $response->get_data()['updated'] );
		$this->assertEquals( 'Brand New Page', get_post_meta( $page_id, 'spk_mainheading', true ) );
	}

	/**
	 * Test a fresh page is refused when the site is plainly the other variant
	 *
	 * The caller is on the wrong route and is told so, rather than writing keys
	 * this site's template will never read.
	 *
	 * @return void
	 */
	public function test_post_undetermined_page_on_modern_site_is_refused() {
		// Make the site unambiguously modern: strip setUp's legacy marker first.
		delete_post_meta( $this->legacy_page_id, 'spk_mainheading' );
		update_post_meta( $this->legacy_page_id, 'spk_main_heading', 'Modern Heading' );
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN );

		$page_id = $this->create_lap_page();

		$request = $this->request( 'POST', $page_id );
		$request->set_body_params( array( 'spk_mainheading' => 'Should Not Land' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'variant_mismatch', $data['code'] );
		// The site decided this, not the page.
		$this->assertEquals( 'site', $data['data']['detected_from'] );
		$this->assertEmpty( get_post_meta( $page_id, 'spk_mainheading', true ) );
	}

	/**
	 * Test a fresh page on a mixed site proceeds
	 *
	 * A mixed site offers no majority evidence either way, so the route's
	 * declaration stands.
	 *
	 * @return void
	 */
	public function test_post_undetermined_page_on_mixed_site_proceeds() {
		// setUp's page is legacy; add a modern one to make the site mixed.
		$this->create_lap_page( Speakeasy_LAP_Variant_Detector::VARIANT_MODERN );

		$page_id = $this->create_lap_page();

		$request = $this->request( 'POST', $page_id );
		$request->set_body_params( array( 'spk_mainheading' => 'Mixed Site Page' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Mixed Site Page', get_post_meta( $page_id, 'spk_mainheading', true ) );
	}

	/**
	 * Test the first LAP page on a site can be populated
	 *
	 * With no other LAP pages there is nothing to check the route against.
	 * Refusing here would make it impossible to bootstrap a site.
	 *
	 * @return void
	 */
	public function test_post_undetermined_page_on_site_with_no_other_lap_pages_proceeds() {
		// Strip setUp's marker so no page on the site identifies a variant.
		delete_post_meta( $this->legacy_page_id, 'spk_mainheading' );

		$request = $this->request( 'POST', $this->legacy_page_id );
		$request->set_body_params( array( 'spk_mainheading' => 'First Page On Site' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			'First Page On Site',
			get_post_meta( $this->legacy_page_id, 'spk_mainheading', true )
		);
	}
}
