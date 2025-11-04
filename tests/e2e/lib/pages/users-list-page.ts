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
		// Find the row that contains a link starting with the exact username
		// Use regex to match username followed by optional badge text
		const linkPattern = new RegExp( `^${ username.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) }(\\s|$)` );
		return this.usersTable.getByRole( 'row' ).filter( { has: this.page.getByRole( 'link', { name: linkPattern } ) } );
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

	/**
	 * Get the "Last Seen" column header
	 */
	public getLastSeenColumnHeader(): Locator {
		return this.usersTable.locator( 'thead th' ).filter( { hasText: 'Last seen' } );
	}

	/**
	 * Get the "Last Seen" value for a specific user
	 *
	 * @param {string} username The username to get the "Last Seen" value for
	 */
	public getLastSeenValue( username: string ): Locator {
		// "Last seen" is the 6th td cell (0-indexed: td[5])
		// Columns: username, name, email, role, posts, last-seen, two-factor, wpcom
		return this.getUserRow( username ).locator( 'td' ).nth( 5 );
	}
}
