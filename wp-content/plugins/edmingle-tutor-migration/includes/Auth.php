<?php
/**
 * Authentication Service
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Auth {

	private $token_option = 'etm_edmingle_token';
	private $base_url_option = 'etm_api_base_url';
	private $email_option = 'etm_admin_email';
	private $password_option = 'etm_admin_password';

	/**
	 * Log in to Edmingle API and get the token.
	 *
	 * @return \WP_Error|array Returns the auth response or error.
	 */
	public function login() {
		$base_url = get_option( $this->base_url_option );
		$email    = get_option( $this->email_option );
		$password = $this->decrypt_password( get_option( $this->password_option ) );

		if ( empty( $base_url ) || empty( $email ) || empty( $password ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Missing API credentials. Please check Settings.', 'edmingle-tutor-migration' ) );
		}

		// Use the correct Edmingle login endpoint from the Postman collection
		$endpoint = rtrim( $base_url, '/' ) . '/tutor/login';

		$args = array(
			'method'  => 'POST',
			'body'    => array(
				'JSONString' => wp_json_encode( array(
					'username'         => $email,
					'password'         => $password,
					'persistent_login' => true,
				) )
			),
			'timeout' => 15,
		);

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code !== 200 || ! isset( $data['user']['apikey'] ) ) {
			$msg = isset( $data['message'] ) ? $data['message'] : 'Authentication failed with status ' . $status_code;
			return new \WP_Error( 'auth_failed', $msg );
		}

		$token      = $data['user']['apikey'];
		$user_id    = isset( $data['user']['user_id'] ) ? $data['user']['user_id'] : '';
		$server_key = isset( $data['user']['server_key'] ) ? $data['user']['server_key'] : '';

		if ( ! empty( $token ) ) {
			$this->store_token( $token, $user_id, $server_key );
			return array( 
				'success'    => true, 
				'token'      => $token,
				'user_id'    => $user_id,
				'server_key' => $server_key
			);
		}

		return new \WP_Error( 'invalid_response', 'No token found in response' );
	}

	/**
	 * Log out (clear the stored token).
	 */
	public function logout() {
		delete_option( 'etm_admin_token' );
		delete_option( 'etm_admin_user_id' );
		delete_option( 'etm_admin_server_key' );
	}

	/**
	 * Store the access token securely.
	 *
	 * @param string $token
	 * @param int|string $user_id
	 * @param string $server_key
	 */
	private function store_token( $token, $user_id = '', $server_key = '' ) {
		update_option( 'etm_admin_token', $token );
		if ( ! empty( $user_id ) ) {
			update_option( 'etm_admin_user_id', $user_id );
		}
		if ( ! empty( $server_key ) ) {
			update_option( 'etm_admin_server_key', $server_key );
		}
	}

	/**
	 * Get the currently stored token.
	 *
	 * @return string|false
	 */
	public function get_token() {
		return get_option( 'etm_admin_token' );
	}

	/**
	 * Refresh token logic.
	 *
	 * @return \WP_Error|array
	 */
	public function refresh_token() {
		return $this->login();
	}

	/**
	 * Check if the user is authenticated.
	 *
	 * @return bool
	 */
	public function is_authenticated() {
		$token = $this->get_token();
		return ! empty( $token );
	}

	/**
	 * Encrypt a password before saving it.
	 *
	 * @param string $plain_text
	 * @return string
	 */
	public function encrypt_password( $plain_text ) {
		if ( empty( $plain_text ) ) {
			return '';
		}
		$key    = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'fallback-key-etm';
		$iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$cipher = openssl_encrypt( $plain_text, 'aes-256-cbc', $key, 0, $iv );
		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a stored password.
	 *
	 * @param string $encrypted_text
	 * @return string
	 */
	public function decrypt_password( $encrypted_text ) {
		if ( empty( $encrypted_text ) ) {
			return '';
		}
		$key        = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'fallback-key-etm';
		$data       = base64_decode( $encrypted_text );
		$iv_size    = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv         = substr( $data, 0, $iv_size );
		$cipher     = substr( $data, $iv_size );
		$plain_text = openssl_decrypt( $cipher, 'aes-256-cbc', $key, 0, $iv );
		return $plain_text;
	}
}
