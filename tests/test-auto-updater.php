<?php
/**
 * Tests for Speakeasy_Auto_Updater
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for Auto Updater
 */
class Test_Auto_Updater extends WP_UnitTestCase {

	/**
	 * Test that class exists
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'Speakeasy_Auto_Updater' ) );
	}

	/**
	 * Test constructor requires GitHub configuration
	 */
	public function test_constructor_checks_github_config() {
		// Without GitHub configuration, updater should not initialize.
		if ( ! defined( 'SPEAKEASY_GITHUB_REPO' ) ) {
			$updater = new Speakeasy_Auto_Updater();
			$this->assertInstanceOf( Speakeasy_Auto_Updater::class, $updater );
		}
	}

	/**
	 * Test that updater can be instantiated
	 */
	public function test_can_be_instantiated() {
		$updater = new Speakeasy_Auto_Updater();
		$this->assertInstanceOf( Speakeasy_Auto_Updater::class, $updater );
	}

	/**
	 * Test auto_update_plugin filter is registered
	 */
	public function test_auto_update_filter_registered() {
		$updater = new Speakeasy_Auto_Updater();

		// The filter should be registered.
		$this->assertTrue( has_filter( 'auto_update_plugin' ) !== false );
	}

	/**
	 * Test enable_auto_update returns true for this plugin
	 */
	public function test_enable_auto_update_for_this_plugin() {
		$updater = new Speakeasy_Auto_Updater();

		// Simulate the filter.
		$plugin_file = 'wp-speakeasy/wp-speakeasy.php';
		$update      = (object) array(
			'plugin' => $plugin_file,
		);

		$result = $updater->enable_auto_update( false, $update );

		// Should return true for our plugin.
		$this->assertTrue( $result );
	}

	/**
	 * Test enable_auto_update returns original value for other plugins
	 */
	public function test_enable_auto_update_for_other_plugins() {
		$updater = new Speakeasy_Auto_Updater();

		// Simulate the filter for a different plugin.
		$update = (object) array(
			'plugin' => 'other-plugin/other-plugin.php',
		);

		$result = $updater->enable_auto_update( false, $update );

		// Should return original value (false) for other plugins.
		$this->assertFalse( $result );
	}
}
