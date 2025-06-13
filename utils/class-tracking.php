<?php

namespace Automattic\VIP\Security\Utils;

use Automattic\VIP\Security\Constants;

class Tracking {

	const TRACKS_PREFIX = 'vip_security_boost_';
	const STATS_PREFIX  = 'vip-security-boost-';

	public static function track_mfa_display( $filter_enabled = false ) {
		$stats_name = self::STATS_PREFIX . 'mfa-display';
		if ( $filter_enabled ) {
			$stats_name .= '-filtered';
		}

		error_log( 'VIP Security Tracking: MFA Display - ' . $stats_name );
		self::record_stats( $stats_name );
	}

	public static function track_mfa_filter_click( $filter_type = '' ) {
		$event_name       = self::TRACKS_PREFIX . 'mfa_filter_click';
		$event_properties = [
			'filter_type' => $filter_type,
		];

		error_log( 'VIP Security Tracking: MFA Filter Click - ' . $event_name . ' (filter_type: ' . $filter_type . ')' );
		self::record_tracks_event( $event_name, $event_properties );
		self::record_stats( self::STATS_PREFIX . 'mfa-filter-click' );
	}

	public static function track_mfa_sorting( $sort_column = '', $sort_order = '' ) {
		$event_name       = self::TRACKS_PREFIX . 'mfa_sorting';
		$event_properties = [
			'sort_column' => $sort_column,
			'sort_order'  => $sort_order,
		];

		error_log( 'VIP Security Tracking: MFA Sorting - ' . $event_name . ' (column: ' . $sort_column . ', order: ' . $sort_order . ')' );
		self::record_tracks_event( $event_name, $event_properties );
		self::record_stats( self::STATS_PREFIX . 'mfa-sorting' );
	}

	public static function track_blocked_users_view() {
		$event_name = self::TRACKS_PREFIX . 'blocked_users_view';

		error_log( 'VIP Security Tracking: Blocked Users View - ' . $event_name );
		self::record_tracks_event( $event_name );
		self::record_stats( self::STATS_PREFIX . 'blocked-users-view' );
	}

	public static function track_user_unblock( $user_id = 0, $user_role = '' ) {
		$event_name       = self::TRACKS_PREFIX . 'user_unblock';
		$event_properties = [
			'user_role'   => $user_role,
			'has_user_id' => ! empty( $user_id ),
		];

		error_log( 'VIP Security Tracking: User Unblock - ' . $event_name . ' (role: ' . $user_role . ', has_user_id: ' . ( ! empty( $user_id ) ? 'yes' : 'no' ) . ')' );
		self::record_tracks_event( $event_name, $event_properties );
		self::record_stats( self::STATS_PREFIX . 'user-unblock' );
	}

	public static function track_privileged_email_sent( $email_type = '', $recipient_role = '' ) {
		$stats_name = self::STATS_PREFIX . 'privileged-email-sent';
		if ( ! empty( $email_type ) ) {
			$stats_name .= '-' . $email_type;
		}

		error_log( 'VIP Security Tracking: Privileged Email Sent - ' . $stats_name . ' (type: ' . $email_type . ', role: ' . $recipient_role . ')' );
		self::record_stats( $stats_name );

		\Automattic\VIP\Logstash\log2logstash([
			'severity' => 'info',
			'feature'  => 'vip_security_email_tracking',
			'plugin'   => Constants::LOG_PLUGIN_NAME,
			'message'  => 'Privileged user email notification sent',
			'extra'    => [
				'email_type'     => $email_type,
				'recipient_role' => $recipient_role,
			],
		]);
	}

	private static function record_tracks_event( $event_name, $event_properties = [] ) {
		if ( ! function_exists( 'wpcom_tracks_record_event' ) ) {
			error_log( 'VIP Security Tracking: wpcom_tracks_record_event function not available for ' . $event_name );
			return;
		}

		$default_properties = [
			'plugin_name' => Constants::LOG_PLUGIN_NAME,
			'site_id'     => defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : 0,
		];

		$properties = array_merge( $default_properties, $event_properties );
		error_log( 'VIP Security Tracking: Recording tracks event - ' . $event_name . ' with properties: ' . wp_json_encode( $properties ) );
		wpcom_tracks_record_event( get_current_user_id(), $event_name, $properties );
	}

	private static function record_stats( $stat_name, $value = 1 ) {
		if ( function_exists( '\Automattic\VIP\Stats\send_pixel' ) ) {
			error_log( 'VIP Security Tracking: Recording stat - ' . $stat_name . ' with value: ' . $value );
			\Automattic\VIP\Stats\send_pixel( [ $stat_name => $value ] );
		} else {
			error_log( 'VIP Security Tracking: VIP Stats send_pixel function not available for ' . $stat_name );
		}
	}
}
