<?php
namespace Automattic\VIP\Security\MFAUsers;

class Forced_MFA_Users {
	const MFA_SKIP_USER_IDS_OPTION_KEY = 'vip_security_mfa_skip_user_ids';

	/**
	 * The capability required to force MFA.
	 *
	 * @var string|array The capability slug or an array of slugs.
	 */
	private static $capability;

	public static function init() {
		if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
			return;
		}
	
		$configs               = constant( 'VIP_SECURITY_BOOST_CONFIGS' );
		$module_configs        = $configs[ 'module_configs' ] ?? [];
		$forced_mfa_configs    = $module_configs[ 'forced-mfa-users' ] ?? [];

		self::$capability = $forced_mfa_configs['capability'] ?? [];
        add_action( 'set_current_user', [ __CLASS__, 'filter_user_capabilities' ] );
	}

    /**
    * Require 2FA based on capabilities set in config
    */
    public static function filter_user_capabilities() {
        $required_capability_or_caps = self::$capability;

        if ( empty( $required_capability_or_caps ) ) {
            return;
        }

        $user_has_required_capability = false;

        if ( is_array( $required_capability_or_caps ) ) {
            foreach ( $required_capability_or_caps as $cap ) {
                if ( is_string( $cap ) && ! empty( $cap ) && current_user_can( $cap ) ) {
                    $user_has_required_capability = true;
                    break;
                }
            }
        } elseif ( is_string( $required_capability_or_caps ) ) {
            $user_has_required_capability = current_user_can( $required_capability_or_caps );
        }

        if ( $user_has_required_capability ) {
            add_filter( 'wpcom_vip_is_two_factor_forced', function() {
                return true;
            }, PHP_INT_MAX );
        }
    }
}
Forced_MFA_Users::init();