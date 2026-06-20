<?php
/**
 * Tests for Speakeasy_Module_Manager
 *
 * @package WP_Speakeasy
 */

/**
 * Test case for Module Manager
 */
class Test_Module_Manager extends WP_UnitTestCase {

	/**
	 * Reset singleton instance before each test
	 */
	public function setUp(): void {
		parent::setUp();
		// Reset the singleton instance using reflection.
		$reflection = new ReflectionClass( Speakeasy_Module_Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$instance->setAccessible( false );
	}

	/**
	 * Test singleton pattern returns same instance
	 */
	public function test_singleton_returns_same_instance() {
		$instance1 = Speakeasy_Module_Manager::instance();
		$instance2 = Speakeasy_Module_Manager::instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test module registration with valid module
	 */
	public function test_register_module_with_valid_module() {
		$manager = Speakeasy_Module_Manager::instance();
		$module  = $this->create_test_module( 'test-module' );

		$manager->register_module( 'test-module', $module );

		$registered = $manager->get_module( 'test-module' );
		$this->assertSame( $module, $registered );
	}

	/**
	 * Test get_module returns null for unregistered module
	 */
	public function test_get_module_returns_null_for_unregistered() {
		$manager = Speakeasy_Module_Manager::instance();

		$this->assertNull( $manager->get_module( 'nonexistent-module' ) );
	}

	/**
	 * Test get_all_modules returns all registered modules
	 */
	public function test_get_all_modules_returns_all_registered() {
		$manager = Speakeasy_Module_Manager::instance();
		$module1 = $this->create_test_module( 'module-1' );
		$module2 = $this->create_test_module( 'module-2' );

		$manager->register_module( 'module-1', $module1 );
		$manager->register_module( 'module-2', $module2 );

		$all_modules = $manager->get_all_modules();

		$this->assertCount( 2, $all_modules );
		$this->assertArrayHasKey( 'module-1', $all_modules );
		$this->assertArrayHasKey( 'module-2', $all_modules );
	}

	/**
	 * Test init_modules initializes enabled modules in priority order
	 */
	public function test_init_modules_respects_priority_order() {
		$manager = Speakeasy_Module_Manager::instance();

		// Create modules with different priorities.
		$module_high   = $this->create_test_module( 'high-priority', 10 );
		$module_medium = $this->create_test_module( 'medium-priority', 50 );
		$module_low    = $this->create_test_module( 'low-priority', 100 );

		// Register in random order.
		$manager->register_module( 'low', $module_low );
		$manager->register_module( 'high', $module_high );
		$manager->register_module( 'medium', $module_medium );

		// Track initialization order.
		global $init_order;
		$init_order = array();

		$manager->init_modules();

		// Verify they initialized in priority order (low number first).
		$this->assertEquals( array( 'high-priority', 'medium-priority', 'low-priority' ), $init_order );
	}

	/**
	 * Test is_module_enabled returns correct status
	 */
	public function test_is_module_enabled_returns_status() {
		$manager = Speakeasy_Module_Manager::instance();
		$module  = $this->create_test_module( 'test-module', 10, true );

		$manager->register_module( 'test-module', $module );

		$this->assertTrue( $manager->is_module_enabled( 'test-module' ) );
	}

	/**
	 * Test is_module_enabled returns false for unregistered module
	 */
	public function test_is_module_enabled_false_for_unregistered() {
		$manager = Speakeasy_Module_Manager::instance();

		$this->assertFalse( $manager->is_module_enabled( 'nonexistent' ) );
	}

	/**
	 * Test enable_module enables a module
	 */
	public function test_enable_module_enables_module() {
		$manager = Speakeasy_Module_Manager::instance();
		$module  = $this->create_test_module( 'test-module', 10, false );

		$manager->register_module( 'test-module', $module );

		$result = $manager->enable_module( 'test-module' );

		$this->assertTrue( $result );
	}

	/**
	 * Test disable_module disables a module
	 */
	public function test_disable_module_disables_module() {
		$manager = Speakeasy_Module_Manager::instance();
		$module  = $this->create_test_module( 'test-module', 10, true );

		$manager->register_module( 'test-module', $module );

		$result = $manager->disable_module( 'test-module' );

		$this->assertTrue( $result );
	}

	/**
	 * Test enable_module returns false for unregistered module
	 */
	public function test_enable_module_fails_for_unregistered() {
		$manager = Speakeasy_Module_Manager::instance();

		$result = $manager->enable_module( 'nonexistent' );

		$this->assertFalse( $result );
	}

	/**
	 * Helper: Create a test module
	 *
	 * @param string $name     Module name.
	 * @param int    $priority Priority.
	 * @param bool   $enabled  Enabled status.
	 * @return Speakeasy_Module
	 */
	private function create_test_module( $name, $priority = 10, $enabled = true ) {
		return new class( $name, $priority, $enabled ) implements Speakeasy_Module {
			private $name;
			private $priority;
			private $enabled;

			public function __construct( $name, $priority, $enabled ) {
				$this->name     = $name;
				$this->priority = $priority;
				$this->enabled  = $enabled;
			}

			public function get_name(): string {
				return $this->name;
			}

			public function get_description(): string {
				return 'Test module: ' . $this->name;
			}

			public function get_version(): string {
				return '1.0.0';
			}

			public function init(): void {
				global $init_order;
				if ( ! isset( $init_order ) ) {
					$init_order = array();
				}
				$init_order[] = $this->name;
			}

			public function is_enabled(): bool {
				return $this->enabled;
			}

			public function get_priority(): int {
				return $this->priority;
			}
		};
	}
}
