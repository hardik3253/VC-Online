<?php
/**
 * Logger & Debug Mode
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Logger {

	private static $option_name = 'etm_debug_mode';

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool
	 */
	public static function is_debug_enabled() {
		return get_option( self::$option_name, 'no' ) === 'yes';
	}

	/**
	 * Write a log message if debug mode is enabled.
	 *
	 * @param string $message
	 * @param string $type
	 */
	public static function write_log( $message, $type = 'debug' ) {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/edmingle-migration/logs';
		$log_file = $log_dir . '/' . sanitize_key( $type ) . '-' . date( 'Y-m-d' ) . '.log';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
		}

		$time = current_time( 'mysql' );
		$log_entry = "[$time] $message\n";

		error_log( $log_entry, 3, $log_file );
	}

	/**
	 * Log full API request/response.
	 *
	 * @param string $method
	 * @param string $endpoint
	 * @param array $headers
	 * @param array $body
	 * @param array $response
	 */
	public static function log_api_call( $method, $endpoint, $headers, $body, $response ) {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		$log_data = "================================================\n";
		$log_data .= "Method: $method\n";
		$log_data .= "Endpoint: $endpoint\n";
		$log_data .= "Headers: " . wp_json_encode( $headers ) . "\n";
		$log_data .= "Payload: " . wp_json_encode( $body ) . "\n";
		$log_data .= "Response Status: " . ( isset( $response['status_code'] ) ? $response['status_code'] : 'Unknown' ) . "\n";
		
		if ( is_wp_error( $response ) ) {
			$log_data .= "Error: " . $response->get_error_message() . "\n";
		} else {
			$log_data .= "Response Data: " . ( isset( $response['raw'] ) ? $response['raw'] : wp_json_encode( $response ) ) . "\n";
		}
		
		$log_data .= "================================================\n";

		self::write_log( $log_data, 'api' );
	}
}
