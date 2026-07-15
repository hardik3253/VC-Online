<?php
/**
 * Admin Setup & Callbacks
 *
 * @package Edmingle_Tutor_Migration\Admin
 */

namespace ETM\Admin;

class Admin {

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
			58
		);

		add_submenu_page(
			'edmingle-migration-dashboard',
			__( 'Dashboard', 'edmingle-tutor-migration' ),
			__( 'Dashboard', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-dashboard',
			array( $this, 'display_plugin_dashboard_page' )
		);

		add_submenu_page(
			'edmingle-migration-dashboard',
			__( 'Data Explorer', 'edmingle-tutor-migration' ),
			__( 'Data Explorer', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-data-explorer',
			array( $this, 'display_plugin_data_explorer_page' )
		);

		add_submenu_page(
			'edmingle-migration-dashboard',
			__( 'API Explorer', 'edmingle-tutor-migration' ),
			__( 'API Explorer', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-explorer',
			array( $this, 'display_plugin_explorer_page' )
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
			__( 'Logs', 'edmingle-tutor-migration' ),
			__( 'Logs', 'edmingle-tutor-migration' ),
			'manage_options',
			'edmingle-migration-logs',
			array( $this, 'display_plugin_logs_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'etm_settings_group', 'etm_api_base_url', 'sanitize_text_field' );
		register_setting( 'etm_settings_group', 'etm_admin_email', 'sanitize_email' );
		register_setting( 'etm_settings_group', 'etm_admin_orgid', 'sanitize_text_field' );
		
		// Custom save for password to encrypt it
		register_setting( 'etm_settings_group', 'etm_admin_password', array(
			'sanitize_callback' => array( $this, 'sanitize_password' )
		) );
	}

	/**
	 * Sanitize and encrypt the password.
	 */
	public function sanitize_password( $input ) {
		// If input is exactly 12 asterisks (dummy placeholder), don't change the option
		if ( $input === '************' ) {
			return get_option( 'etm_admin_password' );
		}
		
		$auth = new \ETM\Includes\Auth();
		return $auth->encrypt_password( trim( $input ) );
	}

	/**
	 * Enqueue admin styles.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'edmingle-migration' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'etm-admin-css',
			ETM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ETM_VERSION,
			'all'
		);
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'edmingle-migration' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'etm-api-explorer-js',
			ETM_PLUGIN_URL . 'assets/js/api-explorer.js',
			array( 'jquery' ),
			ETM_VERSION,
			true
		);

		wp_localize_script( 'etm-api-explorer-js', 'etm_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'etm_admin_nonce' ),
		) );

		if ( strpos( $hook_suffix, 'edmingle-migration-data-explorer' ) !== false ) {
			wp_enqueue_script(
				'etm-data-explorer-js',
				ETM_PLUGIN_URL . 'assets/js/data-explorer.js',
				array( 'jquery' ),
				ETM_VERSION,
				true
			);

			wp_localize_script( 'etm-data-explorer-js', 'etm_admin', array(
				'nonce' => wp_create_nonce( 'etm_admin_nonce' ),
			) );
		}
	}

	/**
	 * Render Views
	 */
	public function display_plugin_dashboard_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Display the Data Explorer page.
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_data_explorer_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/data-explorer.php';
	}
	public function display_plugin_explorer_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/api-explorer.php';
	}
	public function display_plugin_settings_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/settings.php';
	}
	public function display_plugin_logs_page() {
		require_once ETM_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * AJAX: Test Connection
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$api = new \ETM\Includes\ApiClient();
		$start = microtime( true );
		$result = $api->connect();
		$end = microtime( true );

		$time = round( ( $end - $start ) * 1000 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'time'    => $time,
			) );
		}

		// Because we're using login() inside connect(), success means token is cached
		wp_send_json_success( array(
			'message' => 'Connected successfully',
			'time'    => $time,
			'method'  => 'Email + Password (Token Cached)'
		) );
	}

	/**
	 * AJAX: Execute API
	 */
	public function ajax_execute_api() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$method   = isset( $_POST['api_method'] ) ? sanitize_text_field( $_POST['api_method'] ) : 'GET';
		$endpoint = isset( $_POST['endpoint'] ) ? sanitize_text_field( $_POST['endpoint'] ) : '';
		$headers  = isset( $_POST['headers'] ) ? json_decode( stripslashes( $_POST['headers'] ), true ) : array();
		$body     = isset( $_POST['body'] ) ? json_decode( stripslashes( $_POST['body'] ), true ) : array();
		$query    = isset( $_POST['query'] ) ? json_decode( stripslashes( $_POST['query'] ), true ) : array();

		if ( ! is_array( $headers ) ) $headers = array();
		if ( ! is_array( $body ) ) $body = array();
		if ( ! is_array( $query ) ) $query = array();

		$api = new \ETM\Includes\ApiClient();
		$response = $api->execute_request( $method, $endpoint, $headers, $body, $query );

		// Log History
		$history = new \ETM\Includes\Request_History();
		$status = is_wp_error( $response ) ? 'Failed' : $response['status_code'];
		$exec_time = is_wp_error( $response ) ? 0 : $response['execution_time'];

		$history_data = array(
			'method'    => $method,
			'endpoint'  => $endpoint,
			'status'    => $status,
			'date'      => current_time( 'mysql' ),
			'time'      => $exec_time,
			'headers'   => $headers,
			'body'      => $body,
			'query'     => $query
		);
		$history->save_request( $history_data );

		// Log Debug Mode
		\ETM\Includes\Logger::log_api_call( $method, $endpoint, $headers, $body, $response );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Delete History
	 */
	public function ajax_delete_history() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
		$history = new \ETM\Includes\Request_History();
		
		if ( $index === -1 ) {
			$history->clear_history();
		} else {
			$history->delete_request( $index );
		}

		wp_send_json_success( 'History deleted' );
	}

	/**
	 * AJAX: Toggle Debug Mode
	 */
	public function ajax_toggle_debug() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'no';
		update_option( 'etm_debug_mode', $status );

		wp_send_json_success( 'Debug mode toggled' );
	}
}
