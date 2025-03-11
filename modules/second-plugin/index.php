<?php
/*
 * Plugin Name: VIP Second Plugin
 * Description: Test.
 * Version: 1.0.0
 * Author: Automattic
 * License: MIT
 * Text Domain: security-bundle
 * Domain Path: /lang
 */

use Automattic\VIP\Security\SecondPlugin\Plugin;

if ( defined( 'ABSPATH' ) ) {
	Plugin::get_instance();
}
