<?php
/**
 * Database Management Class
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class ETM_Database {

	/**
	 * Create required database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = array(
			'edmingle_students',
			'edmingle_courses',
			'edmingle_batches',
			'edmingle_curriculum',
			'edmingle_materials',
			'edmingle_certificates',
			'edmingle_notifications',
		);

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				edmingle_id varchar(255) NOT NULL,
				json_data longtext NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY edmingle_id (edmingle_id)
			) $charset_collate;";

			dbDelta( $sql );
		}

		// Create logs table
		$logs_table = $wpdb->prefix . 'edmingle_logs';
		$sql = "CREATE TABLE $logs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url text NOT NULL,
			execution_time int(11) NOT NULL,
			status_code int(11) NOT NULL,
			error_message text,
			rows_saved int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Save API response to a table.
	 *
	 * @param string $table_name e.g., 'edmingle_students'
	 * @param array  $items Array of objects/arrays containing id and data.
	 * @param string $id_key The key to use for edmingle_id.
	 * @return int Number of rows inserted/updated.
	 */
	public static function save_data( $table_name, $items, $id_key = 'id' ) {
		global $wpdb;
		$table = $wpdb->prefix . $table_name;
		$rows_saved = 0;

		foreach ( $items as $item ) {
			// Convert object to array for easier access
			if ( is_object( $item ) ) {
				$item = (array) $item;
			}
			
			if ( ! isset( $item[ $id_key ] ) ) {
				continue;
			}

			$edmingle_id = sanitize_text_field( $item[ $id_key ] );
			$json_data   = wp_json_encode( $item );

			// Insert or update
			$result = $wpdb->query( $wpdb->prepare(
				"INSERT INTO $table (edmingle_id, json_data, created_at, updated_at) 
				 VALUES (%s, %s, current_timestamp(), current_timestamp())
				 ON DUPLICATE KEY UPDATE json_data = %s, updated_at = current_timestamp()",
				$edmingle_id, $json_data, $json_data
			) );

			if ( $result !== false ) {
				$rows_saved++;
			}
		}

		return $rows_saved;
	}

	/**
	 * Log API request.
	 *
	 * @param string $url
	 * @param int    $execution_time
	 * @param int    $status_code
	 * @param string $error_message
	 * @param int    $rows_saved
	 */
	public static function log_request( $url, $execution_time, $status_code, $error_message = '', $rows_saved = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'edmingle_logs';

		$wpdb->insert(
			$table,
			array(
				'url'            => sanitize_url( $url ),
				'execution_time' => intval( $execution_time ),
				'status_code'    => intval( $status_code ),
				'error_message'  => sanitize_text_field( $error_message ),
				'rows_saved'     => intval( $rows_saved ),
			),
			array( '%s', '%d', '%d', '%s', '%d' )
		);
	}

	/**
	 * Get total records for a table.
	 */
	public static function get_total_records( $table_name ) {
		global $wpdb;
		$table = $wpdb->prefix . $table_name;
		return (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table" );
	}

	/**
	 * Get last sync time for a table.
	 */
	public static function get_last_sync( $table_name ) {
		global $wpdb;
		$table = $wpdb->prefix . $table_name;
		$time = $wpdb->get_var( "SELECT MAX(updated_at) FROM $table" );
		return $time ? $time : 'Never';
	}

	/**
	 * Get recent records.
	 */
	public static function get_records( $table_name, $limit = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . $table_name;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY updated_at DESC LIMIT %d", $limit ) );
	}
	
	/**
	 * Get recent logs.
	 */
	public static function get_recent_logs( $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'edmingle_logs';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d", $limit ) );
	}
}
