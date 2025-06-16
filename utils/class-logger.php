<?php
/**
 * Generic logger for VIP Security Boost plugin.
 */

namespace Automattic\VIP\Security\Utils;

class Logger {
	/**
	 * Log data to both error_log (non-production) and Logstash
	 *
	 * @param array $data Log data with required fields: feature, message, severity
	 * @return void
	 */
	public static function log( array $data ): void {

		// Auto-detect file and line if not provided
		if ( ! isset( $data['file'] ) && ! isset( $data['line'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$backtrace  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
			$base_index = 0;
			
			if ( isset( $backtrace[ $base_index ]['file'] ) ) {
				$data['file'] = $backtrace[ $base_index ]['file'];
			}

			if ( isset( $backtrace[ $base_index ]['line'] ) ) {
				$data['line'] = $backtrace[ $base_index ]['line'];
			}

			// If caller wants to log their caller too
			if ( isset( $data['debug_function_caller'] ) ) {
				$function_caller_index = 1;
				if ( isset( $backtrace[ $function_caller_index ]['file'] ) ) {
					$data['extra']['caller_file'] = $backtrace[ $function_caller_index ]['file'];
				}

				if ( isset( $backtrace[ $function_caller_index ]['line'] ) ) {
					$data['extra']['caller_line'] = $backtrace[ $function_caller_index ]['line'];
				}
			}
		}

		// Log to error_log if not in production and not during testing
		$is_testing = ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'test' === constant( 'VIP_GO_APP_ENVIRONMENT' ) );
		if ( ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'production' !== constant( 'VIP_GO_APP_ENVIRONMENT' ) ) && ! $is_testing ) {
			$log_message = sprintf(
				'[VIP Security Boost] %s: %s',
				strtoupper( $data['severity'] ?? 'info' ),
				$data['message'] ?? 'No message'
			);
			
			if ( isset( $data['extra'] ) && ! empty( $data['extra'] ) ) {
				$log_message .= ' | Extra: ' . wp_json_encode( $data['extra'] );
			}
			
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $log_message );
		}

		// Send to Logstash
		\Automattic\VIP\Logstash\Logger::log2logstash( $data );
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
			'extra'    => $extra,
		] );
	}
}
