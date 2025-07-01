<?php
namespace Automattic\VIP\Security\InactiveUsers;

use Automattic\VIP\Security\InactiveUsers\Inactive_Users;

/**
 * This is a dedicated function to help testing the change in the fallback_release_date
 */
class Inactive_Users_Test extends Inactive_Users {

	public static function get_fallback_release_date_timestamp() {
		return strtotime( '2025-01-01' );
	}
}
