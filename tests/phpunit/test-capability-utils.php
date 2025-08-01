<?php

namespace Automattic\VIP\Security\Tests;

use Automattic\VIP\Security\Utils\Capability_Utils;
use WP_UnitTestCase;

/**
 * Test Capability_Utils class
 */
class Test_Capability_Utils extends WP_UnitTestCase {

	/**
	 * Test user_has_any_capability with valid capabilities
	 */
	public function test_user_has_any_capability_with_valid_caps() {
		$user = $this->factory->user->create_and_get( array(
			'role' => 'administrator',
		) );

		// Administrators have 'manage_options' capability
		$this->assertTrue( 
			Capability_Utils::user_has_any_capability( $user, [ 'manage_options' ] ),
			'Administrator should have manage_options capability'
		);

		// Test with multiple capabilities (OR logic)
		$this->assertTrue( 
			Capability_Utils::user_has_any_capability( $user, [ 'fake_capability', 'manage_options', 'another_fake' ] ),
			'Should return true if user has at least one capability'
		);
	}

	/**
	 * Test user_has_any_capability with invalid capabilities
	 */
	public function test_user_has_any_capability_with_invalid_caps() {
		$user = $this->factory->user->create_and_get( array(
			'role' => 'subscriber',
		) );

		// Subscriber shouldn't have admin capabilities
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ 'manage_options' ] ),
			'Subscriber should not have manage_options capability'
		);

		// Test with non-existent capabilities
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ 'fake_capability_1', 'fake_capability_2' ] ),
			'Should return false for non-existent capabilities'
		);
	}

	/**
	 * Test user_has_any_capability with empty or invalid input
	 */
	public function test_user_has_any_capability_with_invalid_input() {
		$user = $this->factory->user->create_and_get( array(
			'role' => 'administrator',
		) );

		// Empty capabilities array
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [] ),
			'Should return false for empty capabilities array'
		);

		// Null user
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( null, [ 'manage_options' ] ),
			'Should return false for null user'
		);

		// Non-existent user
		$fake_user = new \WP_User( 999999 );
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $fake_user, [ 'manage_options' ] ),
			'Should return false for non-existent user'
		);
	}

	/**
	 * Test that user_has_any_capability directly checks allcaps array
	 */
	public function test_user_has_any_capability_uses_allcaps() {
		$user = $this->factory->user->create_and_get( array(
			'role' => 'subscriber',
		) );

		// Directly add a capability to allcaps without going through WP capability system
		$user->allcaps['custom_capability'] = true;

		// This should return true because we're checking allcaps directly
		$this->assertTrue( 
			Capability_Utils::user_has_any_capability( $user, [ 'custom_capability' ] ),
			'Should find capability in allcaps array'
		);

		// Test with falsy capability value
		$user->allcaps['disabled_capability'] = false;
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ 'disabled_capability' ] ),
			'Should return false for falsy capability value'
		);
	}

	/**
	 * Test that method doesn't trigger infinite loops in map_meta_cap context
	 * This test simulates the conditions that would cause an infinite loop
	 */
	public function test_no_infinite_loop_in_map_meta_cap() {
		$user = $this->factory->user->create_and_get( array(
			'role' => 'administrator',
		) );

		// Track if we're in a potential infinite loop
		$call_count = 0;
		$max_calls  = 5;

		add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id ) use ( &$call_count, $max_calls ) {
			$call_count++;
			
			// Prevent actual infinite loop in test
			if ( $call_count > $max_calls ) {
				$this->fail( 'Potential infinite loop detected in map_meta_cap' );
			}

			// This should NOT cause infinite loop because we check allcaps directly
			if ( 'upload_files' === $cap ) {
				$current_user = get_user_by( 'id', $user_id );
				Capability_Utils::user_has_any_capability( $current_user, [ 'manage_options' ] );
			}

			return $caps;
		}, 10, 3 );

		// Trigger map_meta_cap
		user_can( $user, 'upload_files' );

		// Remove filter
		remove_all_filters( 'map_meta_cap' );

		// If we got here without failing, the test passed
		$this->assertLessThanOrEqual( $max_calls, $call_count, 'Should not exceed max calls' );
	}

	/**
	 * Test normalize_capabilities_input
	 */
	public function test_normalize_capabilities_input() {
		// String input
		$this->assertEquals( 
			[ 'manage_options' ], 
			Capability_Utils::normalize_capabilities_input( 'manage_options' ),
			'Should convert string to array'
		);

		// Array input
		$this->assertEquals( 
			[ 'manage_options', 'edit_posts' ], 
			Capability_Utils::normalize_capabilities_input( [ 'manage_options', 'edit_posts' ] ),
			'Should return valid array as-is'
		);

		// Empty values filtered out
		$this->assertEquals( 
			[ 'manage_options' ], 
			Capability_Utils::normalize_capabilities_input( [ 'manage_options', '', '   ', null ] ),
			'Should filter out empty values'
		);

		// Invalid input types
		$this->assertEquals( [], Capability_Utils::normalize_capabilities_input( null ) );
		$this->assertEquals( [], Capability_Utils::normalize_capabilities_input( 123 ) );
		$this->assertEquals( [], Capability_Utils::normalize_capabilities_input( new \stdClass() ) );
	}

	/**
	 * Test normalize_roles_input
	 */
	public function test_normalize_roles_input() {
		// String input
		$this->assertEquals( 
			[ 'administrator' ], 
			Capability_Utils::normalize_roles_input( 'administrator' ),
			'Should convert string to array'
		);

		// Array input with filtering
		$this->assertEquals( 
			[ 'administrator', 'editor' ], 
			Capability_Utils::normalize_roles_input( [ 'administrator', '', 'editor', '   ' ] ),
			'Should filter out empty values'
		);
	}

	/**
	 * Test user_has_any_capability with corrupted allcaps
	 */
	public function test_user_has_any_capability_with_corrupted_allcaps() {
		$user = $this->factory->user->create_and_get( array(
			'role' => 'administrator',
		) );

		// Test with non-array allcaps
		$user->allcaps = null;
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ 'manage_options' ] ),
			'Should return false when allcaps is null'
		);

		$user->allcaps = 'string';
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ 'manage_options' ] ),
			'Should return false when allcaps is a string'
		);

		$user->allcaps = new \stdClass();
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ 'manage_options' ] ),
			'Should return false when allcaps is an object'
		);

		// Test with unset allcaps
		unset( $user->allcaps );
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ 'manage_options' ] ),
			'Should return false when allcaps is not set'
		);
	}

	/**
	 * Test user_has_any_capability with non-scalar capabilities
	 */
	public function test_user_has_any_capability_with_non_scalar_capabilities() {
		$user = $this->factory->user->create_and_get( array(
			'role' => 'administrator',
		) );

		// Mix of valid and invalid capability types
		$capabilities = [
			'manage_options',  // valid
			123,              // valid (scalar)
			null,             // invalid
			[],               // invalid
			new \stdClass(),  // invalid
			'edit_posts',     // valid
		];

		// Should still return true because valid capabilities exist
		$this->assertTrue( 
			Capability_Utils::user_has_any_capability( $user, $capabilities ),
			'Should skip non-scalar capabilities and still find valid ones'
		);

		// Only non-scalar capabilities
		$this->assertFalse( 
			Capability_Utils::user_has_any_capability( $user, [ null, [], new \stdClass() ] ),
			'Should return false when all capabilities are non-scalar'
		);
	}
}
