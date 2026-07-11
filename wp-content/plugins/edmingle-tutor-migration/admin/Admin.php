<?php
/**
 * Admin controller for the plugin.
 *
 * @package Edmingle_Tutor_Migration\Admin
 */

namespace ETM\Admin;

class Admin {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'edmingle-migration' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'etm-admin',
			ETM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ETM_VERSION,
			'all'
		);

		if ( strpos( $hook_suffix, 'edmingle-migration-dashboard' ) !== false ) {
			wp_enqueue_script(
				'etm-migration-js',
				ETM_PLUGIN_URL . 'assets/js/migration.js',
				array( 'jquery' ),
				ETM_VERSION,
				true
			);

			wp_localize_script( 'etm-migration-js', 'etm_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'etm_migration_nonce' ),
			) );
		}

		if ( strpos( $hook_suffix, 'edmingle-migration-mapping' ) !== false ) {
			wp_enqueue_script(
				'etm-mapping-js',
				ETM_PLUGIN_URL . 'assets/js/mapping.js',
				array( 'jquery' ),
				ETM_VERSION,
				true
			);

			wp_localize_script( 'etm-mapping-js', 'etm_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'etm_mapping_nonce' ),
			) );
		}
	}

	/**
	 * Register the admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Edmingle Migration', 'edmingle-tutor-migration' ),
			__( 'Edmingle Migration', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-dashboard',
			array( $this, 'display_plugin_dashboard_page' ),
			'dashicons-migrate',
			50
		);

		add_submenu_page(
			'edmingle-migration-dashboard',
			__( 'Settings', 'edmingle-tutor-migration' ),
			__( 'Settings', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-settings',
			array( $this, 'display_plugin_settings_page' )
		);

		add_submenu_page(
			'edmingle-migration-dashboard',
			__( 'Course Mapping', 'edmingle-tutor-migration' ),
			__( 'Course Mapping', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-mapping',
			array( $this, 'display_plugin_mapping_page' )
		);

		add_submenu_page(
			'edmingle-migration-dashboard',
			__( 'Logs & Verification', 'edmingle-tutor-migration' ),
			__( 'Logs', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-logs',
			array( $this, 'display_plugin_logs_page' )
		);
	}

	/**
	 * Render the dashboard view.
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_dashboard_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Register the settings for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'etm_settings_group',
			'etm_api_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @since 1.0.0
	 * @param array $input Contains all settings fields as array keys
	 * @return array Sanitized array
	 */
	public function sanitize_settings( $input ) {
		$sanitized_input = array();

		if ( isset( $input['base_url'] ) ) {
			$sanitized_input['base_url'] = esc_url_raw( trim( $input['base_url'] ) );
		}

		if ( isset( $input['api_token'] ) ) {
			$sanitized_input['api_token'] = sanitize_text_field( trim( $input['api_token'] ) );
		}

		if ( isset( $input['api_secret'] ) ) {
			$sanitized_input['api_secret'] = sanitize_text_field( trim( $input['api_secret'] ) );
		}

		return $sanitized_input;
	}

	/**
	 * Render the settings view.
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_settings_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the course mapping view.
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_mapping_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/course-mapping.php';
	}

	/**
	 * Render the logs & verification view.
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_logs_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * Handle CSV Export.
	 *
	 * @since 1.0.0
	 */
	public function export_logs_csv() {
		$logger = new \ETM\Includes\Logger();
		$logger->export_csv();
	}

	/**
	 * AJAX Callback: Fetch and auto-match courses.
	 *
	 * @since 1.0.0
	 */
	public function ajax_fetch_courses() {
		check_ajax_referer( 'etm_mapping_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$mapper = new \ETM\Includes\Course_Mapper();
		$data = $mapper->auto_match();
		$saved_mapping = $mapper->get_mapping();

		$data['saved_mapping'] = $saved_mapping;

		wp_send_json_success( $data );
	}

	/**
	 * AJAX Callback: Save course mapping.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_mapping() {
		check_ajax_referer( 'etm_mapping_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$mappings = isset( $_POST['mappings'] ) ? (array) $_POST['mappings'] : array();

		$mapper = new \ETM\Includes\Course_Mapper();
		$result = $mapper->save_mapping( $mappings );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( 'Mapping saved successfully.' );
	}

	/**
	 * AJAX Callback: Run a migration batch.
	 *
	 * @since 1.0.0
	 */
	public function ajax_run_batch() {
		check_ajax_referer( 'etm_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$step       = isset( $_POST['step'] ) ? sanitize_text_field( $_POST['step'] ) : '';
		$page       = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 50;

		if ( empty( $step ) ) {
			wp_send_json_error( 'Missing step parameter.' );
		}

		$processor = new \ETM\Includes\Batch_Processor();
		$result    = $processor->run_step( $step, $page, $batch_size );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX Callback: Test API Connection.
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'etm_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$api = new \ETM\Includes\ApiClient();
		$result = $api->connect();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( 'Connection successful!' );
	}
}
