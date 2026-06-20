<?php
/**
 * Tests for Speakeasy_App_Passwords_Module
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for Application Passwords module
 */
class Test_App_Passwords_Module extends WP_UnitTestCase {

	/**
	 * Test module metadata
	 */
	public function test_module_metadata() {
		$module = new Speakeasy_App_Passwords_Module();

		$this->assertEquals( 'Application Passwords Enabler', $module->get_name() );
		$this->assertEquals( '1.0.0', $module->get_version() );
		$this->assertIsString( $module->get_description() );
		$this->assertNotEmpty( $module->get_description() );
	}

	/**
	 * Test module priority is high (999)
	 */
	public function test_module_priority_is_high() {
		$module = new Speakeasy_App_Passwords_Module();

		$this->assertEquals( 999, $module->get_priority() );
	}

	/**
	 * Test module is enabled by default
	 */
	public function test_module_enabled_by_default() {
		$module = new Speakeasy_App_Passwords_Module();

		$this->assertTrue( $module->is_enabled() );
	}

	/**
	 * Test module implements interface
	 */
	public function test_module_implements_interface() {
		$module = new Speakeasy_App_Passwords_Module();

		$this->assertInstanceOf( Speakeasy_Module::class, $module );
	}

	/**
	 * Test wp_is_application_passwords_available filter
	 */
	public function test_application_passwords_available_filter() {
		$module = new Speakeasy_App_Passwords_Module();
		$module->init();

		// Simulate the filter.
		$result = apply_filters( 'wp_is_application_passwords_available', false );

		// Our module should force it to true with priority 999.
		$this->assertTrue( $result );
	}

	/**
	 * Test wp_is_application_passwords_available_for_user filter
	 */
	public function test_application_passwords_available_for_user_filter() {
		$module = new Speakeasy_App_Passwords_Module();
		$module->init();

		// Simulate the filter for a specific user.
		$result = apply_filters( 'wp_is_application_passwords_available_for_user', false, 1 );

		// Our module should force it to true.
		$this->assertTrue( $result );
	}

	/**
	 * Test module removes known blockers
	 */
	public function test_removes_known_blockers() {
		// Add a fake blocker filter.
		add_filter( 'wp_is_application_passwords_available', '__return_false', 10 );

		$module = new Speakeasy_App_Passwords_Module();
		$module->init();

		// Our filter with priority 999 should override the blocker.
		$result = apply_filters( 'wp_is_application_passwords_available', false );

		$this->assertTrue( $result );

		// Clean up.
		remove_all_filters( 'wp_is_application_passwords_available' );
	}

	/**
	 * Test module can be registered with Module Manager
	 */
	public function test_can_be_registered_with_manager() {
		$manager = Speakeasy_Module_Manager::instance();
		$module  = new Speakeasy_App_Passwords_Module();

		$manager->register_module( 'app-passwords', $module );

		$registered = $manager->get_module( 'app-passwords' );
		$this->assertSame( $module, $registered );
	}
}
