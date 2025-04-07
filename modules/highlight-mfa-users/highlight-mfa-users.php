<?php
namespace Automattic\VIP\Security\MFAUsers;

class Highlight_MFA_Users {
	const MFA_SKIP_USER_IDS_OPTION_KEY = 'vip_security_mfa_skip_user_ids';

	public static function init() {
		// Feature is always active unless specific users are skipped via option.
		add_action( 'admin_notices', [ __CLASS__, 'display_mfa_disabled_notice' ] );
		add_action( 'pre_get_users', [ __CLASS__, 'filter_users_by_mfa_status' ] );
	}

	/**
	 * Display an admin notice on the Users page showing the count of users with MFA disabled.
	 */
	public static function display_mfa_disabled_notice() {
		// TODO: if Two_Factor_Core is disabled, do we display all admin users?
		if ( ! class_exists( 'Two_Factor_Core' ) ) {
			return;
		}

		// Only show on the main users list table
		$screen = get_current_screen();
		if ( ! $screen || 'users' !== $screen->id ) {
			return;
		}


		$skipped_user_ids = get_option( self::MFA_SKIP_USER_IDS_OPTION_KEY, [] );
		if ( ! is_array( $skipped_user_ids ) ) {
			$skipped_user_ids = [];
		}

		// Query for administrator user IDs, excluding skipped ones
		$args = [
			'role'    => 'administrator',
			'fields'  => 'ID',
			'exclude' => array_merge( $skipped_user_ids, [1] ), // Exclude skipped users AND user ID 1
			'number'  => -1, // Get all relevant users
		];
		$user_query = new \WP_User_Query( $args );
		$user_ids   = $user_query->get_results();

		$mfa_disabled_count = 0;
		foreach ( $user_ids as $user_id ) {
			// Use the reliable check from the Two Factor plugin
			if ( ! \Two_Factor_Core::is_user_using_two_factor( $user_id ) ) {
				$mfa_disabled_count++;
			}
		}

		if ( $mfa_disabled_count > 0 ) {
			$filter_url = add_query_arg( 'filter_mfa_disabled', '1', admin_url( 'users.php' ) );
			printf(
				'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
				sprintf(
					_n(
						'There is %d administrator with MFA disabled.',
						'There are %d administrators with MFA disabled.',
						$mfa_disabled_count,
						'wpvip'
					),
					number_format_i18n( $mfa_disabled_count )
				),
				esc_url( $filter_url ),
				esc_html__( 'Filter list to show these users.', 'wpvip' )
			);
		}
	}

	/**
		* Modify the user query on the Users page to filter by MFA status if requested.
		* @param \WP_User_Query $query The WP_User_Query instance (passed by reference).
		*/
	public static function filter_users_by_mfa_status( $query ) {
		global $pagenow;
		if ( is_admin() && 'users.php' === $pagenow && isset( $_GET['filter_mfa_disabled'] ) && '1' === $_GET['filter_mfa_disabled'] ) {
			
			// Ensure we don't break other meta queries
			$meta_query = $query->get( 'meta_query' );
			if ( ! is_array( $meta_query ) ) {
				$meta_query = [];
			}

			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => '_two_factor_enabled_providers',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_two_factor_enabled_providers',
					'value'   => 'a:0:{}',
					'compare' => '=',
				],
				[
					'key'     => '_two_factor_enabled_providers',
					'value'   => '',
					'compare' => '=',
				],
			];
			$query->set( 'role__in', ['administrator'] );
			$query->set( 'meta_query', $meta_query );

			// Exclude skipped users
			$skipped_user_ids = get_option( self::MFA_SKIP_USER_IDS_OPTION_KEY, [] );
			if ( ! is_array( $skipped_user_ids ) ) {
				$skipped_user_ids = [];
			}
			if ( ! empty( $skipped_user_ids ) ) {
				$exclude_ids = $query->get( 'exclude' );
				if ( ! is_array( $exclude_ids ) ) {
					$exclude_ids = [];
				}
				$query->set( 'exclude', array_unique( array_merge( $exclude_ids, $skipped_user_ids ) ) );
			}
		}
	}
}
Highlight_MFA_Users::init();