<?php
/**
 * Plugin Name:     Tutor Razorpay
 * Plugin URI:      https://tutorlms.com
 * Description:     Razorpay payment integration for Tutor LMS
 * Author:          Themeum
 * Author URI:      https://tutorlms.com
 * Text Domain:     tutor-razorpay
 * Domain Path:     /languages
 * Version:         1.0.3
 * License: GPLv2 or later
 *
 * @package         TutorRazorpay
 */

// Your code starts here.
require_once __DIR__ . '/vendor/autoload.php';

// Define plugin meta info.
define( 'TUTOR_RAZORPAY_VERSION', '1.0.3' );
define( 'TUTOR_RAZORPAY_URL', plugin_dir_url( __FILE__ ) );
define( 'TUTOR_RAZORPAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'TUTOR_RAZORPAY_PAYMENTS_DIR', trailingslashit( TUTOR_RAZORPAY_PATH . 'src/Payments' ) );

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

add_action(
	'plugins_loaded',
	function() {
		if ( is_plugin_active( 'tutor/tutor.php' ) && is_plugin_active( 'tutor-pro/tutor-pro.php' ) ) {
			new TutorRazorpay\Init();
		}
	},
	100
);
