<?php
/**
 * Tests for Speakeasy_Admin_Page
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for Admin Page
 */
class Test_Admin_Page extends WP_UnitTestCase {

	/**
	 * Test that class exists
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'Speakeasy_Admin_Page' ) );
	}

	/**
	 * Test that admin page can be instantiated
	 */
	public function test_can_be_instantiated() {
		$admin = new Speakeasy_Admin_Page();
		$this->assertInstanceOf( Speakeasy_Admin_Page::class, $admin );
	}

	/**
	 * Test admin_menu action is registered
	 */
	public function test_admin_menu_action_registered() {
		$admin = new Speakeasy_Admin_Page();

		$this->assertTrue( has_action( 'admin_menu' ) !== false );
	}

	/**
	 * Test admin_init action is registered
	 */
	public function test_admin_init_action_registered() {
		$admin = new Speakeasy_Admin_Page();

		$this->assertTrue( has_action( 'admin_init' ) !== false );
	}

	/**
	 * Test render_settings_page requires manage_options capability
	 */
	public function test_settings_page_requires_capability() {
		// Mock user without manage_options capability.
		wp_set_current_user( 0 ); // Guest user.

		$admin = new Speakeasy_Admin_Page();

		// Start output buffering.
		ob_start();
		$admin->render_settings_page();
		$output = ob_get_clean();

		// Should show permission error or be empty.
		$this->assertTrue( empty( $output ) || strpos( $output, 'permission' ) !== false );
	}

	/**
	 * Test get_system_info returns array
	 */
	public function test_get_system_info_returns_array() {
		$admin = new Speakeasy_Admin_Page();

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $admin );
		$method     = $reflection->getMethod( 'get_system_info' );
		$method->setAccessible( true );

		$info = $method->invoke( $admin );

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'wordpress_version', $info );
		$this->assertArrayHasKey( 'php_version', $info );
	}
}
