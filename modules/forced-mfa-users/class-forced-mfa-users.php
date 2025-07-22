<?php
namespace Automattic\VIP\Security\MFAUsers;

use Automattic\VIP\Security\Utils\Configs;
use Automattic\VIP\Security\Utils\Capability_Utils;

class Forced_MFA_Users {
	/**
	 * The roles that should have MFA enforced.
	 *
	 * @var array An array of role slugs.
	 */
	private static $roles;

	/**
	 * The capabilities that should have MFA enforced.
	 *
	 * @var array An array of capability slugs.
	 */
	private static $capabilities;

	public static function init() {
		$forced_mfa_configs = Configs::get_module_configs( 'forced-mfa-users' );
		if ( empty( $forced_mfa_configs ) ) {
			return;
		}
		
		// Normalize capabilities and roles configuration
		self::$capabilities = Capability_Utils::normalize_capabilities_input( $forced_mfa_configs['capabilities'] ?? [] );
		self::$roles        = Capability_Utils::normalize_roles_input( $forced_mfa_configs['roles'] ?? [] );
		
		// Ensure we have either capabilities or roles configured
		if ( empty( self::$capabilities ) && empty( self::$roles ) ) {
			return;
		}
		
		add_action( 'set_current_user', [ __CLASS__, 'maybe_enforce_two_factor' ] );

		// Add SDS hook
		add_filter( 'vip_site_details_index_data', [ __CLASS__, 'add_two_factor_enforcement_status_to_sds_payload' ] );
	}

	/**
	 * Require 2FA based on capabilities or roles set in config
	 */
	public static function maybe_enforce_two_factor() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		// don't enforce 2FA if the user is already excluded by VIP mu-plugins logic
		if ( function_exists( 'wpcom_vip_should_force_two_factor' ) && ! wpcom_vip_should_force_two_factor() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return;
		}

		// Check if user has elevated permissions based on capabilities or roles
		$user_has_two_factor_enforced = Capability_Utils::user_has_elevated_permissions( 
			$user, 
			self::$capabilities, 
			self::$roles 
		);

		if ( $user_has_two_factor_enforced ) {
			add_filter( 'wpcom_vip_is_two_factor_forced', function () {
				return true;
			}, PHP_INT_MAX );
		}
	}

	/**
	 * Add the two factor enforcement status to the SDS payload.
	 *
	 * @param array $data The SDS payload.
	 *
	 * @return array The SDS payload with the two factor enforcement status added.
	 */
	public static function add_two_factor_enforcement_status_to_sds_payload( $data ) {
		// TODO wait for PR #59 and use the SDS_DATA_KEY constant
		if ( ! isset( $data['vip_security_boost'] ) ) {
			$data['vip_security_boost'] = array();
		}
		$data['vip_security_boost']['two_factor_status_'] = self::get_two_factor_enforcement_status();
		return $data;
	}

	/**
	 * Check if the `wpcom_vip_is_two_factor_forced` filter has been overridden to always return true.
	 *
	 * Some codebases might add `add_filter( 'wpcom_vip_is_two_factor_forced', '__return_true' );`
	 * to enforce Two-Factor globally. This helper allows runtime checks (e.g. within tests)
	 * to detect that situation without triggering the filter itself.
	 *
	 * @return array {
	 *     'is_enforced_globally': bool,
	 *     'is_not_enforced_globally': bool,
	 *     'has_two_factor_forced_filter': bool,
	 *     'is_entirely_disabled': bool,
	 *     'has_enable_two_factor_filter': bool
	 * }
	 */
	public static function get_two_factor_enforcement_status(): array {
		// Explicit global namespace references are required inside namespaced code.
		$filters = [
			// return wpcom_vip_is_two_factor_forced status
			'is_enforced_globally'         => \has_filter( 'wpcom_vip_is_two_factor_forced', '__return_true' ) !== false,
			'is_not_enforced_globally'     => \has_filter( 'wpcom_vip_is_two_factor_forced', '__return_false' ) !== false,
			'has_two_factor_forced_filter' => \has_filter( 'wpcom_vip_is_two_factor_forced' ) !== false,
			// return wpcom_vip_enable_two_factor status
			'is_entirely_disabled'         => \has_filter( 'wpcom_vip_enable_two_factor', '__return_false' ) !== false || apply_filters( 'wpcom_vip_enable_two_factor', true ) === false,
			'has_enable_two_factor_filter' => \has_filter( 'wpcom_vip_enable_two_factor' ) !== false,
		];
		return $filters;
	}
}

Forced_MFA_Users::init();
