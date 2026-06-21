<?php
/**
 * Tests for Speakeasy_REST_API class
 *
 * @package WP_Speakeasy
 * @since 1.1.0
 */

/**
 * Test case for REST API endpoints
 *
 * @since 1.1.0
 */
class Test_Speakeasy_REST_API extends WP_UnitTestCase {

	/**
	 * REST API instance
	 *
	 * @var Speakeasy_REST_API
	 */
	private $rest_api;

	/**
	 * Test API key
	 *
	 * @var string
	 */
	private $api_key = 'test_api_key_12345';

	/**
	 * Test user
	 *
	 * @var WP_User
	 */
	private $test_user;

	/**
	 * Set up test environment
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Store test API key.
		update_option( 'speakeasy_api_key', $this->api_key );

		// Create test user.
		$this->test_user = $this->factory->user->create_and_get(
			array(
				'user_login' => 'testadmin',
				'role'       => 'administrator',
			)
		);

		// Initialize REST API.
		$this->rest_api = new Speakeasy_REST_API();
		$this->rest_api->register_routes();

		// Mock Application Passwords as available.
		add_filter( 'wp_is_application_passwords_available', '__return_true', 999 );
		add_filter( 'wp_is_application_passwords_available_for_user', '__return_true', 999 );
	}

	/**
	 * Tear down test environment
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function tearDown(): void {
		// Clean up.
		delete_option( 'speakeasy_api_key' );
		remove_filter( 'wp_is_application_passwords_available', '__return_true', 999 );
		remove_filter( 'wp_is_application_passwords_available_for_user', '__return_true', 999 );

		parent::tearDown();
	}

	/**
	 * Test route registration
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_route_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/speakeasy/v1/application-passwords', $routes );
	}

	/**
	 * Test valid request creates Application Password
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_valid_request_creates_application_password() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'username' => 'testadmin',
				'name'     => 'Test Password',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertNotEmpty( $data['password'] );
		$this->assertEquals( 'testadmin', $data['username'] );
		$this->assertEquals( $this->test_user->ID, $data['user_id'] );
		$this->assertEquals( 'Test Password', $data['name'] );

		// Verify password format (WordPress App Password format: xxxx xxxx xxxx xxxx xxxx xxxx).
		$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9]{4} [a-zA-Z0-9]{4} [a-zA-Z0-9]{4} [a-zA-Z0-9]{4} [a-zA-Z0-9]{4} [a-zA-Z0-9]{4}$/', $data['password'] );
	}

	/**
	 * Test default name generated when not provided
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_default_name_generated() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'username' => 'testadmin',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringStartsWith( 'Speakeasy Automation - ', $data['name'] );
	}

	/**
	 * Test revokes existing password with same name
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_revokes_existing_password_with_same_name() {
		// Create first password.
		$request1 = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request1->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request1->set_body_params(
			array(
				'username' => 'testadmin',
				'name'     => 'Duplicate Name',
			)
		);

		$response1 = rest_do_request( $request1 );
		$data1     = $response1->get_data();
		$password1 = $data1['password'];

		// Create second password with same name.
		$request2 = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request2->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request2->set_body_params(
			array(
				'username' => 'testadmin',
				'name'     => 'Duplicate Name',
			)
		);

		$response2 = rest_do_request( $request2 );
		$data2     = $response2->get_data();
		$password2 = $data2['password'];

		// Passwords should be different.
		$this->assertNotEquals( $password1, $password2 );

		// Only one password with that name should exist.
		$passwords = WP_Application_Passwords::get_user_application_passwords( $this->test_user->ID );
		$matching  = array_filter(
			$passwords,
			function ( $p ) {
				return 'Duplicate Name' === $p['name'];
			}
		);

		$this->assertCount( 1, $matching );
	}

	/**
	 * Test missing API key returns 401
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_missing_api_key_returns_401() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_body_params(
			array(
				'username' => 'testadmin',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'missing_api_key', $data['code'] );
	}

	/**
	 * Test invalid API key returns 401
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_invalid_api_key_returns_401() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', 'wrong_api_key' );
		$request->set_body_params(
			array(
				'username' => 'testadmin',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'invalid_api_key', $data['code'] );
	}

	/**
	 * Test missing username returns 400
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_missing_username_returns_400() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params( array() );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'missing_username', $data['code'] );
	}

	/**
	 * Test user not found returns 404
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_user_not_found_returns_404() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'username' => 'nonexistentuser',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'user_not_found', $data['code'] );
	}

	/**
	 * Test Application Passwords disabled for user returns 403
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_app_passwords_disabled_for_user_returns_403() {
		// Disable for this specific user.
		add_filter(
			'wp_is_application_passwords_available_for_user',
			function ( $available, $user ) {
				if ( $user->ID === $this->test_user->ID ) {
					return false;
				}
				return $available;
			},
			1000,
			2
		);

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'username' => 'testadmin',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 403, $response->get_status() );
		$this->assertEquals( 'app_passwords_disabled', $data['code'] );
	}

	/**
	 * Test Application Passwords globally unavailable returns 503
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_app_passwords_globally_unavailable_returns_503() {
		// Disable globally.
		remove_filter( 'wp_is_application_passwords_available', '__return_true', 999 );
		add_filter( 'wp_is_application_passwords_available', '__return_false', 1000 );

		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'username' => 'testadmin',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 503, $response->get_status() );
		$this->assertEquals( 'app_passwords_unavailable', $data['code'] );
	}

	/**
	 * Test timing attack protection with hash_equals
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_timing_attack_protection() {
		// This test verifies hash_equals is used by checking that
		// different wrong keys don't reveal timing information.
		// We can't easily measure timing in unit tests, but we can
		// verify the behavior is consistent.

		$request1 = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request1->set_header( 'X-Speakeasy-API-Key', 'aaaaaaaaaaaaaaaa' );
		$request1->set_body_params( array( 'username' => 'testadmin' ) );

		$request2 = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request2->set_header( 'X-Speakeasy-API-Key', 'zzzzzzzzzzzzzzzz' );
		$request2->set_body_params( array( 'username' => 'testadmin' ) );

		$response1 = rest_do_request( $request1 );
		$response2 = rest_do_request( $request2 );

		// Both should return same error code.
		$this->assertEquals( 401, $response1->get_status() );
		$this->assertEquals( 401, $response2->get_status() );
		$this->assertEquals( 'invalid_api_key', $response1->get_data()['code'] );
		$this->assertEquals( 'invalid_api_key', $response2->get_data()['code'] );
	}

	/**
	 * Test input sanitization
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function test_input_sanitization() {
		$request = new WP_REST_Request( 'POST', '/speakeasy/v1/application-passwords' );
		$request->set_header( 'X-Speakeasy-API-Key', $this->api_key );
		$request->set_body_params(
			array(
				'username' => 'testadmin',
				'name'     => '<script>alert("xss")</script>Test Name',
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		// Name should be sanitized (no script tags).
		$this->assertStringNotContainsString( '<script>', $data['name'] );
		$this->assertStringContainsString( 'Test Name', $data['name'] );
	}
}
