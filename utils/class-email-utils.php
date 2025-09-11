<?php
namespace Automattic\VIP\Security\Utils;

use Automattic\VIP\Security\Constants;

class Email_Utils {
	/** Extract lowercase domain from a valid email; null if invalid */
	public static function domain( $email ): ?string {
		if ( ! is_string( $email ) ) {
			return null;
		}
		$normalized = strtolower( trim( $email ) );
		if ( ! is_email( $normalized ) ) {
			return null;
		}
		$part = strrchr( $normalized, '@' ); // "@domain" or false
		if ( false === $part ) {
			return null;
		}
		return substr( $part, 1 ); // strip leading "@"
	}

	/** True if the email is on an Automattic/VIP-owned domain (filterable) */
	public static function is_a8c_owned( $email ): bool {
		$domain = self::domain( $email );
		if ( null === $domain ) {
			return false;
		}

		$allowed = apply_filters(
			'vip_security_jetpack_owner_email_domains',
			defined( Constants::class . '::A8C_EMAIL_DOMAINS' )
				? Constants::A8C_EMAIL_DOMAINS
				: [ 'wpvip.com', 'automattic.com' ]
		);

		$allowed = array_map(
			static fn( $d ) => strtolower( trim( (string) $d ) ),
			(array) $allowed
		);

		return in_array( $domain, $allowed, true );
	}
}
