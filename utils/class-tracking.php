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
	const PIXEL         = 'https://pixel.wp.com/t.gif';
	const TRACKS_PREFIX = 'vip_security_boost';
	const STATS_PREFIX  = 'vip-security-boost';

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

	public function mfa_display( $filter_enabled ) {
		$stats_name = self::STATS_PREFIX . '-mfa-display' . ( $filter_enabled ? '-filtered' : '' );
		self::record_stats( $stats_name );
	}

	public function mfa_filter_click( $filter_type ) {
		self::record_tracks_event( 'mfa_filter_click', [ 'filter_type' => $filter_type ] );
		self::record_stats( self::STATS_PREFIX . '-mfa-filter-click' );
	}

	public function mfa_sorting( $sort_column, $sort_order ) {
		self::record_tracks_event( 'mfa_sorting', [
			'sort_column' => $sort_column,
			'sort_order'  => $sort_order,
		] );
		self::record_stats( self::STATS_PREFIX . '-mfa-sorting' );
	}

	public function blocked_users_view() {
		self::record_tracks_event( 'blocked_users_view' );
		self::record_stats( self::STATS_PREFIX . '-blocked-users-view' );
	}

	public function user_unblock( $user_id, $user_role ) {
		self::record_tracks_event( 'user_unblock', [
			'user_role'   => $user_role,
			'has_user_id' => ! empty( $user_id ),
		] );
		self::record_stats( self::STATS_PREFIX . '-user-unblock' );
	}

	public function privileged_email_sent( $email_type, $recipient_role ) {
		$stats_name = self::STATS_PREFIX . '-privileged-email-sent';
		if ( ! empty( $email_type ) ) {
			$stats_name .= '-' . $email_type;
		}
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

	/**
	 * Record a Tracks event
	 *
	 * @param string $event_name Event name.
	 * @param array  $event_properties Event properties.
	 * @return bool True on success, false on failure.
	 */
	private static function record_tracks_event( $event_name, $event_properties = [] ): bool {
		// Skip if we're running tests
		if ( 'local' === constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			Logger::info( 'vip-security-boost', 'Skipping tracks event in local environment', [
				'event_name' => $event_name,
			] );
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

		$default_properties = [
			'plugin_name' => Constants::LOG_PLUGIN_NAME,
			'site_id'     => defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : 0,
		];

		$merged_properties = array_merge( $default_properties, $event_properties, $data );

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
	private static function record_stats( $stat_name, $value = 1 ) {
		// Only track stats in production
		Logger::info( 'vip-security-boost', 'Record stat in non-production', [
			'stat_name' => $stat_name,
			'value'     => $value,
		] );

		if ( function_exists( '\Automattic\VIP\Stats\send_pixel' ) ) {
			try {
				\Automattic\VIP\Stats\send_pixel( [ $stat_name => $value ] );
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
			return self::TRACKS_PREFIX . '_' . constant( 'VIP_GO_APP_ENVIRONMENT' );
		}

		return self::TRACKS_PREFIX;
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
		$instance = new self();
		
		add_action( 'vip_security_mfa_display', [ $instance, 'mfa_display' ], 10, 1 );
		add_action( 'vip_security_mfa_filter_click', [ $instance, 'mfa_filter_click' ], 10, 1 );
		add_action( 'vip_security_mfa_sorting', [ $instance, 'mfa_sorting' ], 10, 2 );
		add_action( 'vip_security_blocked_users_view', [ $instance, 'blocked_users_view' ] );
		add_action( 'vip_security_user_unblock', [ $instance, 'user_unblock' ], 10, 2 );
		add_action( 'vip_security_privileged_email_sent', [ $instance, 'privileged_email_sent' ], 10, 2 );
	}
}
