<?php
require_once __DIR__ . '/test-inactive-users.php';
/**
 * Test class that runs all InactiveUsers tests with corrupted roles.
 *
 * This ensures that the inactive users functionality is resilient to database corruption
 * where roles may have missing 'name' keys or 'capabilities' stored as strings instead of arrays.
 */
class InactiveUsersCorruptedRolesTest extends \InactiveUsersTest {
	/**
	 * Stores the original roles before corruption.
	 *
	 * @var array|false
	 */
	private $original_roles;

	/**
	 * The option name for roles in the database.
	 *
	 * @var string
	 */
	private $role_option_name;

	/**
	 * Stores the original error handler.
	 *
	 * @var callable|null
	 */
	private $original_error_handler;

	/**
	 * Set up each test with corrupted roles.
	 */
	public function setUp(): void {
		parent::setUp();

		// Install custom error handler to suppress expected role repair warnings
		$this->original_error_handler = set_error_handler( [ $this, 'handle_expected_role_warnings' ] );

		// Save and corrupt roles
		$this->role_option_name = $this->get_role_option_name();
		$this->original_roles   = get_option( $this->role_option_name );
		$this->corrupt_roles();

		// Register sanitizers immediately after corruption to prevent errors
		\Automattic\VIP\Security\Utils\Role_Sanitizer::register_role_sanitizers();
	}

	/**
	 * Restore original roles after each test.
	 */
	public function tearDown(): void {
		// Unregister sanitizers before restoring roles
		\Automattic\VIP\Security\Utils\Role_Sanitizer::unregister_role_sanitizers();
		$this->restore_roles();

		// Restore original error handler
		if ( null !== $this->original_error_handler ) {
			restore_error_handler();
		}

		parent::tearDown();
	}

	/**
	 * Custom error handler that suppresses expected role repair warnings.
	 *
	 * @param int    $errno   The level of the error.
	 * @param string $errstr  The error message.
	 * @param string $errfile The filename where the error occurred.
	 * @param int    $errline The line number where the error occurred.
	 * @return bool True to suppress the error, false to use default error handler.
	 */
	public function handle_expected_role_warnings( $errno, $errstr, $errfile, $errline ) {
		// Suppress E_USER_WARNING from Role_Sanitizer about repaired roles
		if ( $errno === E_USER_WARNING && strpos( $errstr, 'Repaired' ) !== false && strpos( $errfile, 'class-role-sanitizer.php' ) !== false ) {
			return true; // Suppress this warning
		}

		// For all other errors, call the original handler or return false to use default
		if ( null !== $this->original_error_handler && is_callable( $this->original_error_handler ) ) {
			return call_user_func( $this->original_error_handler, $errno, $errstr, $errfile, $errline );
		}

		return false; // Use default error handler
	}

	/**
	 * Corrupt roles to simulate database corruption scenarios.
	 *
	 * @return void
	 */
	private function corrupt_roles() {
		$roles = $this->original_roles;

		if ( ! is_array( $roles ) ) {
			return;
		}

		// Corruption scenario 1: Remove 'name' key from administrator role
		if ( isset( $roles['administrator'] ) ) {
			unset( $roles['administrator']['name'] );
		}

		// Corruption scenario 2: Change 'capabilities' to string for editor role
		if ( isset( $roles['editor']['capabilities'] ) && is_array( $roles['editor']['capabilities'] ) ) {
			$roles['editor']['capabilities'] = 'edit_posts';
		}

		// Corruption scenario 3: Remove 'name' key from author role
		if ( isset( $roles['author'] ) ) {
			unset( $roles['author']['name'] );
		}

		update_option( $this->role_option_name, $roles );

		// Clear wp_roles global to force reload with corrupted data
		global $wp_roles;
		$wp_roles = null;
	}

	/**
	 * Restore the original roles.
	 *
	 * @return void
	 */
	private function restore_roles() {
		if ( false !== $this->original_roles ) {
			update_option( $this->role_option_name, $this->original_roles );
		}

		// Clear wp_roles global to force reload with original data
		global $wp_roles;
		$wp_roles = null;
	}

	/**
	 * Get the role option name for the current site.
	 *
	 * @return string
	 */
	private function get_role_option_name() {
		global $wpdb;
		$prefix = is_multisite()
			? $wpdb->get_blog_prefix( get_current_blog_id() )
			: $wpdb->prefix;
		return $prefix . 'user_roles';
	}
}
