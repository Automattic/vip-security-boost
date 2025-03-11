<?php

namespace Automattic\VIP\Security;

class Integration extends \Automattic\VIP\Integrations\Integration {
	/** The version of the plugin to load */
	protected string $version = '1.0';

	/**
	 * Instance for Integration.
	 *
	 * @var Integration|null
	 */
	private static $instance = null;

	/**
	 * Get Integration instance (initialise if null)
	 *
	 * @return Integration
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Integration( 'security-bundle' );
		}

		return self::$instance;
	}

	/**
	 * List of plugins that are loaded.
	 *
	 * @param string $plugin_name
	 */
	private $loaded_plugins = [];

	/**
	 * Returns `true` if the plugin is already available e.g. via customer code. We will use
	 * this function to prevent activating of integration from platform side.
	 */
	public function is_loaded(): bool {
		return ! empty( $this->loaded_plugins );
	}

	/**
	 * Return the environment-level configuration for this integration.
	 *
	 * @return array<string,array>
	 */
	public function get_env_config(): array {
		if ( constant( 'VIP_GO_APP_ENVIRONMENT' ) === 'local' && defined( 'GOOP_API_URL' ) ) {
			$endpoint  = sprintf( '%s/v1/vip-integrations/frontend/integration?slug=security-bundle&level=site&site_id=%s', constant( 'GOOP_API_URL' ), constant( 'VIP_GO_APP_ID' ) );
			$api_error = new \WP_Error( 'goop-api-error', 'There was an error while fetching the integration configuration from the API.' );
			$response  = vip_safe_wp_remote_get( $endpoint, $api_error, 5, 30, 30, array() );

			if ( is_wp_error( $response ) ) {
				error_log( 'Error: ' . $response->get_error_message() );
				return parent::get_env_config();
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body_raw    = wp_remote_retrieve_body( $response );
			$body        = json_decode( $body_raw, true );

			if ( $status_code !== 200 || ! is_array( $body ) || ! isset( $body[ 'data' ] ) ) {
				error_log( 'Error: ' . $body_raw );
				return parent::get_env_config();
			}

			return $body[ 'data' ][ 'config' ];
		}

		return parent::get_env_config();
	}
	
	/**
	 * Applies hooks to load the plugin.
	 */
	public function load(): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		add_action( 'plugins_loaded', function () {
			// Return if the integration is already loaded.
			//
			// In activate() method we do make sure to not activate the integration if its already loaded
			// but still adding it here as a safety measure i.e. if load() is called directly.
			if ( $this->is_loaded() ) {
				return;
			}

			// Load the version of the plugin that should be set to the latest version, otherwise if it's not found deactivate the integration.
			$configs  = $this->get_env_config();
			$enabled_modules = $configs['enabled_modules'] ?? [];

			foreach ( $enabled_modules as $plugin ) {
				$load_path = dirname( __FILE__ ) . '/modules/' . $plugin . '/index.php';

				if ( file_exists( $load_path ) ) {
					$this->loaded_plugins[] = $plugin;
					require_once $load_path;
				} else {
					trigger_error( 'Plugin not found: ' . $plugin, E_USER_WARNING );
				}
			}

			if ( empty( $this->loaded_plugins ) ) {
				$this->is_active = false;
			}
		} );
	}
}
