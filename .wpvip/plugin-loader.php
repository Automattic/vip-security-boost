<?php

defined( 'ABSPATH' ) || die();

if ( ! defined( 'WP_TESTS_DOMAIN' ) && function_exists( 'wpcom_vip_load_plugin' ) ) {
	wpcom_vip_load_plugin( 'vip-security-boost/vip-security-boost.php' );
}
// add_filter( 'wpcom_vip_is_two_factor_forced', '__return_true' );
// add_filter( 'wpcom_vip_is_two_factor_local_testing', '__return_true' );

add_action( 'set_current_user', function() { 
	$limited = current_user_can( 'edit_posts' );
	add_filter( 'wpcom_vip_is_two_factor_forced', function() use ( $limited ) {
			return $limited;
	}, PHP_INT_MAX );
} );