<?php

namespace Automattic\VIP\Security;

class Integration extends \Automattic\VIP\Integrations\Integration {
	protected string $version = '1.0';

	private static $instance = null;
	private $loaded_modules = [];

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Integration( 'security-bundle' );
		}

		return self::$instance;
	}

	public function is_loaded(): bool {
		return ! empty( $this->loaded_modules );
	}

	public function get_env_config(): array {
		if ( constant( 'VIP_GO_APP_ENVIRONMENT' ) === 'local' && defined( 'GOOP_API_URL' ) ) {
			$endpoint  = sprintf( '%s/v1/vip-integrations/frontend/integration?slug=security-bundle&level=site&site_id=%s&is_vip=true', constant( 'GOOP_API_URL' ), constant( 'VIP_GO_APP_ID' ) );
			$api_error = new \WP_Error( 'goop-api-error', 'There was an error while fetching the integration configuration from the API.' );
			$response  = vip_safe_wp_remote_get( $endpoint, $api_error, 5, 5, 5, array() );

			if ( is_wp_error( $response ) ) {
				error_log( 'Error: ' . $response->get_error_message() );
				return parent::get_env_config();
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body_raw    = wp_remote_retrieve_body( $response );
			$body        = json_decode( $body_raw, true );

			if ( $status_code !== 200 || ! is_array( $body ) || ! isset( $body['data'] ) ) {
				error_log( 'Error: ' . $body_raw );
				return parent::get_env_config();
			}

			return $body['data']['config'];
		}

		return parent::get_env_config();
	}
	
	public function load(): void {
		add_action( 'muplugins_loaded', function () {
			if ( $this->is_loaded() ) {
				return;
			}

			$configs         = $this->get_env_config();
			$enabled_modules = $configs['enabled_modules'] ?? [];

			if ( ! defined( 'SECURITY_BUNDLE_CONFIGS' ) ) {
				define( 'SECURITY_BUNDLE_CONFIGS', $configs );
			}

			foreach ( $enabled_modules as $module ) {
				$load_path = __DIR__ . '/modules/' . $module . '/' . $module . '.php';

				if ( file_exists( $load_path ) ) {
					$this->loaded_modules[] = $module;
					require_once $load_path;
				} else {
					trigger_error( 'Module not found: ' . $module, E_USER_WARNING );
				}
			}

			if ( empty( $loaded_modules ) ) {
				$this->is_active = false;
			}
		} );
	}
}
