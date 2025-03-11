<?php
/*
 * Plugin Name: VIP Auth Monitoring Plugin
 * Description: Monitors authentication events.
 * Version: 1.0.0
 * Author: Automattic
 * License: MIT
 * Text Domain: security-bundle
 * Domain Path: /lang
 */

use Automattic\VIP\Security\AuthMonitoring\Plugin;

if ( defined( 'ABSPATH' ) ) {
	Plugin::get_instance();
}
