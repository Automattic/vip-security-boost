<?php
namespace Automattic\VIP\Security\XmlRpc;

class Xml_Rpc {
	private static $disabled;

	public static function init() {
		if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
			error_log( 'VIP_SECURITY_BOOST_CONFIGS not defined' );
			return;
		}

		$configs        = constant( 'VIP_SECURITY_BOOST_CONFIGS' );
		$module_configs = $configs[ 'module_configs' ] ?? [];
		$xmlrpc_configs = $module_configs['xml-rpc'] ?? [];
		self::$disabled = $xmlrpc_configs['disabled'] ?? false;

		if ( self::$disabled ) {
			self::disable_xml_rpc();
		}
	}

	/**
	 * Disable XML-RPC
	 */
	public static function disable_xml_rpc() {
		// Disable XML-RPC methods that require authentication.
		add_filter( 'xmlrpc_enabled', '__return_false', PHP_INT_MAX );

		// Remove the “Really Simple Discovery” link from the header.
		remove_action( 'wp_head', 'rsd_link' );

		// Remove the X-Pingback HTTP header.
		add_filter( 'wp_headers', function( $headers ) {
			if ( isset( $headers['X-Pingback'] ) ) {
				unset( $headers['X-Pingback'] );
			}
			return $headers;
		}, PHP_INT_MAX, 1 );

		// Return an empty array for all XML-RPC methods.
		add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );

		// Disable XML-RPC completely by returning a 403 Forbidden header.
		add_filter('wp_xmlrpc_server_class', function( $class ) {
			header('HTTP/1.1 403 Forbidden');
			exit('Access to XML-RPC is disabled on this site.');
		});
	}
}

Xml_Rpc::init();
