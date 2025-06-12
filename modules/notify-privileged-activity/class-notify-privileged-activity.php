<?php
namespace Automattic\VIP\Security\NotifyPrivilegedActivity;

use Automattic\VIP\Security\Email\Email;
use Automattic\VIP\Security\Constants;

class Notify_Privileged_Activity {
	const LOG_FEATURE_NAME = 'sb_notify_privileged_activity';

	public static function init(): void {
		if ( is_multisite() ) {
			add_action( 'add_user_to_blog', [ __CLASS__, 'notify_admin_user_creation' ] );
		} else {
			add_action( 'user_register', [ __CLASS__, 'notify_admin_user_creation' ] );
		}

		add_action( 'set_user_role', [ __CLASS__, 'notify_user_promoted_to_admin' ], 10, 3 );
	}

	/**
	 * Handle new user creation.
	 *
	 * @param int $user_id The ID of the newly registered user.
	 */
	public static function notify_admin_user_creation( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			/* Translators: %s: Site name. */
			$subject     = sprintf( __( '[%s] New Administrator Added', 'wpvip' ),
				get_bloginfo( 'name' )
			);
			$email_title = sprintf( __( 'New Administrator Added', 'wpvip' ) );

			self::send_notification( $user, $subject, $email_title, 'privileged-user-created' );
		}
	}

	/**
	 * Handle user promotion to administrator.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $new_role  The new role.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	public static function notify_user_promoted_to_admin( $user_id, $new_role, $old_roles ) {
		// Don't send a notification if the user is being created (empty roles), or if they are already an administrator.
		if ( empty( $old_roles ) || 'administrator' !== $new_role || in_array( 'administrator', $old_roles, true ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Translators: %s: Site name. */
		$subject     = sprintf( __( '[%s] User Promoted to Administrator', 'wpvip' ),
			get_bloginfo( 'name' )
		);
		$email_title = sprintf( __( 'User Promoted to Administrator', 'wpvip' ) );

		self::send_notification( $user, $subject, $email_title, 'privileged-user-promoted' );
	}

	/**
	 * Send the notification email.
	 *
	 * @param \WP_User $user    The user object.
	 * @param string   $subject The email subject.
	 */
	private static function send_notification( $user, $subject, $email_title, $template ) {
		try {
			$admin_email = get_option( 'admin_email' );

			if ( empty( $admin_email ) || ! is_email( $admin_email ) ) {
				return;
			}


			\Automattic\VIP\Logstash\log2logstash(
				[
					'severity' => 'debug',
					'feature'  => self::LOG_FEATURE_NAME,
					'plugin'   => Constants::LOG_PLUGIN_NAME,
					'message'  => 'User granted admin roles (notification type: ' . $template . ') user: ' . $user->user_login,
				]
			);

			Email::send( $user->ID, $admin_email, $subject, $template, [
				'user_login'  => $user->user_login,
				'user_email'  => $user->user_email,
				'user_role'   => 'Administrator',
				'email_title' => $email_title,
				'admin_url'   => admin_url(),
			] );
		} catch ( \Exception $e ) {
			\Automattic\VIP\Logstash\log2logstash(
				[
					'severity' => 'error',
					'feature'  => self::LOG_FEATURE_NAME,
					'plugin'   => Constants::LOG_PLUGIN_NAME,
					'message'  => 'Failed to notify admin user creation (type: ' . $template . ') user: ' . $user->user_login . ' - error: ' . $e->getMessage(),
				]
			);
		}
	}
}
