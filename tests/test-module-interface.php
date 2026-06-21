<?php
/**
 * Tests for Speakeasy_Module interface
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for module interface
 */
class Test_Module_Interface extends WP_UnitTestCase {

	/**
	 * Test that interface exists
	 */
	public function test_interface_exists() {
		$this->assertTrue( interface_exists( 'Speakeasy_Module' ) );
	}

	/**
	 * Test that interface has required methods
	 */
	public function test_interface_has_required_methods() {
		$reflection   = new ReflectionClass( 'Speakeasy_Module' );
		$methods      = $reflection->getMethods();
		$method_names = array_map(
			function ( $method ) {
				return $method->getName();
			},
			$methods
		);

		$this->assertContains( 'get_name', $method_names );
		$this->assertContains( 'get_description', $method_names );
		$this->assertContains( 'get_version', $method_names );
		$this->assertContains( 'init', $method_names );
		$this->assertContains( 'is_enabled', $method_names );
		$this->assertContains( 'get_priority', $method_names );
	}

	/**
	 * Test that concrete implementation must define all methods
	 */
	public function test_concrete_class_must_implement_all_methods() {
		// Create a test module that implements the interface.
		$test_module = new class() implements Speakeasy_Module {
			public function get_name(): string {
				return 'Test Module';
			}

			public function get_description(): string {
				return 'Test module description';
			}

			public function get_version(): string {
				return '1.0.0';
			}

			public function init(): void {
				// Test implementation.
			}

			public function is_enabled(): bool {
				return true;
			}

			public function get_priority(): int {
				return 10;
			}
		};

		$this->assertInstanceOf( Speakeasy_Module::class, $test_module );
		$this->assertEquals( 'Test Module', $test_module->get_name() );
		$this->assertEquals( 'Test module description', $test_module->get_description() );
		$this->assertEquals( '1.0.0', $test_module->get_version() );
		$this->assertTrue( $test_module->is_enabled() );
		$this->assertEquals( 10, $test_module->get_priority() );
	}
}
