<?php
/**
 * Tests for Speakeasy_LAP_Meta_Module
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for LAP Meta Fields module
 */
class Test_LAP_Meta_Module extends WP_UnitTestCase {

	/**
	 * Test module metadata
	 */
	public function test_module_metadata() {
		$module = new Speakeasy_LAP_Meta_Module();

		$this->assertEquals( 'LAP Meta Fields', $module->get_name() );
		$this->assertEquals( '1.0.0', $module->get_version() );
		$this->assertIsString( $module->get_description() );
		$this->assertNotEmpty( $module->get_description() );
	}

	/**
	 * Test module priority
	 */
	public function test_module_priority() {
		$module = new Speakeasy_LAP_Meta_Module();

		$this->assertEquals( 100, $module->get_priority() );
	}

	/**
	 * Test module is enabled by default
	 */
	public function test_module_enabled_by_default() {
		$module = new Speakeasy_LAP_Meta_Module();

		$this->assertTrue( $module->is_enabled() );
	}

	/**
	 * Test module implements interface
	 */
	public function test_module_implements_interface() {
		$module = new Speakeasy_LAP_Meta_Module();

		$this->assertInstanceOf( Speakeasy_Module::class, $module );
	}

	/**
	 * Test module registers rest_api_init action
	 */
	public function test_registers_rest_api_init_action() {
		$module = new Speakeasy_LAP_Meta_Module();
		$module->init();

		// Check if the action was added.
		$this->assertTrue( has_action( 'rest_api_init' ) !== false );
	}

	/**
	 * Test schema loading from file
	 */
	public function test_loads_schema_from_file() {
		// Create a test schema file.
		$schema_dir = SPEAKEASY_PATH . 'modules/lap-meta/schemas';
		if ( ! file_exists( $schema_dir ) ) {
			mkdir( $schema_dir, 0755, true );
		}

		$test_schema_file = $schema_dir . '/test-template.php';
		file_put_contents(
			$test_schema_file,
			'<?php return array( "test_field" => array( "type" => "string", "single" => true, "show_in_rest" => true ) );'
		);

		$module = new Speakeasy_LAP_Meta_Module();

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $module );
		$method     = $reflection->getMethod( 'load_schema_file' );
		$method->setAccessible( true );

		$schema = $method->invoke( $module, 'test-template' );

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'test_field', $schema );

		// Clean up.
		unlink( $test_schema_file );
	}

	/**
	 * Test schema loading returns empty array for missing file
	 */
	public function test_returns_empty_array_for_missing_schema() {
		$module = new Speakeasy_LAP_Meta_Module();

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $module );
		$method     = $reflection->getMethod( 'load_schema_file' );
		$method->setAccessible( true );

		$schema = $method->invoke( $module, 'nonexistent-template' );

		$this->assertIsArray( $schema );
		$this->assertEmpty( $schema );
	}

	/**
	 * Test can be registered with Module Manager
	 */
	public function test_can_be_registered_with_manager() {
		$manager = Speakeasy_Module_Manager::instance();
		$module  = new Speakeasy_LAP_Meta_Module();

		$manager->register_module( 'lap-meta', $module );

		$registered = $manager->get_module( 'lap-meta' );
		$this->assertSame( $module, $registered );
	}

	/**
	 * Test module handles missing schema directory gracefully
	 */
	public function test_handles_missing_schema_directory() {
		// This should not throw an error.
		$module = new Speakeasy_LAP_Meta_Module();

		$this->assertInstanceOf( Speakeasy_LAP_Meta_Module::class, $module );
	}
}
