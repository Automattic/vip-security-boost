<?php

namespace Automattic\VIP\Security\SecondPlugin;

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
		// Initialize the integration
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
		Logger::log( [
			'feature'  => 'auth',
			'message'  => 'Second plugin test',
			'severity' => 'info',
			'site_id'  => get_current_blog_id(),
		] );
	}
}
