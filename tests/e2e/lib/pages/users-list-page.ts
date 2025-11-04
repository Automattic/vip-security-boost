import type { Locator, Page } from '@playwright/test';

const selectors = {
	usersTable: 'table.wp-list-table',
	userRowByUsername: ( username: string ) => `text=${ username }`,
	inactiveUserBadge: '.inactive-user-badge--inactive',
	blockedUserBadge: '.inactive-user-badge.inactive-user-badge--blocked',
};

export class UsersListPage {
	private readonly page: Page;
	public readonly usersTable: Locator;

	/**
	 * Constructs an instance of the component.
	 *
	 * @param { Page } page The underlying page
	 */
	constructor( page: Page ) {
		this.page = page;
		this.usersTable = page.locator( selectors.usersTable );
	}

	/**
	 * Navigate to Users List page
	 */
	public visit(): Promise<unknown> {
		return this.page.goto( '/wp-admin/users.php' );
	}

	/**
	 * Find a user row by username
	 *
	 * @param {string} username The username to search for
	 */
	public getUserRow( username: string ): Locator {
		// Find the cell containing the username, then get its parent row
		return this.usersTable.locator( `xpath=//a[contains(text(), '${ username }')]/ancestor::tr` );
	}

	/**
	 * Get the inactive user badge for a specific user
	 *
	 * @param {string} username The username to check for inactive badge
	 */
	public getInactiveUserBadge( username: string ): Locator {
		return this.getUserRow( username ).locator( selectors.inactiveUserBadge ).first();
	}

	public getBlockedUserBadge( username: string ): Locator {
		return this.getUserRow( username ).locator( selectors.blockedUserBadge ).first();
	}
}
