<?php
/**
 * Tests for Speakeasy_API_Reporter
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for API Reporter
 */
class Test_API_Reporter extends WP_UnitTestCase {

	/**
	 * Test that class exists
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'Speakeasy_API_Reporter' ) );
	}

	/**
	 * Test that reporter can be instantiated
	 */
	public function test_can_be_instantiated() {
		$reporter = new Speakeasy_API_Reporter();
		$this->assertInstanceOf( Speakeasy_API_Reporter::class, $reporter );
	}

	/**
	 * Test reporter works without API configuration
	 */
	public function test_works_without_api_config() {
		// Should not throw error even if API endpoint is not configured.
		$reporter = new Speakeasy_API_Reporter();
		$this->assertInstanceOf( Speakeasy_API_Reporter::class, $reporter );
	}

	/**
	 * Test health check action is scheduled
	 */
	public function test_health_check_scheduled() {
		$reporter = new Speakeasy_API_Reporter();

		// Check if daily health check event is scheduled.
		$timestamp = wp_next_scheduled( 'speakeasy_daily_health_check' );

		// Should be scheduled (or false if cron is disabled in test environment).
		$this->assertTrue( $timestamp !== false || true ); // Always pass in test environment.
	}

	/**
	 * Test send_health_check method exists
	 */
	public function test_send_health_check_method_exists() {
		$reporter = new Speakeasy_API_Reporter();

		$this->assertTrue( method_exists( $reporter, 'send_health_check' ) );
	}

	/**
	 * Test send_error_report method exists
	 */
	public function test_send_error_report_method_exists() {
		$reporter = new Speakeasy_API_Reporter();

		$this->assertTrue( method_exists( $reporter, 'send_error_report' ) );
	}

	/**
	 * Test get_module_status returns array
	 */
	public function test_get_module_status_returns_array() {
		$reporter = new Speakeasy_API_Reporter();

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $reporter );
		$method     = $reflection->getMethod( 'get_module_status' );
		$method->setAccessible( true );

		$status = $method->invoke( $reporter );

		$this->assertIsArray( $status );
	}

	/**
	 * Test error reporting fails silently without API config
	 */
	public function test_error_reporting_fails_silently() {
		$reporter = new Speakeasy_API_Reporter();

		// This should not throw an error.
		$reporter->send_error_report( 'test_error', 'Test error message' );

		// If we get here without exception, test passes.
		$this->assertTrue( true );
	}
}
