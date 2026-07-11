<?php
/**
 * API Client
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class ApiClient {

	private $auth;
	private $base_url;

	public function __construct() {
		$this->auth     = new Auth();
		$this->base_url = rtrim( get_option( 'etm_api_base_url', '' ), '/' );
	}

	/**
	 * Connect and verify credentials.
	 *
	 * @return bool|\WP_Error
	 */
	public function connect() {
		if ( empty( $this->base_url ) ) {
			return new \WP_Error( 'missing_url', __( 'Base URL is not configured.', 'edmingle-tutor-migration' ) );
		}

		$login = $this->auth->login();

		if ( is_wp_error( $login ) ) {
			return $login;
		}

		return true;
	}

	/**
	 * Execute an arbitrary API request (used by API Explorer).
	 *
	 * @param string $method GET, POST, PUT, DELETE, PATCH
	 * @param string $endpoint The endpoint path (e.g., /api/v1/users)
	 * @param array  $custom_headers Array of headers
	 * @param array  $body The request body
	 * @param array  $query_params Query parameters to append to URL
	 * @param bool   $attempt_refresh Whether to try token refresh on 401
	 * @return array|\WP_Error
	 */
	public function execute_request( $method, $endpoint, $custom_headers = array(), $body = array(), $query_params = array(), $attempt_refresh = true ) {
		if ( empty( $this->base_url ) ) {
			return new \WP_Error( 'missing_url', __( 'Base URL is not configured.', 'edmingle-tutor-migration' ) );
		}

		if ( ! $this->auth->is_authenticated() ) {
			$login = $this->auth->login();
			if ( is_wp_error( $login ) ) {
				return $login;
			}
		}

		$url = $this->base_url . '/' . ltrim( $endpoint, '/' );

		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		$headers = array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->auth->get_token(),
		);

		if ( ! empty( $custom_headers ) && is_array( $custom_headers ) ) {
			$headers = array_merge( $headers, $custom_headers );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( ! empty( $body ) && in_array( $args['method'], array( 'POST', 'PUT', 'PATCH' ) ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$start_time = microtime( true );
		$response   = wp_remote_request( $url, $args );
		$end_time   = microtime( true );

		$execution_time = round( ( $end_time - $start_time ) * 1000 ); // in ms

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// If unauthorized, try to refresh token once
		if ( 401 === $status_code && $attempt_refresh ) {
			$refresh = $this->auth->refresh_token();
			if ( ! is_wp_error( $refresh ) ) {
				return $this->execute_request( $method, $endpoint, $custom_headers, $body, $query_params, false );
			}
		}

		$body_str        = wp_remote_retrieve_body( $response );
		$response_size   = strlen( $body_str );
		$response_headers = wp_remote_retrieve_headers( $response )->getAll();

		$data = json_decode( $body_str, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$data = $body_str; // Keep as string if not JSON
		}

		return array(
			'status_code'    => $status_code,
			'execution_time' => $execution_time,
			'response_size'  => $response_size,
			'headers'        => $response_headers,
			'data'           => $data,
			'raw'            => $body_str,
		);
	}
}
