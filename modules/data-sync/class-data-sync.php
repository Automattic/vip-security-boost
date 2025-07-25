<?php

namespace Automattic\VIP\Security\Data_Sync;

use Automattic\VIP\Security\Utils\Logger;
use Automattic\VIP\Security\Constants;

/**
 * This is the class that sends data to Site Details Service (SDS)
 * no matter which modules are active since in some cases there is some data that does not belong to a specific module.
 *
 * The module is always loaded and doesn't need to be enabled in the config.
 */
class Data_Sync {
	const LOG_FEATURE_NAME = 'sb_data_sync';

	public static function init() {
		// adding the dedicated security_boost filter via vip_site_details_index_security_boost_data, this should be used if we want to add data to only the security_boost key
		add_filter( 'vip_site_details_index_data', [ __CLASS__, 'add_security_boost_extended_data' ] );

		// adds 2FA data to the security_boost SDS key
		add_filter( 'vip_site_details_index_security_boost_data', [ __CLASS__, 'add_two_factor_enforcement_status_to_sds_payload' ] );

		// adds Jetpack Account Protection status to the security_boost SDS key
		add_filter( 'vip_site_details_index_security_boost_data', [ __CLASS__, 'add_jetpack_account_protection_status' ] );
	}
	/**
	 * This is the aggregator to ensure every module can simply add its own data to the SDS payload via vip_site_details_index_security_boost_data
	 */
	public static function add_security_boost_extended_data( $data ) {
		try {
			if ( ! isset( $data[ Constants::SDS_DATA_KEY ] ) ) {
				$data[ Constants::SDS_DATA_KEY ] = array();
			}
			$updated_data                    = apply_filters( 'vip_site_details_index_security_boost_data', $data[ Constants::SDS_DATA_KEY ] );
			$data[ Constants::SDS_DATA_KEY ] = $updated_data;
		} catch ( \Exception $e ) {
			Logger::error(
				self::LOG_FEATURE_NAME,
				'Error adding security boost extended data to SDS payload: ' . $e->getMessage()
			);
		}
		return $data;
	}


	/**
	 * Add the two-factor enforcement status details to the Site Details Service (SDS) payload.
	 *
	 * The function augments the incoming `$data` array by injecting a new element under the
	 * standard SDS data key (`Constants::SDS_DATA_KEY`). The resulting payload section has the
	 * following structure:
	 *
	 *     'two_factor_status' => [
	 *         'is_enforced_globally'         => bool, // `wpcom_vip_is_two_factor_forced` hooked to `__return_true`
	 *         'is_not_enforced_globally'     => bool, // `wpcom_vip_is_two_factor_forced` hooked to `__return_false`
	 *         'has_two_factor_forced_filter' => bool, // Any filter present on `wpcom_vip_is_two_factor_forced`
	 *         'is_entirely_disabled'         => bool, // 2FA disabled via `wpcom_vip_enable_two_factor` returning false
	 *         'has_enable_two_factor_filter' => bool, // Any filter present on `wpcom_vip_enable_two_factor`
	 *     ],
	 *
	 * @return array Modified SDS payload including the `two_factor_status` information.
	 */
	public static function add_two_factor_enforcement_status_to_sds_payload( $data ) {
		$data['two_factor_status'] = [
			// return wpcom_vip_is_two_factor_forced status
			'is_enforced_globally'         => \has_filter( 'wpcom_vip_is_two_factor_forced', '__return_true' ) !== false,
			'is_not_enforced_globally'     => \has_filter( 'wpcom_vip_is_two_factor_forced', '__return_false' ) !== false,
			'has_two_factor_forced_filter' => has_filter( 'wpcom_vip_is_two_factor_forced' ) !== false,
			// return wpcom_vip_enable_two_factor status
			'is_entirely_disabled'         => \has_filter( 'wpcom_vip_enable_two_factor', '__return_false' ) !== false || apply_filters( 'wpcom_vip_enable_two_factor', true ) === false,
			'has_enable_two_factor_filter' => \has_filter( 'wpcom_vip_enable_two_factor' ) !== false,
		];
		return $data;
	}

	/**
	 * Append Jetpack Account Protection status information.
	 *
	 * Possible values:
	 *  - jetpack_missing_or_inactive  : Jetpack plugin not installed or inactive.
	 *  - active                       : Feature active.
	 *  - disabled_via_code            : Disabled through DISABLE_JETPACK_ACCOUNT_PROTECTION constant.
	 *  - disabled_via_options         : Disabled via Jetpack options/settings.
	 *
	 * @param array $data Existing security boost data.
	 * @return array Modified data including `jetpack_account_protection_status` key.
	 */
	public static function add_jetpack_account_protection_status( $data ) {
		if ( ! class_exists( 'Jetpack' ) || ! \Jetpack::is_active() ) {
			$data[ Constants::SDS_JETPACK_ACCOUNT_PROTECTION_STATUS_KEY ] = 'jp_missing_or_inactive';
			return $data;
		}
		// check if jp is in offline mode
		if ( class_exists( '\Automattic\Jetpack\Status' ) ) {
			$jp_status       = new \Automattic\Jetpack\Status();
			$is_offline_mode = $jp_status->is_offline_mode();
			if ( $is_offline_mode ) {
				$data[ Constants::SDS_JETPACK_ACCOUNT_PROTECTION_STATUS_KEY ] = 'jp_offline';
				return $data;
			}
		}
		// check if jp is not connected
		if ( class_exists( '\Automattic\Jetpack\Connection\Manager' ) ) {
			$connection = new \Automattic\Jetpack\Connection\Manager();
			if ( ! $connection->is_connected() ) {
				$data[ Constants::SDS_JETPACK_ACCOUNT_PROTECTION_STATUS_KEY ] = 'jp_not_connected';
			}
		}

		$jetpack_modules = \Jetpack::get_active_modules();
		if ( class_exists( '\Automattic\Jetpack\Account_Protection' ) ) {
			if ( in_array( 'account-protection', $jetpack_modules ) ) {
				$data[ Constants::SDS_JETPACK_ACCOUNT_PROTECTION_STATUS_KEY ] = 'active';
			} else {
				$data[ Constants::SDS_JETPACK_ACCOUNT_PROTECTION_STATUS_KEY ] = 'not_enabled';
			}

			// If explicitly disabled via constant, set and short-circuit.
			if ( defined( 'DISABLE_JETPACK_ACCOUNT_PROTECTION' ) && \DISABLE_JETPACK_ACCOUNT_PROTECTION ) {
				$data[ Constants::SDS_JETPACK_ACCOUNT_PROTECTION_STATUS_KEY ] = 'disabled_via_code';
				return $data;
			}
		} else {
			$data[ Constants::SDS_JETPACK_ACCOUNT_PROTECTION_STATUS_KEY ] = 'module_missing';
		}

		return $data;
	}
}


Data_Sync::init();
