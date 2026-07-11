<?php
/**
 * The core plugin class.
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Plugin {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		// Instantiate loader, define dependencies via Autoloader
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new \ETM\Admin\Admin();
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );

		add_action( 'wp_ajax_etm_fetch_courses', array( $plugin_admin, 'ajax_fetch_courses' ) );
		add_action( 'wp_ajax_etm_save_mapping', array( $plugin_admin, 'ajax_save_mapping' ) );
		add_action( 'wp_ajax_etm_run_batch', array( $plugin_admin, 'ajax_run_batch' ) );
		add_action( 'wp_ajax_etm_test_connection', array( $plugin_admin, 'ajax_test_connection' ) );

		add_action( 'admin_post_etm_export_logs', array( $plugin_admin, 'export_logs_csv' ) );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		// e.g. $plugin_public = new \ETM\Public\Public_Controller();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		// Execute the actions and filters
	}
}
