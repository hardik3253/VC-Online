<?php
/**
 * Core Plugin Class
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Plugin {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
	}

	/**
	 * Load required dependencies.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		require_once ETM_PLUGIN_DIR . 'admin/Admin.php';
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since 1.0.0
	 */
	private function define_admin_hooks() {
		$plugin_admin = new \ETM\Admin\Admin();

		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );

		// New AJAX Endpoints for Phase 0
		add_action( 'wp_ajax_etm_test_connection', array( $plugin_admin, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_etm_execute_api', array( $plugin_admin, 'ajax_execute_api' ) );
		add_action( 'wp_ajax_etm_delete_history', array( $plugin_admin, 'ajax_delete_history' ) );
		add_action( 'wp_ajax_etm_toggle_debug', array( $plugin_admin, 'ajax_toggle_debug' ) );
	}

	/**
	 * Run the plugin.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Execution handled by constructor/hooks.
	}
}
