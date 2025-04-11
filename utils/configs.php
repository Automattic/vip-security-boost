<?php
namespace Automattic\VIP\Security\Utils;

/**
 * Get the module configs for a given module name.
 *
 * @param string $module_name The name of the module to get the configs for.
 * @return array The module configs.
 */
function get_module_configs( $module_name ) {
    if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
        trigger_error( 'VIP_SECURITY_BOOST_CONFIGS not defined' );
        return [];
    }

    $configs = constant( 'VIP_SECURITY_BOOST_CONFIGS' );

    if ( ! isset( $configs[ 'module_configs' ] ) ) {
        trigger_error( 'module_configs not found in VIP_SECURITY_BOOST_CONFIGS' );
        return [];
    }
    $module_configs = $configs[ 'module_configs' ];

    if ( ! isset( $module_configs[ $module_name ] ) ) {
        trigger_error( 'module_configs not found for ' . $module_name );
        return [];
    }

    $current_module_config = $module_configs[ $module_name ];

    // if configs is a string, convert it to an array
    if ( is_string( $current_module_config ) ) {
        $current_module_config = json_decode( $current_module_config, true );
    }

    return $current_module_config;
}
