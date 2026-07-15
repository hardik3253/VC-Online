<?php
/**
 * Setup Wizard Handlers
 *
 * @package Edmingle_Tutor_Migration\Admin
 */

namespace ETM\Admin;

use ETM\Includes\Edmingle_API;
use ETM\Includes\Auth;

class Setup_Wizard {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'wp_ajax_etm_setup_authenticate', array( $this, 'ajax_authenticate' ) );
		add_action( 'wp_ajax_etm_setup_fetch_institution', array( $this, 'ajax_fetch_institution' ) );
		add_action( 'wp_ajax_etm_setup_fetch_organization', array( $this, 'ajax_fetch_organization' ) );
		add_action( 'wp_ajax_etm_setup_verify_access', array( $this, 'ajax_verify_access' ) );
	}

	/**
	 * Step 1: Authenticate and store token, user_id, server_key
	 */
	public function ajax_authenticate() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( $_POST['base_url'] ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

		if ( empty( $base_url ) || empty( $email ) || empty( $password ) ) {
			wp_send_json_error( 'Please provide Base URL, Email, and Password.' );
		}

		// Save temporarily to options so Auth class can use them
		update_option( 'etm_api_base_url', $base_url );
		update_option( 'etm_admin_email', $email );
		
		// Encrypt and save password (using Admin class instance to borrow encrypt_password logic or doing it directly)
		$auth = new Auth();
		if ( $password !== '************' ) {
			update_option( 'etm_admin_password', $auth->encrypt_password( $password ) );
		}

		// Call Auth login
		$login = $auth->login();

		if ( is_wp_error( $login ) ) {
			wp_send_json_error( $login->get_error_message() );
		}

		wp_send_json_success( array(
			'message'    => 'Authentication successful.',
			'user_id'    => $login['user_id'],
			// Don't send raw keys back to JS for security, just success indicator
		) );
	}

	/**
	 * Step 2: Fetch Institution ID
	 */
	public function ajax_fetch_institution() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );

		$base_url = get_option( 'etm_api_base_url', '' );
		$host = wp_parse_url( $base_url, PHP_URL_HOST );

		$endpoint = 'nuSource/api/v1/institute/instituteinfo?host_name=' . urlencode( $host );
		$response = Edmingle_API::request( $endpoint );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$data = $response['data'];
		
		if ( isset( $data['inst_info']['institute_id'] ) ) {
			$inst_id = $data['inst_info']['institute_id'];
			update_option( 'etm_admin_institution_id', $inst_id );
			wp_send_json_success( array( 'institution_id' => $inst_id ) );
		}

		wp_send_json_error( 'Institution ID not found in response.' );
	}

	/**
	 * Step 3: Fetch Organization ID
	 */
	public function ajax_fetch_organization() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );

		$inst_id = get_option( 'etm_admin_institution_id', '' );
		if ( empty( $inst_id ) ) {
			wp_send_json_error( 'Institution ID is missing.' );
		}

		$endpoint = 'institution/organizations?institution_id=' . $inst_id;
		$response = Edmingle_API::request( $endpoint );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$data = $response['data'];

		if ( isset( $data['data'] ) && is_array( $data['data'] ) && ! empty( $data['data'] ) ) {
			$org = $data['data'][0];
			if ( isset( $org['id'] ) ) {
				update_option( 'etm_admin_orgid', $org['id'] );
				wp_send_json_success( array( 'organization_id' => $org['id'] ) );
			}
		}

		wp_send_json_error( 'Organization ID not found in response.' );
	}

	/**
	 * Step 4: Verify API Access
	 */
	public function ajax_verify_access() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );

		$tests = array(
			'students'    => 'nuSource/api/v1/users',
			'enrollments' => 'nuSource/api/v1/enrollments',
			'curriculum'  => 'nuSource/api/v1/curriculum',
			'progress'    => 'nuSource/api/v1/progress'
		);

		$results = array();
		$all_passed = true;
		$error_message = '';

		foreach ( $tests as $key => $endpoint ) {
			// Using limit 1 just to test access
			$url = add_query_arg( 'limit', 1, $endpoint );
			$response = Edmingle_API::request( $url );
			
			if ( is_wp_error( $response ) ) {
				$results[$key] = false;
				$all_passed = false;
				$error_message = $response->get_error_message();
				break;
			} else {
				$results[$key] = true;
			}
		}

		if ( $all_passed ) {
			wp_send_json_success( $results );
		} else {
			wp_send_json_error( array(
				'message' => 'API Verification failed: ' . $error_message,
				'results' => $results
			) );
		}
	}
}
