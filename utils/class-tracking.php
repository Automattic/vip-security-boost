<?php
/**
 * Send Tracks events and stats for VIP Security Boost plugin.
 */

namespace Automattic\VIP\Security\Utils;

use Automattic\VIP\Security\Constants;

class Tracking {

	/**
	 * Pixel URL.
	 */
	const PIXEL  = 'https://pixel.wp.com/t.gif';
	const PREFIX = 'vip_security_boost';

	private Counter $mfa_display_counter;
	private Counter $mfa_filter_click_counter;
	private Counter $mfa_sorting_counter;
	private Counter $blocked_users_view_counter;
	private Counter $user_unblock_counter;
	private Counter $privileged_email_sent_counter;

	public function initialize( RegistryInterface $registry ): void {
		$this->mfa_display_counter = $registry->getOrRegisterCounter(
			'vip_security_boost',
			'mfa_display_total',
			'Number of MFA display views',
			[ 'filtered' ]
		);

		$this->mfa_filter_click_counter = $registry->getOrRegisterCounter(
			'vip_security_boost',
			'mfa_filter_click_total',
			'Number of MFA filter clicks',
			[ 'filter_type' ]
		);

		$this->mfa_sorting_counter = $registry->getOrRegisterCounter(
			'vip_security_boost',
			'mfa_sorting_total',
			'Number of MFA sorting actions',
			[ 'sort_column', 'sort_order' ]
		);

		$this->blocked_users_view_counter = $registry->getOrRegisterCounter(
			'vip_security_boost',
			'blocked_users_view_total',
			'Number of blocked users view accesses',
			[]
		);

		$this->user_unblock_counter = $registry->getOrRegisterCounter(
			'vip_security_boost',
			'user_unblock_total',
			'Number of user unblock actions',
			[ 'user_role' ]
		);

		$this->privileged_email_sent_counter = $registry->getOrRegisterCounter(
			'vip_security_boost',
			'privileged_email_sent_total',
			'Number of privileged activity emails sent',
			[ 'email_type', 'recipient_role' ]
		);
	}

	public static function mfa_display( $filter_enabled ) {
		self::record_stats( 'mfa-display' . ( $filter_enabled ? '-filtered' : '' ) );
	}

	public static function mfa_filter_click( $filter_type ) {
		self::record_tracks_event( 'mfa_filter_click', [ 'filter_type' => $filter_type ] );
		self::record_stats( 'mfa-filter-click' );
	}

	public static function mfa_sorting( $sort_column, $sort_order ) {
		self::record_tracks_event( 'mfa_sorting', [
			'sort_column' => $sort_column,
			'sort_order'  => $sort_order,
		] );
		self::record_stats( 'mfa-sorting' );
	}

	public static function blocked_users_view() {
		self::record_tracks_event( 'blocked_users_view' );
		self::record_stats( 'blocked-users-view' );
	}

	public static function user_unblock( $user_id, $user_role ) {
		self::record_tracks_event( 'user_unblock', [
			'user_role'   => $user_role,
			'has_user_id' => ! empty( $user_id ),
		] );
		self::record_stats( 'user-unblock' );
	}

	public static function privileged_email_sent( $email_type, $recipient_role ) {
		self::record_tracks_event( 'privileged_email_sent', [
			'email_type'     => $email_type,
			'recipient_role' => $recipient_role,
		] );
		self::record_stats( 'privileged-email-sent' );

		Logger::info( 'vip-security-boost', 'Privileged user email notification sent', [
			'email_type'     => $email_type,
			'recipient_role' => $recipient_role,
		] );
	}

	/**
	 * Record a Tracks event
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_properties Event properties.
	 * @return bool True on success, false on failure.
	 */
	private static function record_tracks_event( $event_name, $event_properties = [] ): bool {
		// Skip tracking in test environments
		if ( 'test' === constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			return false;
		}

		$event_name_prefixed = $event_name;
		$prefix              = self::get_prefix();
		if ( ! str_starts_with( $event_name, $prefix ) ) {
			$event_name_prefixed = $prefix . '_' . $event_name;
		}
		$data = [
			'_en' => $event_name_prefixed,
			'_et' => self::build_timestamp(),
		];

		// Add user tracking data
		//phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__,WordPressVIPMinimum.Security.PHPFilterFunctions.MissingSecondParameter
		$data['_via_ua'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? filter_var( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		//phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__,WordPressVIPMinimum.Security.PHPFilterFunctions.MissingSecondParameter
		$data['_via_ip'] = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		//phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.MissingSecondParameter
		$data['_lg'] = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? filter_var( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';

		// Get user identity
		$identity = self::get_identity( get_current_user_id() );

		$default_properties = [
			'plugin_name' => Constants::LOG_PLUGIN_NAME,
			'site_id'     => defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : 0,
		];

		$merged_properties = array_merge( $default_properties, $event_properties, $data, $identity );

		Logger::info( 'vip-security-boost', 'Tracks event recorded', [
			'event_name' => $event_name_prefixed,
			'properties' => $merged_properties,
		] );

		$pixel = esc_url_raw( self::PIXEL . '?' . http_build_query( $merged_properties ) );
		return self::record_pixel( $pixel );
	}

	/**
	 * Record stats using VIP Stats
	 *
	 * @param string $stat_name Stat name.
	 * @param mixed  $value Stat value.
	 */
	private static function record_stats( $stat_name ) {
		// We're tracking the stats in production only
		if ( 'local' === constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			Logger::info( 'vip-security-boost', 'Bumping stats for https://mc.a8c.com/s/' . self::PREFIX . "/{$stat_name}", [
				'stat_name' => $stat_name,
			] );
			return;
		}

		if ( function_exists( '\Automattic\VIP\Stats\send_pixel' ) ) {
			try {
				\Automattic\VIP\Stats\send_pixel( [ self::PREFIX => $stat_name ] );
			} catch ( \Exception $e ) {
				Logger::error( 'vip-security-boost', 'Stats recording failed', [
					'stat_name' => $stat_name,
					'error'     => $e->getMessage(),
				] );
			}
		} else {
			Logger::warning( 'vip-security-boost', 'VIP Stats send_pixel function not available', [
				'stat_name' => $stat_name,
			] );
		}
	}

	/**
	 * Get prefix with environment suffix for non-production
	 *
	 * @return string Prefixed event name.
	 */
	protected static function get_prefix(): string {
		if ( 'production' !== constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			return self::PREFIX . '_dev';
		}

		return self::PREFIX;
	}

	/**
	 * Get the identity of the current user for Tracks.
	 *
	 * @param int $user_id User ID.
	 * @return array Identity properties.
	 */
	protected static function get_identity( $user_id ): array {
		$identify = [];

		if ( $user_id && 0 !== $user_id ) {
			$current_user    = get_user_by( 'ID', $user_id );
			$identify['_ui'] = hash( 'sha256', $user_id );
			$identify['_ut'] = hash( 'sha256', strtolower( $current_user->user_email ) );
		}

		return $identify;
	}

	/**
	 * Synchronously request the pixel.
	 *
	 * @param string $pixel Pixel URL and query string.
	 * @return bool Always returns true.
	 */
	protected static function record_pixel( $pixel ): bool {
		// Add the Request Timestamp and URL terminator just before the HTTP request
		$pixel .= '&_rt=' . self::build_timestamp() . '&_=_';

		vip_safe_wp_remote_get(
			$pixel,
			[
				'blocking'    => false,
				'redirection' => 2,
				'httpversion' => '1.1',
				'timeout'     => 1,
			]
		);

		return true;
	}

	/**
	 * Create a timestamp representing milliseconds since 1970-01-01
	 *
	 * @return string A string representing a timestamp.
	 */
	protected static function build_timestamp(): string {
		$ts = round( microtime( true ) * 1000 );

		return number_format( $ts, 0, '', '' );
	}

	/**
	 * Set up action hooks for tracking events (similar to VIP Auth pattern)
	 */
	public static function setup_action_hooks() {
		add_action( 'vip_security_mfa_display', [ __CLASS__, 'mfa_display' ], 10, 1 );
		add_action( 'vip_security_mfa_filter_click', [ __CLASS__, 'mfa_filter_click' ], 10, 1 );
		add_action( 'vip_security_mfa_sorting', [ __CLASS__, 'mfa_sorting' ], 10, 2 );
		add_action( 'vip_security_blocked_users_view', [ __CLASS__, 'blocked_users_view' ] );
		add_action( 'vip_security_user_unblock', [ __CLASS__, 'user_unblock' ], 10, 2 );
		add_action( 'vip_security_privileged_email_sent', [ __CLASS__, 'privileged_email_sent' ], 10, 2 );
	}
}
