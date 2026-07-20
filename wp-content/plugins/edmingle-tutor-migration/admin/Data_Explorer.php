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
			'students'      => 'organization/students',
			'courses'       => 'organization/courses',
			'batches'       => 'short/masterbatch',
			'curriculum'    => 'curriculum',
			'materials'     => 'materials',
			'certificates'  => 'certificates/students?response_type=1',
			'notifications' => 'notifications',
		);
		return isset( $endpoints[ $type ] ) ? $endpoints[ $type ] : '';
	}

	/**
	 * Handle fetch data AJAX request (Sync Engine).
	 */
	public function ajax_fetch_data() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
		$type   = str_replace( 'edmingle_fetch_', '', $action );
		$resume = isset( $_POST['resume'] ) && $_POST['resume'] === 'true';
		
		$endpoint = $this->get_endpoint_for_type( $type );
		if ( empty( $endpoint ) ) {
			wp_send_json_error( 'Endpoint not configured for ' . $type );
		}

		$state_key = 'etm_sync_state_' . $type;
		$state = get_option( $state_key, array(
			'current_page'    => 1,
			'total_pages'     => 1,
			'records_fetched' => 0,
			'total_records'   => 0,
			'status'          => 'idle',
			'next_cursor'     => '',
			'next_url'        => '',
		) );

		if ( ! $resume ) {
			// Start fresh
			$state['current_page']    = 1;
			$state['records_fetched'] = 0;
			$state['total_records']   = 0;
			$state['status']          = 'running';
			$state['next_cursor']     = '';
			$state['next_url']        = '';
		}

		// Prepare API request parameters based on state
		$query_params = array();
		if ( ! empty( $state['next_url'] ) ) {
			// If API returned a specific next_url, parse it
			$parsed = wp_parse_url( $state['next_url'] );
			if ( isset( $parsed['query'] ) ) {
				wp_parse_str( $parsed['query'], $query_params );
			}
			$endpoint = $parsed['path']; // Override endpoint just in case
		} else {
			// Standard page/limit or cursor
			$query_params['limit'] = 50;
			if ( ! empty( $state['next_cursor'] ) ) {
				$query_params['cursor'] = $state['next_cursor'];
			} else {
				$query_params['page'] = $state['current_page'];
				$query_params['offset'] = ( $state['current_page'] - 1 ) * 50;
				$query_params['start'] = ( $state['current_page'] - 1 ) * 50;
			}
		}

		$response = Edmingle_API::request( $endpoint, 'GET', array(), $query_params );

		if ( is_wp_error( $response ) ) {
			$state['status'] = 'failed';
			update_option( $state_key, $state );
			ETM_Database::log_request( $endpoint, 0, 500, $response->get_error_message(), $state['current_page'] );
			wp_send_json_error( array(
				'message' => $response->get_error_message(),
				'state'   => $state
			) );
		}

		$data           = $response['data'];
		$execution_time = $response['execution_time'];

		// Data structures vary, but typically lists are in 'data', 'resources', 'users', etc.
		$items = array();
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$items = $data['data'];
		} elseif ( isset( $data['students'] ) && is_array( $data['students'] ) ) {
			$items = $data['students'];
		} elseif ( isset( $data['courses'] ) && is_array( $data['courses'] ) ) {
			$items = $data['courses'];
		} elseif ( isset( $data['payload']['certificates'] ) && is_array( $data['payload']['certificates'] ) ) {
			$items = $data['payload']['certificates'];
		} elseif ( isset( $data['resources'] ) && is_array( $data['resources'] ) ) {
			$items = reset( $data['resources'] ); // Sometimes resources is an associative array of lists
		} else {
			// Fallback: use the full array if it looks like a list
			if ( is_array( $data ) && ! isset( $data['code'] ) ) {
				$items = $data;
			}
		}

		$table_name = 'edmingle_' . $type;
		$stats = array( 'imported' => 0, 'updated' => 0, 'skipped' => 0 );
		
		if ( ! empty( $items ) ) {
			// Determine ID key
			$id_key = 'id';
			$first_item = (array) reset( $items );
			if ( ! isset( $first_item['id'] ) ) {
				if ( isset( $first_item[ $type . '_id' ] ) ) {
					$id_key = $type . '_id';
				} elseif ( isset( $first_item['resource_id'] ) ) {
					$id_key = 'resource_id';
				} elseif ( isset( $first_item['user_id'] ) ) {
					$id_key = 'user_id';
				} else {
					foreach ( $first_item as $k => $v ) {
						if ( substr( $k, -3 ) === '_id' ) {
							$id_key = $k;
							break;
						}
					}
				}
			}

			// Loop Detection: If the first ID is identical to the last page, the API is ignoring pagination
			$first_id = $first_item[ $id_key ];
			if ( isset( $state['last_first_id'] ) && $state['last_first_id'] == $first_id && $state['current_page'] > 1 ) {
				$state['status'] = 'completed';
				update_option( $state_key, $state );
				wp_send_json_success( array(
					'state'          => $state,
					'stats'          => $stats,
					'items_in_page'  => count( $items ),
					'execution_time' => $execution_time,
				) );
				exit;
			}
			$state['last_first_id'] = $first_id;

			$stats = ETM_Database::save_data( $table_name, $items, $id_key );
			$state['records_fetched'] += ( $stats['imported'] + $stats['updated'] + $stats['skipped'] );
		}

		ETM_Database::log_request( $endpoint, $execution_time, $response['status_code'], '', $state['current_page'], $stats );

		// Pagination Engine: auto-detect next page or end of records
		$has_more = false;

		// 1. Check for specific pagination meta
		if ( isset( $data['meta']['pagination'] ) ) {
			$meta = $data['meta']['pagination'];
			$state['total_records'] = isset( $meta['total'] ) ? $meta['total'] : 0;
			$state['total_pages']   = isset( $meta['total_pages'] ) ? $meta['total_pages'] : 1;
			if ( isset( $meta['current_page'] ) && isset( $meta['total_pages'] ) && $meta['current_page'] < $meta['total_pages'] ) {
				$has_more = true;
				$state['current_page'] = $meta['current_page'] + 1;
			}
		} elseif ( isset( $data['total_count'] ) ) {
			$state['total_records'] = $data['total_count'];
			if ( $state['records_fetched'] < $data['total_count'] && count( $items ) > 0 ) {
				$has_more = true;
				$state['current_page']++;
			}
		} elseif ( isset( $data['next_page_url'] ) && ! empty( $data['next_page_url'] ) ) {
			$has_more = true;
			$state['next_url'] = $data['next_page_url'];
			$state['current_page']++;
		} elseif ( isset( $data['cursor'] ) && ! empty( $data['cursor'] ) ) {
			$has_more = true;
			$state['next_cursor'] = $data['cursor'];
			$state['current_page']++;
		} else {
			// Generic fallback: if we got exactly the limit we requested, assume there might be more
			if ( count( $items ) >= 50 ) {
				$has_more = true;
				$state['current_page']++;
			}
		}

		if ( ! $has_more ) {
			$state['status'] = 'completed';
		} else {
			$state['status'] = 'running';
		}

		update_option( $state_key, $state );

		wp_send_json_success( array(
			'state'          => $state,
			'stats'          => $stats,
			'items_in_page'  => count( $items ),
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
