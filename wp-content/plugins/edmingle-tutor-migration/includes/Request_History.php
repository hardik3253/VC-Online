<?php
/**
 * Request History Manager
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Request_History {

	private $option_name = 'etm_api_history';
	private $max_history = 20;

	/**
	 * Save a request to history.
	 *
	 * @param array $request_data
	 */
	public function save_request( $request_data ) {
		$history = $this->get_history();

		// Add new request at the beginning
		array_unshift( $history, $request_data );

		// Keep only the last 20 requests
		if ( count( $history ) > $this->max_history ) {
			$history = array_slice( $history, 0, $this->max_history );
		}

		update_option( $this->option_name, $history, false );
	}

	/**
	 * Get the request history.
	 *
	 * @return array
	 */
	public function get_history() {
		$history = get_option( $this->option_name, array() );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Delete a specific request by index.
	 *
	 * @param int $index
	 * @return bool
	 */
	public function delete_request( $index ) {
		$history = $this->get_history();
		if ( isset( $history[ $index ] ) ) {
			unset( $history[ $index ] );
			$history = array_values( $history ); // Re-index array
			update_option( $this->option_name, $history, false );
			return true;
		}
		return false;
	}

	/**
	 * Clear all history.
	 */
	public function clear_history() {
		delete_option( $this->option_name );
	}
}
