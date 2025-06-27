<?php

namespace Automattic\VIP\Security\Utils;

use Automattic\VIP\Security\Constants;

class Logger {
	public static $logged_entries = [];
	/**
	 * Log data to both error_log (non-production) and Logstash
	 *
	 * @param array $data Log data with required fields: feature, message, severity
	 * @return void
	 */
	public static function log( array $data ): void {
		self::$logged_entries[] = $data;
	}

	/**
	 * Log an info message
	 *
	 * @param string $feature Feature name
	 * @param string $message Log message
	 * @param array  $extra Extra data
	 */
	public static function info( string $feature, string $message, array $extra = [] ): void {
		self::log( [
			'severity' => 'info',
			'feature'  => $feature,
			'message'  => $message,
			'plugin'   => Constants::LOG_PLUGIN_NAME,
			'extra'    => $extra,
		] );
	}

	/**
	 * Log a warning message
	 *
	 * @param string $feature Feature name
	 * @param string $message Log message
	 * @param array  $extra Extra data
	 */
	public static function warning( string $feature, string $message, array $extra = [] ): void {
		self::log( [
			'severity' => 'warning',
			'feature'  => $feature,
			'message'  => $message,
			'plugin'   => Constants::LOG_PLUGIN_NAME,
			'extra'    => $extra,
		] );
	}

	/**
	 * Log an error message
	 *
	 * @param string $feature Feature name
	 * @param string $message Log message
	 * @param array  $extra Extra data
	 */
	public static function error( string $feature, string $message, array $extra = [] ): void {
		self::log( [
			'severity' => 'error',
			'feature'  => $feature,
			'message'  => $message,
			'plugin'   => Constants::LOG_PLUGIN_NAME,
			'extra'    => $extra,
		] );
	}

	public static function set_entries( array $entries ): void {
		static::$logged_entries = [];
	}

	public static function wp_debug_log( array $entry ): void {
		self::$logged_entries[] = $entry;
	}

	public static function get_entries(): array {
		return static::$logged_entries;
	}
}
