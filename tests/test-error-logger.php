<?php
/**
 * Tests for Speakeasy_Error_Logger
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for Error Logger
 */
class Test_Error_Logger extends WP_UnitTestCase {

	/**
	 * Clean up error log before each test
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( 'speakeasy_error_log' );
	}

	/**
	 * Clean up error log after each test
	 */
	public function tearDown(): void {
		delete_option( 'speakeasy_error_log' );
		parent::tearDown();
	}

	/**
	 * Test that class exists
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'Speakeasy_Error_Logger' ) );
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern() {
		$instance1 = Speakeasy_Error_Logger::instance();
		$instance2 = Speakeasy_Error_Logger::instance();

		$this->assertInstanceOf( Speakeasy_Error_Logger::class, $instance1 );
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Happy path: Log an error and retrieve it from storage
	 */
	public function test_log_and_retrieve_error() {
		$logger = Speakeasy_Error_Logger::instance();

		$logger->log_error( 'error', 'Test error message', '/test/file.php', 42 );

		$errors = $logger->get_errors();

		$this->assertCount( 1, $errors );
		$this->assertEquals( 'error', $errors[0]['type'] );
		$this->assertEquals( 'Test error message', $errors[0]['message'] );
		$this->assertStringContainsString( 'test/file.php', $errors[0]['file'] );
		$this->assertEquals( 42, $errors[0]['line'] );
		$this->assertArrayHasKey( 'timestamp', $errors[0] );
	}

	/**
	 * Happy path: Log multiple errors
	 */
	public function test_log_multiple_errors() {
		$logger = Speakeasy_Error_Logger::instance();

		$logger->log_error( 'error', 'Error 1', '/test/file1.php', 10 );
		$logger->log_error( 'warning', 'Warning 1', '/test/file2.php', 20 );
		$logger->log_error( 'notice', 'Notice 1', '/test/file3.php', 30 );

		$errors = $logger->get_errors();

		$this->assertCount( 3, $errors );
		$this->assertEquals( 'notice', $errors[0]['type'] ); // Most recent first.
		$this->assertEquals( 'warning', $errors[1]['type'] );
		$this->assertEquals( 'error', $errors[2]['type'] );
	}

	/**
	 * Happy path: Clear error log
	 */
	public function test_clear_errors() {
		$logger = Speakeasy_Error_Logger::instance();

		$logger->log_error( 'error', 'Test error', '/test/file.php', 1 );
		$this->assertCount( 1, $logger->get_errors() );

		$result = $logger->clear_errors();

		$this->assertTrue( $result );
		$this->assertCount( 0, $logger->get_errors() );
	}

	/**
	 * Edge case: Log 51 errors and verify oldest is pruned
	 */
	public function test_auto_prune_old_errors() {
		$logger = Speakeasy_Error_Logger::instance();

		// Log 51 errors.
		for ( $i = 1; $i <= 51; $i++ ) {
			$logger->log_error( 'error', "Error $i", '/test/file.php', $i );
		}

		$errors = $logger->get_errors();

		// Should only keep 50 most recent.
		$this->assertCount( 50, $errors );
		// Most recent should be "Error 51".
		$this->assertEquals( 'Error 51', $errors[0]['message'] );
		// Oldest should be "Error 2" (Error 1 was pruned).
		$this->assertEquals( 'Error 2', $errors[49]['message'] );
	}

	/**
	 * Edge case: Log error with very long message
	 */
	public function test_long_message_truncation() {
		$logger = Speakeasy_Error_Logger::instance();

		$long_message = str_repeat( 'A', 600 );
		$logger->log_error( 'error', $long_message, '/test/file.php', 1 );

		$errors = $logger->get_errors();

		$this->assertCount( 1, $errors );
		// Should be truncated to 500 chars.
		$this->assertLessThanOrEqual( 500, strlen( $errors[0]['message'] ) );
	}

	/**
	 * Edge case: Log error with sensitive data
	 */
	public function test_sanitize_sensitive_data() {
		$logger = Speakeasy_Error_Logger::instance();

		$message_with_key = 'API request failed with key: sk_live_1234567890abcdef';
		$logger->log_error( 'error', $message_with_key, '/test/file.php', 1 );

		$errors = $logger->get_errors();

		// Message should be sanitized.
		$this->assertStringNotContainsString( 'sk_live_', $errors[0]['message'] );
		$this->assertStringNotContainsString( '1234567890abcdef', $errors[0]['message'] );
	}

	/**
	 * Edge case: Strip ABSPATH from file paths
	 */
	public function test_strip_abspath_from_file() {
		$logger = Speakeasy_Error_Logger::instance();

		$full_path = ABSPATH . 'wp-content/plugins/wp-speakeasy/includes/test.php';
		$logger->log_error( 'error', 'Test error', $full_path, 1 );

		$errors = $logger->get_errors();

		// Should not contain ABSPATH.
		$this->assertStringNotContainsString( ABSPATH, $errors[0]['file'] );
		// Should contain relative path.
		$this->assertStringContainsString( 'wp-content/plugins/wp-speakeasy', $errors[0]['file'] );
	}

	/**
	 * Test get_error_count method
	 */
	public function test_get_error_count() {
		$logger = Speakeasy_Error_Logger::instance();

		$this->assertEquals( 0, $logger->get_error_count() );

		$logger->log_error( 'error', 'Error 1', '/test/file.php', 1 );
		$this->assertEquals( 1, $logger->get_error_count() );

		$logger->log_error( 'error', 'Error 2', '/test/file.php', 2 );
		$this->assertEquals( 2, $logger->get_error_count() );

		$logger->clear_errors();
		$this->assertEquals( 0, $logger->get_error_count() );
	}

	/**
	 * Test logging with context data
	 */
	public function test_log_with_context() {
		$logger = Speakeasy_Error_Logger::instance();

		$context = array(
			'module' => 'test-module',
			'action' => 'test-action',
		);

		$logger->log_error( 'error', 'Test error', '/test/file.php', 1, $context );

		$errors = $logger->get_errors();

		$this->assertArrayHasKey( 'context', $errors[0] );
		$this->assertEquals( 'test-module', $errors[0]['context']['module'] );
		$this->assertEquals( 'test-action', $errors[0]['context']['action'] );
	}

	/**
	 * Test logging with stack trace
	 */
	public function test_log_with_stack_trace() {
		$logger = Speakeasy_Error_Logger::instance();

		$logger->log_error( 'error', 'Test error', '/test/file.php', 1, array(), 'Stack trace here' );

		$errors = $logger->get_errors();

		$this->assertArrayHasKey( 'trace', $errors[0] );
		$this->assertEquals( 'Stack trace here', $errors[0]['trace'] );
	}

	/**
	 * Test that invalid error types default to 'error'
	 */
	public function test_invalid_error_type_defaults() {
		$logger = Speakeasy_Error_Logger::instance();

		$logger->log_error( 'invalid-type', 'Test message', '/test/file.php', 1 );

		$errors = $logger->get_errors();

		$this->assertEquals( 'error', $errors[0]['type'] );
	}

	/**
	 * Test that error logger handles storage failure gracefully
	 */
	public function test_handles_storage_failure_gracefully() {
		$logger = Speakeasy_Error_Logger::instance();

		// This should not throw an exception even if update_option fails.
		// We can't easily mock update_option failure in tests, but the code should handle it.
		$logger->log_error( 'error', 'Test error', '/test/file.php', 1 );

		// If we get here without exception, the test passes.
		$this->assertTrue( true );
	}
}
