<?php

namespace Automattic\VIP\Security;

use Automattic\VIP\Security\Constants;
use function Automattic\VIP\Security\Utils\{ get_module_configs, get_all_module_configs };

class Loader {
	const LOG_FEATURE_NAME = 'sb_module_loader';

	private static ?Loader $instance      = null;
	private static array $configs         = [];
	private static array $enabled_modules = [];

	public static function instance(): Loader {
		if ( ! self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init(): void {
		$this->load_configs();
		$this->load_modules();
	}

	private function load_configs(): void {
		if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
			throw new \Exception( 'VIP_SECURITY_BOOST_CONFIGS is not defined.' );
		}

		self::$configs   = get_all_module_configs();
		$enabled_modules = self::$configs['enabled_modules'] ?? [];

		// set to empty array if there are no enabled modules (empty array or string)
		if ( empty( $enabled_modules ) ) {
			$enabled_modules = [];
		}

		// If enabled_modules is a string, convert it to an array
		// I noticed the integrations-config can output a string so we need to handle that
		if ( is_string( $enabled_modules ) ) {
			$enabled_modules = explode( ',', $enabled_modules );
		}

		self::$enabled_modules = $enabled_modules;
	}

	private function load_modules(): void {
		foreach ( self::$enabled_modules as $module ) {
			// Sanitize module name to prevent path traversal
			$module = basename( $module );

			$load_path = realpath( __DIR__ . '/modules/' . $module . '/class-' . $module . '.php' );

			if ( file_exists( $load_path ) ) {
				$this->load_module( $module, $load_path );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error( 'Module not found: ' . esc_html( $module ), E_USER_WARNING );
			}
		}
	}

	/**
	* @param string $module The name of the module.
	* @param string $load_path The path to the module file to load.
	*/
	private function load_module( $module, $load_path ): void {

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		require_once $load_path;

		// Convert kebab-case module name to PascalCase class name
		$class_name = 'Automattic\\VIP\\Security\\'
			. str_replace( ' ', '', ucwords( str_replace( '-', ' ', $module ) ) )
			. '\\'
			. str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $module ) ) );

		// Load current module configuration
		$current_module_config = [];

		if ( isset( self::$configs ) ) {
			$current_module_config = get_module_configs( $module, self::$configs );
		}
		// Load the module class and call its init method if it exists
		if ( class_exists( $class_name ) && method_exists( $class_name, 'init' ) ) {
			call_user_func( [ $class_name, 'init' ], $current_module_config );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( 'Module not found: ' . esc_html( $module ), E_USER_WARNING );
			\Automattic\VIP\Logstash\log2logstash(
				[
					'severity' => 'error',
					'plugin'   => Constants::LOG_PLUGIN_NAME,
					'feature'  => self::LOG_FEATURE_NAME,
					'message'  => 'Module not found: ' . $module,
				]
			);
		}
	}
}

Loader::instance();
