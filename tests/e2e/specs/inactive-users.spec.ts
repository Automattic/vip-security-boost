import { expect, test } from '@playwright/test';

import { UsersListPage } from '../lib/pages/users-list-page';

test.describe( 'Inactive Users', () => {
	test( 'Display inactive user badge for inactive user', async ( { page } ) => {
		const usersListPage = new UsersListPage( page );
		await usersListPage.visit();
		const inactiveUserBadge = usersListPage.getInactiveUserBadge( 'sbinactiveadmin' );

		// Check that the inactive user badge is visible
		await expect( inactiveUserBadge ).toBeVisible();

		// Verify the badge has both required classes
		await expect( inactiveUserBadge ).toHaveClass( /inactive-user-badge--inactive/ );

		// Verify the badge text content
		await expect( inactiveUserBadge ).toContainText( 'Inactive User' );
	} );
} );
