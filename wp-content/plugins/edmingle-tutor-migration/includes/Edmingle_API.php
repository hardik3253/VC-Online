<?php
/**
 * Edmingle API Class
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Edmingle_API {

	/**
	 * Execute an API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method (GET, POST, etc).
	 * @param array  $params Query params or body data.
	 * @return array|\WP_Error Decoded JSON response or WP_Error.
	 */
	public static function request( $endpoint, $method = 'GET', $params = array() ) {
		$base_url = get_option( 'etm_api_base_url' );
		$org_id   = get_option( 'etm_admin_orgid' );
		$auth     = new \ETM\Includes\Auth();
		$token    = $auth->get_token();

		if ( empty( $base_url ) ) {
			return new \WP_Error( 'missing_url', __( 'Base URL is not configured.', 'edmingle-tutor-migration' ) );
		}

		if ( empty( $token ) ) {
			// Try to login if we don't have a token
			$login = $auth->login();
			if ( is_wp_error( $login ) ) {
				return $login;
			}
			$token = $auth->get_token();
		}

		$url = rtrim( $base_url, '/' ) . '/' . ltrim( $endpoint, '/' );
		$method = strtoupper( $method );

		if ( 'GET' === $method && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'apikey'       => $token,
		);

		if ( ! empty( $org_id ) ) {
			$headers['ORGID'] = $org_id;
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 45, // Increase timeout for potentially large responses
		);

		if ( 'GET' !== $method && ! empty( $params ) ) {
			$args['body'] = wp_json_encode( $params );
		}

		$start_time = microtime( true );
		$response   = wp_remote_request( $url, $args );
		$end_time   = microtime( true );

		$execution_time = intval( ( $end_time - $start_time ) * 1000 );
		$status_code    = is_wp_error( $response ) ? 500 : wp_remote_retrieve_response_code( $response );
		
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			ETM_Database::log_request( $url, $execution_time, $status_code, $error_message );
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : 'API Error ' . $status_code;
			ETM_Database::log_request( $url, $execution_time, $status_code, $error_message );
			return new \WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
		}

		if ( ! is_array( $data ) ) {
			$error_message = 'Invalid JSON response';
			ETM_Database::log_request( $url, $execution_time, $status_code, $error_message );
			return new \WP_Error( 'invalid_response', $error_message );
		}

		// Log success (rows saved will be updated by the caller if needed)
		ETM_Database::log_request( $url, $execution_time, $status_code, 'Success' );

		// We return an array with data and execution_time so caller can use it
		return array(
			'data'           => $data,
			'execution_time' => $execution_time,
		);
	}
}
