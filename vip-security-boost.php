<?php declare(strict_types = 1);

require_once __DIR__ . '/class-integration.php';

use Automattic\VIP\Integrations\IntegrationsSingleton;
use Automattic\VIP\Security\Integration;

/**
 * Local environment specific configurations.
 */
$is_local_env = ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'local' === constant( 'VIP_GO_APP_ENVIRONMENT' );

if ( $is_local_env ) {
	if ( ! defined( 'VIP_GO_APP_ID' ) || ! constant( 'VIP_GO_APP_ID' ) ) {
		define( 'VIP_GO_APP_ID', 101 );
	}

	define( 'GOOP_API_URL', vip_get_env_var( 'GOOP_API_URL', getenv( 'GOOP_API_URL' ) ) );

    /**
     * Register and activate the integration.
     */
    $integration = Integration::instance();

    IntegrationsSingleton::instance()->register( $integration );
    IntegrationsSingleton::instance()->activate_platform_integrations();

    // Load the integration
    $integration->load();
}
