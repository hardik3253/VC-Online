<?php
/**
 * Plugin Name:       Edmingle to Tutor LMS Migration
 * Plugin URI:        https://example.com/
 * Description:       A production-ready plugin to migrate data from Edmingle to Tutor LMS.
 * Version:           1.0.0
 * Author:            Your Name
 * Text Domain:       edmingle-tutor-migration
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

namespace ETM;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin Constants
 */
define( 'ETM_VERSION', '1.0.0' );
define( 'ETM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ETM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader
 */
require_once ETM_PLUGIN_DIR . 'includes/Autoloader.php';

$autoloader = new \ETM\Includes\Autoloader();
$autoloader->register();
$autoloader->addNamespace( 'ETM\Includes', ETM_PLUGIN_DIR . 'includes' );
$autoloader->addNamespace( 'ETM\Admin', ETM_PLUGIN_DIR . 'admin' );

/**
 * Activation and Deactivation Hooks
 */
register_activation_hook( __FILE__, array( '\ETM\Includes\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\ETM\Includes\Deactivator', 'deactivate' ) );

/**
 * Bootstrapping the Plugin
 */
function run_edmingle_tutor_migration() {
	$plugin = new \ETM\Includes\Plugin();
	$plugin->run();
}

run_edmingle_tutor_migration();
