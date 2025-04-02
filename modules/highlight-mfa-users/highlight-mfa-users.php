<?php
namespace Automattic\VIP\Security\MFAUsers;

class Highlight_MFA_Users {
	private static $mode;
	const MFA_SKIP_USER_IDS_OPTION_KEY = 'vip_security_mfa_skip_user_ids';
	public static function init() {
		if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
			error_log( 'VIP_SECURITY_BOOST_CONFIGS not defined' );
			return;
		}

		$configs             = constant( 'VIP_SECURITY_BOOST_CONFIGS' );
		$highlight_mfa_users = $configs['highlight_mfa_users'] ?? [];
		self::$mode          = $highlight_mfa_users['mode'] ?? 'REPORT';

		if ( in_array( self::$mode, array( 'REPORT', 'HIGHLIGHT' ) ) ) {
			add_filter( 'wpmu_users_columns', [ __CLASS__, 'add_mfa_status_column_head' ] );
			add_filter( 'manage_users_columns', [ __CLASS__, 'add_mfa_status_column_head' ] );
			add_filter( 'manage_users_custom_column', [ __CLASS__, 'add_mfa_status_column_content' ], 10, 3 );
		}
	}
	
	public static function add_mfa_status_column_head( $columns ) {
		$columns['mfa_status'] = __( 'MFA Status', 'wpvip' );
		return $columns;
	}

	public static function add_mfa_status_column_content( $output, $column_name, $user_id ) {
		if ( 'mfa_status' !== $column_name ) {
			return $output;
		}

		// Check if user should be skipped via filter
		/**
		 * Filters whether to skip the MFA status check for a specific user via code.
		 * Returning true will prevent the "Enabled" or "Disabled" status from being displayed for this user.
		 * @param bool $skip    Whether to skip the check. Default false.
		 * @param int  $user_id The ID of the user being checked.
		 */
		$skip_via_filter = apply_filters( 'vip_security_mfa_skip_user_check', false, $user_id );

		if ( $skip_via_filter ) {
			return $output;
		}

		// Check if user should be skipped via option
		$skipped_user_ids = get_option( self::MFA_SKIP_USER_IDS_OPTION_KEY, [] );
		if ( ! is_array( $skipped_user_ids ) ) {
			$skipped_user_ids = [];
		}

		if ( in_array( $user_id, $skipped_user_ids, true ) ) {
			return $output;
		}

		// Proceed with MFA check if not skipped
		$mfa_enabled = false;

		if ( class_exists( 'Two_Factor_Core' ) && \Two_Factor_Core::is_user_using_two_factor( $user_id ) ) {
			$mfa_enabled = true;
		} else {
			// $mfa_enabled = get_user_meta( $user_id, 'my_custom_mfa_status_meta_key', true );
		}


		if ( $mfa_enabled ) {
			return __( 'Enabled', 'wpvip' );
		} else {
			return sprintf( '<strong style="color: red;">%s</strong>', __( 'Disabled', 'wpvip' ) );
		}
	}
}
Highlight_MFA_Users::init();