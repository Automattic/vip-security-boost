<?php
/**
 * VIP_Security_Boost setup
 *
 * @package VIP_Security_Boost
 * @since   0.9.0
 */

use Automattic\VIP\Security\Constants;
use function Automattic\VIP\Security\Utils\{load_dev_env_configs};

class Vip_Security_Boost {

	/**
	* @since 0.9.0
	*/
	const VERSION = '0.9.0';

	/**
	* @since 0.9.0
	*/
	const WP_MIN_VERSION = '6.4';

	/**
	* @since 0.9.0
	*/
	const LOG_FEATURE_NAME = 'sb_module_loader';

	/**
	* @var VIP_Security_Boost
	* @since 0.9.0
	*/
	private static ?Vip_Security_Boost $instance = null;

	/**
	* @var array
	* @since 0.9.0
	*/
	private static array $module_configs = [];

	/**
	* @var array
	* @since 0.9.0
	*/
	private static array $enabled_modules = [];

	/**
	* @since 0.9.0
	*/
	public static function instance(): Vip_Security_Boost {
		if ( ! self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	* @since 0.9.0
	*/
	public function init(): void {
		$this->load_dev_env();
		$this->load_configs();
		$this->load_modules();
	}

	/**
	* @since 0.9.0
	*/
	private function load_dev_env(): void {
		// Load dev environment configurations if not in production.
		$is_local_env = ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'local' === constant( 'VIP_GO_APP_ENVIRONMENT' );
		if ( $is_local_env ) {
			require_once __DIR__ . '/dev-env.php';
			load_dev_env_configs();
		}
	}

	/**
	* @since 0.9.0
	*/
	private function load_configs(): void {
		if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
			throw new \Exception( 'VIP_SECURITY_BOOST_CONFIGS is not defined.' );
		}

		$configs         = constant( 'VIP_SECURITY_BOOST_CONFIGS' );
		$enabled_modules = $configs['enabled_modules'] ?? [];
		$module_configs  = $configs['module_configs'] ?? [];

		// If enabled_modules is a string, convert it to an array
		// I noticed the integrations-config can output a string so we need to handle that
		if ( is_string( $enabled_modules ) ) {
			$enabled_modules = explode( ',', $enabled_modules );
		}

		self::$enabled_modules = $enabled_modules;

		if ( is_string( $module_configs ) ) {
			self::$module_configs = json_decode( $module_configs, true );

			if ( is_null( $module_configs ) && json_last_error() !== JSON_ERROR_NONE ) {
				self::$module_configs = [];
			}
		}
	}

	/**
	* @since 0.9.0
	*/
	private function load_modules(): void {
		foreach ( self::$enabled_modules as $module ) {
			// Sanitize module name to prevent path traversal
			$module = basename( $module );

			$load_path = __DIR__ . '/modules/' . $module . '/class-' . $module . '.php';

			if ( file_exists( $load_path ) ) {

				$this->load_module( $load_path );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error( 'Module not found: ' . esc_html( $module ), E_USER_WARNING );
			}
		}
	}

	/**
	* @since 0.9.0
	* @param string $module The name of the module.
	* @param string $load_path The path to the module file to load.
	*/
	private function load_module( $module, $load_path ): void {
		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		require_once $load_path;

		// Convert kebab-case module name to PascalCase class name
		$class_name = 'Automattic\\VIP\\Security\\' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $module ) ) );

		// Load current module configuration
		$current_module_config = [];

		if ( isset( self::$module_configs[ $module ] ) ) {
			$current_module_config = self::$module_configs[ $module ];
		}

		// Load the module class and call its init method if it exists
		if ( class_exists( $class_name ) && method_exists( $class_name, 'init' ) ) {
			call_user_func( [ $class_name, 'init' ], self::$configs[ $module ] );
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
