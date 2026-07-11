<?php
/**
 * Reusable API Client for Edmingle
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class ApiClient {

	/**
	 * The base URL of the API.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * The API Token.
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * The API Secret.
	 *
	 * @var string
	 */
	private $api_secret;

	/**
	 * Max retries for failed requests.
	 *
	 * @var int
	 */
	private $max_retries = 3;

	/**
	 * Timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout = 30;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->authenticate();
	}

	/**
	 * Retrieve credentials and authenticate.
	 *
	 * @since 1.0.0
	 */
	public function authenticate() {
		$settings = get_option( 'etm_api_settings', array() );
		$this->base_url   = isset( $settings['base_url'] ) ? trailingslashit( $settings['base_url'] ) : '';
		$this->api_token  = isset( $settings['api_token'] ) ? $settings['api_token'] : '';
		$this->api_secret = isset( $settings['api_secret'] ) ? $settings['api_secret'] : '';
	}

	/**
	 * Test the API connection.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function connect() {
		// Attempting a simple GET request to base URL or a known health endpoint
		$response = $this->get( '' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Perform a GET request with optional pagination.
	 *
	 * @since 1.0.0
	 * @param string $endpoint The API endpoint.
	 * @param array  $query_args Optional query parameters.
	 * @return array|\WP_Error Response body array or WP_Error.
	 */
	public function get( $endpoint, $query_args = array() ) {
		$url = $this->base_url . ltrim( $endpoint, '/' );

		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}

		$args = array(
			'method'  => 'GET',
			'headers' => $this->get_headers(),
			'timeout' => $this->timeout,
		);

		return $this->request( $url, $args );
	}

	/**
	 * Perform a POST request.
	 *
	 * @since 1.0.0
	 * @param string $endpoint The API endpoint.
	 * @param array  $body The request payload.
	 * @return array|\WP_Error Response body array or WP_Error.
	 */
	public function post( $endpoint, $body = array() ) {
		$url = $this->base_url . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => 'POST',
			'headers' => $this->get_headers(),
			'timeout' => $this->timeout,
			'body'    => wp_json_encode( $body ),
		);

		return $this->request( $url, $args );
	}

	/**
	 * Centralized request method with retry logic and error handling.
	 *
	 * @since 1.0.0
	 * @param string $url The complete URL.
	 * @param array  $args Request arguments.
	 * @param int    $attempt Current retry attempt.
	 * @return array|\WP_Error
	 */
	private function request( $url, $args, $attempt = 1 ) {
		if ( empty( $this->base_url ) ) {
			return new \WP_Error( 'missing_url', __( 'API Base URL is not configured.', 'edmingle-tutor-migration' ) );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( $attempt < $this->max_retries ) {
				sleep( 1 ); // Brief pause before retry
				return $this->request( $url, $args, $attempt + 1 );
			}
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			// Retry on 5xx errors or rate limits (429)
			if ( in_array( $status_code, array( 429, 500, 502, 503, 504 ) ) && $attempt < $this->max_retries ) {
				sleep( 2 ); 
				return $this->request( $url, $args, $attempt + 1 );
			}

			$error_message = isset( $decoded_body['message'] ) ? $decoded_body['message'] : wp_remote_retrieve_response_message( $response );
			return new \WP_Error( 'api_error_' . $status_code, $error_message, $decoded_body );
		}

		return $decoded_body ? $decoded_body : $body;
	}

	/**
	 * Build standardized API headers.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_headers() {
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);

		if ( ! empty( $this->api_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_token;
		}

		if ( ! empty( $this->api_secret ) ) {
			$headers['X-Api-Secret'] = $this->api_secret;
		}

		return $headers;
	}
}
