<?php declare(strict_types = 1);

/**
 * VIP recommends loading all plugins for your site in code. Loading plugins
 * through code results in more control and greater consistency across
 * development environments. Using this file to do so helps load and activate
 * plugins as early as possible in the WordPress load order.
 *
 * @see https://docs.wpvip.com/how-tos/activate-plugins-through-code/
 * @see https://docs.wpvip.com/technical-references/vip-codebase/client-mu-plugins-directory/
 */

require_once WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/vendor/autoload.php';
require_once WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/class-integration.php';

use Automattic\VIP\Integrations\IntegrationsSingleton;
use Automattic\VIP\Security\Integration;

IntegrationsSingleton::instance()->register( Integration::instance() );
IntegrationsSingleton::instance()->activate_platform_integrations();