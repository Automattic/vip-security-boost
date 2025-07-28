<?php

namespace Automattic\VIP\Security\Utils;

class Users_Query_Utils {
	/**
	 * This function replaces WordPress's potentially unreliable `SELECT FOUND_ROWS()`
	 * with a direct `SELECT COUNT(DISTINCT ID)` query. It dynamically uses the
	 * exact same `FROM` and `WHERE` clauses that the main `WP_User_Query` is using,
	 * ensuring the total user count is always accurate for pagination.
	 *
	 * @param string         $sql   The original SQL query (usually 'SELECT FOUND_ROWS()').
	 * @param \WP_User_Query $query The WP_User_Query instance.
	 * @return string The corrected SQL query for counting users.
	 */
	public static function fix_found_users_query( $sql, $query ) {
		// The WP_User_Query object ($query) has already prepared its SQL clauses for the main query.
		// We can reuse them to build a reliable COUNT query.
		// These properties are populated by the prepare_query() method.
		if ( empty( $query->query_from ) || empty( $query->query_where ) ) {
			// This is unexpected, but as a fallback, return the original SQL to avoid errors.
			return $sql;
		}

		global $wpdb;

		// Build the reliable count query using the same FROM and WHERE clauses as the main query.
        // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$count_sql = "SELECT COUNT(DISTINCT {$wpdb->users}.ID) {$query->query_from} {$query->query_where}";

		return $count_sql;
	}
}
