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
		$xmlrpc_configs = $configs['xml-rpc'] ?? [];
		self::$disabled = $xmlrpc_configs['disabled'] ?? false;

		if ( self::$disabled ) {
			self::disable_xml_rpc();
		}
	}

	/**
	 * Disable XML-RPC by returning false to the xmlrpc_enabled filter.
	 */
	private static function disable_xml_rpc() {
		add_filter( 'xmlrpc_enabled', '__return_false', PHP_INT_MAX );
	}
}

Xml_Rpc::init();
