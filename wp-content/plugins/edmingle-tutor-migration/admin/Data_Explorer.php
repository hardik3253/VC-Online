<?php
/**
 * Data Explorer AJAX Handlers
 *
 * @package Edmingle_Tutor_Migration\Admin
 */

namespace ETM\Admin;

use ETM\Includes\Edmingle_API;
use ETM\Includes\ETM_Database;

class Data_Explorer {

	/**
	 * Register hooks.
	 */
	public function register() {
		$types = array(
			'students',
			'courses',
			'batches',
			'curriculum',
			'materials',
			'certificates',
			'notifications'
		);

		foreach ( $types as $type ) {
			add_action( 'wp_ajax_edmingle_fetch_' . $type, array( $this, 'ajax_fetch_data' ) );
		}
		
		add_action( 'wp_ajax_edmingle_view_json', array( $this, 'ajax_view_json' ) );
	}

	/**
	 * Map resource types to API endpoints.
	 */
	private function get_endpoint_for_type( $type ) {
		// These are placeholders/guesses and should be updated based on actual API
		$endpoints = array(
			'students'      => 'nuSource/api/v1/users',
			'courses'       => 'nuSource/api/v1/courses',
			'batches'       => 'nuSource/api/v1/batches',
			'curriculum'    => 'nuSource/api/v1/curriculum',
			'materials'     => 'nuSource/api/v1/materials',
			'certificates'  => 'nuSource/api/v1/certificates',
			'notifications' => 'nuSource/api/v1/notifications',
		);
		return isset( $endpoints[ $type ] ) ? $endpoints[ $type ] : '';
	}

	/**
	 * Handle fetch data AJAX request.
	 */
	public function ajax_fetch_data() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
		$type   = str_replace( 'edmingle_fetch_', '', $action );
		
		$endpoint = $this->get_endpoint_for_type( $type );
		if ( empty( $endpoint ) ) {
			wp_send_json_error( 'Endpoint not configured for ' . $type );
		}

		$response = Edmingle_API::request( $endpoint );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$data           = $response['data'];
		$execution_time = $response['execution_time'];

		// Data structures vary, but typically lists are in 'data', 'resources', 'users', etc.
		$items = array();
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$items = $data['data'];
		} elseif ( isset( $data['resources'] ) && is_array( $data['resources'] ) ) {
			// e.g. meta details
			$items = reset( $data['resources'] );
		} else {
			// Fallback: use the full array if it looks like a list
			if ( is_array( $data ) && ! isset( $data['code'] ) ) {
				$items = $data;
			}
		}

		if ( empty( $items ) ) {
			wp_send_json_error( 'No data found in response. (Execution time: ' . $execution_time . 'ms)' );
		}

		$table_name = 'edmingle_' . $type;
		// Determine ID key. Usually 'id', 'user_id', 'course_id', etc.
		$id_key = 'id';
		$first_item = (array) $items[0];
		if ( ! isset( $first_item['id'] ) ) {
			if ( isset( $first_item[ $type . '_id' ] ) ) {
				$id_key = $type . '_id';
			} elseif ( isset( $first_item['resource_id'] ) ) {
				$id_key = 'resource_id';
			} elseif ( isset( $first_item['user_id'] ) ) {
				$id_key = 'user_id';
			} else {
				// Pick the first key that ends with _id
				foreach ( $first_item as $k => $v ) {
					if ( substr( $k, -3 ) === '_id' ) {
						$id_key = $k;
						break;
					}
				}
			}
		}

		$rows_saved = ETM_Database::save_data( $table_name, $items, $id_key );

		wp_send_json_success( array(
			'total_imported' => $rows_saved,
			'execution_time' => $execution_time,
		) );
	}
	
	/**
	 * View JSON data AJAX handler.
	 */
	public function ajax_view_json() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		if ( empty( $type ) ) {
			wp_send_json_error( 'Type required.' );
		}

		$records = ETM_Database::get_records( 'edmingle_' . $type, 5 ); // get up to 5 latest
		
		if ( empty( $records ) ) {
			wp_send_json_error( 'No records found in database.' );
		}
		
		$output = array();
		foreach ( $records as $rec ) {
			$output[] = json_decode( $rec->json_data, true );
		}

		wp_send_json_success( $output );
	}
}
