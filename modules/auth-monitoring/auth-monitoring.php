<?php
/*
 * Plugin Name: VIP Auth Monitoring Plugin
 * Description: Monitors authentication events.
 * Version: 1.0.0
 * Author: Automattic
 * License: MIT
 * Text Domain: vip-security-boost
 * Domain Path: /lang
 */
namespace Automattic\VIP\Security\AuthMonitoring;

use Automattic\VIP\Security\Utils\Logger;

final class Plugin {
	/** @var self|null */
	private static $instance;

	// @codeCoverageIgnoreStart
	// This code is executed in bootstrap.php, before PHPUnit initializes test coverage
	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	public function init(): void {
		// Initialize the plugin
		add_action( 'wp_login', [ $this, 'on_wp_login' ], 10, 2 );
	}
	// @codeCoverageIgnoreEnd

	/**
	 * Logs the user login event.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       WP_User object of the logged-in user.
	 */
	public function on_wp_login( string $user_login, \WP_User $user ): void {
		// Gather login information
		$username = $user_login;
		$user_id  = $user->ID;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
		$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

		Logger::log( [
			'feature'  => 'auth',
			'message'  => 'User logged in',
			'severity' => 'info',
			'extra'    => [
				'username' => $username,
				'user_id'  => $user_id,
				'user_ip'  => $user_ip,
			],
			'site_id'  => get_current_blog_id(),
		] );
	}
}

Plugin::get_instance();