import { expect, test } from '@playwright/test';

import { UsersListPage } from '../lib/pages/users-list-page';
import { DEFAULT_CONFIG, getSecurityBoostConfigHeaders } from '../lib/security-boost-config-helper';

test.describe( 'Inactive Users', () => {
	test( 'Display inactive user badge for inactive blocked user', async ( { page } ) => {
		const usersListPage = new UsersListPage( page );
		await usersListPage.visit();
		const inactiveUserBadge = usersListPage.getBlockedUserBadge( 'sbinactiveadmin' );
		await expect( usersListPage.getUserRow( 'sbinactiveadmin' ) ).toBeVisible();

		// Verify the badge has both required classes
		await expect( inactiveUserBadge ).toHaveClass( /inactive-user-badge--blocked/ );
		// Check that the inactive user badge is visible
		await expect( inactiveUserBadge ).toBeVisible();

		// Verify the badge text content
		await expect( inactiveUserBadge ).toContainText( 'Blocked: Inactivity' );
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
		const inactiveUserBadge = usersListPage.getInactiveUserBadge( 'sbinactiveadmin' );
		await expect( usersListPage.getUserRow( 'sbinactiveadmin' ) ).toBeVisible();

		// Check that the inactive user badge is visible
		await expect( inactiveUserBadge ).toBeVisible();

		// Verify the badge has both required classes
		await expect( inactiveUserBadge ).toHaveClass( /inactive-user-badge--inactive/ );

		// Verify the badge text content
		await expect( inactiveUserBadge ).toContainText( 'Inactive User' );
	} );
} );
