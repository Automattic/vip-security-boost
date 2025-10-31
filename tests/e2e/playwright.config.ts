/**
 * External dependencies
 */
import { PlaywrightTestConfig } from '@playwright/test';

const config: PlaywrightTestConfig = {
	retries: 1,
	globalSetup: require.resolve( './lib/global-setup' ),
	timeout: 120000,
	reporter: process.env.CI ? [ [ 'github' ] ] : 'line',
	reportSlowTests: null,
	workers: process.env.CI ? 1 : undefined,
	use: {
		headless: process.env.DEBUG_TESTS !== 'true',
		viewport: { width: 1280, height: 1000 },
		ignoreHTTPSErrors: true,
		video: 'retain-on-failure',
		trace: 'retain-on-failure',
		storageState: 'e2eStorageState.json',
		baseURL: process.env.E2E_BASE_URL
			? process.env.E2E_BASE_URL
			: 'http://e2e-sb-test-site.vipdev.lndo.site',
		extraHTTPHeaders: {
			'X-Integration-Test': 'true',
			'X-Integration-Test-Configs': Buffer.from(
				JSON.stringify( {
					enabled_modules: [
						'notify-privileged-activity',
						'highlight-mfa-users',
						'forced-mfa-users',
						'inactive-users',
						'session-control',
						'xml-rpc',
					],
					available_modules: [
						'inactive-users',
						'highlight-mfa-users',
						'forced-mfa-users',
						'xml-rpc',
						'session-control',
						'notify-privileged-activity',
					],
					module_configs: {
						'inactive-users': {
							mode: 'BLOCK',
							considered_inactive_after_days: 14,
							roles: [],
							capabilities: [ 'manage_options', 'edit_others_posts', 'publish_posts' ],
							needs_review: false,
						},
						'forced-mfa-users': {
							roles: [],
							capabilities: [ 'manage_options', 'edit_others_posts', 'publish_posts', 'edit_posts' ],
							needs_review: false,
						},
						'xml-rpc': {
							mode: 'DISABLE',
							needs_review: false,
						},
						'session-control': {
							expiration_days: 7,
							needs_review: false,
						},
						'highlight-mfa-users': {
							roles: [],
							capabilities: [ 'manage_options', 'edit_others_posts' ],
						},
						'notify-privileged-activity': {
							email_users: [],
						},
					},
				} ),
				'utf8',
			).toString( 'base64' ),
		},
	},
};

export default config;
