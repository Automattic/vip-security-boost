<?php

namespace Automattic\VIP\Security\SessionControl;

use Automattic\VIP\Security\Constants;
use Automattic\VIP\Security\Utils\Logger;
use Automattic\VIP\Security\Utils\Configs;

/**
 * Session Control module for VIP Security Boost
 *
 * This module allows controlling the WordPress session time length.
 * Options:
 * - "default": WordPress default behavior (module not active)
 * - 1-13: Number of days the session should last. WordPress default is 14 days, so we're allowing users to choose a number below it.
 */
class Session_Control {
	const LOG_FEATURE_NAME     = 'sb_session_control';
	public const DEFAULT_VALUE = 'default';
	/**
	 * Session expiration time in days
	 *
	 * @var string|int
	 */
	private static $expiration_days = self::DEFAULT_VALUE;

	/**
	 * Stores the reason for invalid configuration, if any.
	 *
	 * @var array { warning: string, expiration_days: string | int }
	 */
	private static $invalid_config;

	/**
	 * Initialize the module
	 */
	public static function init() {
		if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
			return;
		}

		$session_configs       = Configs::get_module_configs( 'session-control' );
		$expiration_days_value = $session_configs['expiration_days'] ?? self::DEFAULT_VALUE;

		// Only apply if a valid expiration time is set
		if ( self::DEFAULT_VALUE !== $expiration_days_value ) {
			// Validate the expiration days value (must be between 1 and 13)
			// check if it's valid int
			if ( ! is_numeric( $expiration_days_value ) ) {
				self::$invalid_config = [
					'warning'         => 'Invalid session expiration days. Must be an integer. Reverting to default.',
					'expiration_days' => $expiration_days_value,
					'error_code'      => 'is-not-numeric',
				];

				// Add filter to trigger error only during authentication
				add_filter( 'wp_login', array( __CLASS__, 'maybe_log_invalid_config' ), 10, 2 );
				return;
			}
			$expiration_days = intval( $expiration_days_value );

			if ( $expiration_days < 1 || $expiration_days > 13 ) {
				self::$invalid_config = [
					'warning'         => 'Invalid session expiration days. Must be between 1 and 13. Reverting to default.',
					'expiration_days' => $expiration_days_value,
					'error_code'      => 'is-out-of-range',
				];

				// Add filter to trigger error only during authentication
				add_filter( 'wp_login', array( __CLASS__, 'maybe_log_invalid_config' ), 10, 2 );
				return;
			}
			self::$expiration_days = $expiration_days;
			// Add filters to modify session expiration time
			add_filter( 'auth_cookie_expiration', array( __CLASS__, 'set_auth_cookie_expiration' ), 99, 3 );
		}
	}

	/**
	 * Set the authentication cookie expiration time
	 *
	 * @param int $expiration The current expiration timestamp.
	 * @param int $user_id User ID.
	 * @param bool $remember Whether to remember the user login.
	 *
	 * @return int Modified expiration timestamp
	 */
	public static function set_auth_cookie_expiration( $expiration, $user_id, $remember ) {
		// Only apply custom expiration if "Remember Me" is checked
		if ( $remember && self::DEFAULT_VALUE !== self::$expiration_days ) {
			// Convert days to seconds
			$days_in_seconds = self::$expiration_days * DAY_IN_SECONDS;
			// Set the expiration time based on our configuration
			return $days_in_seconds;
		}

		// Return the default expiration if "Remember Me" is not checked
		return $expiration;
	}

	/**
	 * If configuration is invalid, log the stored warning during user authentication.
	 *
	 * @param string $user_login The username.
	 * @param \WP_User $user The user object.
	 *
	 * @return \WP_User The unmodified user object.
	 */
	public static function maybe_log_invalid_config( $user_login, $user ) {
		if ( empty( self::$invalid_config ) ) {
			return $user;
		}
		Logger::warning( self::LOG_FEATURE_NAME, self::$invalid_config['warning'], [ 'expiration_days' => self::$invalid_config['expiration_days'], 'error_code' => self::$invalid_config['error_code'] ] );
		return $user;
	}
}

Session_Control::init();
