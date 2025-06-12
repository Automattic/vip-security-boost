<?php
/**
 * Plugin Name: WordPress VIP Security Boost
 * Plugin URI: https://github.com/Automattic/vip-security-boost-integration
 * Description: A comprehensive security suite that protects WordPress VIP sites against common vulnerabilities and implements industry-standard security hardening measures.
 * Author: WordPress VIP
 * Text Domain: vip-security-boost
 * Version: 0.9.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package vip-security-boost
 */

declare(strict_types = 1);

require_once __DIR__ . '/email/email.php';

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'VIP_SB_PLUGIN_FILE' ) ) {
	define( 'VIP_SB_PLUGIN_FILE', __FILE__ );
}

// Include the main VIP_Security_Boost class.
if ( ! class_exists( 'Vip_Security_Boost', false ) ) {
	include_once dirname( VIP_SB_PLUGIN_FILE ) . '/utils/class-vip-security-boost.php';
}

/**
 * Returns the main instance of VIP_Security_Boost.
 *
 * @since  0.9.0
 * @return VIP_Security_Boost
 */
function Vip_Security_Boost() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Vip_Security_Boost::instance();
}

Vip_Security_Boost();
