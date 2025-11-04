import { expect, test } from '@playwright/test';

import { UsersListPage } from '../lib/pages/users-list-page';
import { DEFAULT_CONFIG, getSecurityBoostConfigHeaders } from '../lib/security-boost-config-helper';

test.describe( 'Inactive Users', () => {
	const INACTIVE_ADMIN_USERNAME = 'sbinactiveadmin';
	const BLOCKED_BADGE_CLASS = /inactive-user-badge--blocked/;
	const INACTIVE_BADGE_CLASS = /inactive-user-badge--inactive/;
	const INACTIVE_CONTRIBUTOR_USERNAME = 'sbinactivecontributor';
	test( 'Display inactive user badge for inactive blocked user', async ( { page } ) => {
		const usersListPage = new UsersListPage( page );
		await usersListPage.visit();
		await hasBlockedBadge( usersListPage, INACTIVE_ADMIN_USERNAME );
		await hasBlockedBadge( usersListPage, INACTIVE_CONTRIBUTOR_USERNAME );
	} );
	test( 'Display inactive user badge for inactive user', async ( { page, context } ) => {
		await context.setExtraHTTPHeaders(
			getSecurityBoostConfigHeaders( {
				module_configs: {
					...DEFAULT_CONFIG.module_configs,
					'inactive-users': {
						...DEFAULT_CONFIG.module_configs[ 'inactive-users' ],
						mode: 'REPORT',
					},
				},
			} ),
		);
		const usersListPage = new UsersListPage( page );
		await usersListPage.visit();
		await hasInactiveBadge( usersListPage, INACTIVE_ADMIN_USERNAME );
		await hasInactiveBadge( usersListPage, INACTIVE_CONTRIBUTOR_USERNAME );
	} );

	async function hasInactiveBadge( usersListPage: UsersListPage, username: string ) {
		const inactiveUserBadge = usersListPage.getInactiveUserBadge( username );
		await expect( usersListPage.getUserRow( username ) ).toBeVisible();

		// Check that the inactive user badge is visible
		await expect( inactiveUserBadge ).toBeVisible();

		// Verify the badge has both required classes
		await expect( inactiveUserBadge ).toHaveClass( INACTIVE_BADGE_CLASS );

		// Verify the badge text content
		await expect( inactiveUserBadge ).toContainText( 'Inactive User' );
	}

	async function hasBlockedBadge( usersListPage: UsersListPage, username: string ) {
		const blockedUserBadge = usersListPage.getBlockedUserBadge( username );
		await expect( usersListPage.getUserRow( username ) ).toBeVisible();

		// Check that the blocked user badge is visible
		await expect( blockedUserBadge ).toBeVisible();

		// Verify the badge has both required classes
		await expect( blockedUserBadge ).toHaveClass( BLOCKED_BADGE_CLASS );

		// Verify the badge text content
		await expect( blockedUserBadge ).toContainText( 'Blocked: Inactivity' );
	}
} );
