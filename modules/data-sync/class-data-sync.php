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
		add_filter( 'vip_site_details_index_data', [ __CLASS__, 'add_two_factor_enforcement_status_to_sds_payload' ] );
	}


	/**
	 * Add the two-factor enforcement status details to the Site Details Service (SDS) payload.
	 *
	 * The function augments the incoming `$data` array by injecting a new element under the
	 * standard SDS data key (`Constants::SDS_DATA_KEY`). The resulting payload section has the
	 * following structure:
	 *
	 * [ Constants::SDS_DATA_KEY ] => [
	 *     'two_factor_status' => [
	 *         'is_enforced_globally'         => bool, // `wpcom_vip_is_two_factor_forced` hooked to `__return_true`
	 *         'is_not_enforced_globally'     => bool, // `wpcom_vip_is_two_factor_forced` hooked to `__return_false`
	 *         'has_two_factor_forced_filter' => bool, // Any filter present on `wpcom_vip_is_two_factor_forced`
	 *         'is_entirely_disabled'         => bool, // 2FA disabled via `wpcom_vip_enable_two_factor` returning false
	 *         'has_enable_two_factor_filter' => bool, // Any filter present on `wpcom_vip_enable_two_factor`
	 *     ],
	 * ]
	 *
	 * This mirrors the array returned by {@see self::get_two_factor_enforcement_status()} so that
	 * consumers of the SDS payload can introspect the enforcement configuration without needing to
	 * call WordPress-level helpers during data processing.
	 *
	 * @return array Modified SDS payload including the `two_factor_status` information.
	 */
	public static function add_two_factor_enforcement_status_to_sds_payload( $data ) {
		if ( ! isset( $data[ Constants::SDS_DATA_KEY ] ) ) {
			$data[ Constants::SDS_DATA_KEY ] = array();
		}
		try {
			$data[ Constants::SDS_DATA_KEY ]['two_factor_status'] = self::get_two_factor_enforcement_status();
		} catch ( \Exception $e ) {
			Logger::error(
				self::LOG_FEATURE_NAME,
				'Error adding two factor enforcement status to SDS payload: ' . $e->getMessage()
			);
		}
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
		// Please note that detecting a filter is no exact science.
		// SDS data is retrieved during CRON so some of these filters might be added only for logged in users, still we try our best to detect them.
		$filters = [
			// return wpcom_vip_is_two_factor_forced status
			'is_enforced_globally'         => \has_filter( 'wpcom_vip_is_two_factor_forced', '__return_true' ) !== false,
			'is_not_enforced_globally'     => \has_filter( 'wpcom_vip_is_two_factor_forced', '__return_false' ) !== false,
			'has_two_factor_forced_filter' => has_filter( 'wpcom_vip_is_two_factor_forced' ) !== false,
			// return wpcom_vip_enable_two_factor status
			'is_entirely_disabled'         => \has_filter( 'wpcom_vip_enable_two_factor', '__return_false' ) !== false || apply_filters( 'wpcom_vip_enable_two_factor', true ) === false,
			'has_enable_two_factor_filter' => \has_filter( 'wpcom_vip_enable_two_factor' ) !== false,
		];
		return $filters;
	}
}

Data_Sync::init();
