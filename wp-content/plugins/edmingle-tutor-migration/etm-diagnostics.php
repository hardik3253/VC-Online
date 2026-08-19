<?php
/**
 * ETM Diagnostics Tool
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	die( 'Error: wp-load.php not found.' );
}
require_once $wp_load_path;

if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Access denied. You must be logged in as an administrator.' );
}

header( 'Content-Type: application/json' );

global $wpdb;

$diagnostics = array(
	'php_version' => PHP_VERSION,
	'memory_limit' => ini_get( 'memory_limit' ),
	'max_execution_time' => ini_get( 'max_execution_time' ),
	'mysql_version' => $wpdb->db_version(),
	'tables' => array(),
	'api_connection' => array(),
);

// 1. Check tables
$tables = array(
	'edmingle_students',
	'edmingle_courses',
	'edmingle_batches',
	'edmingle_curriculum',
	'edmingle_materials',
	'edmingle_certificates',
	'edmingle_notifications',
	'edmingle_logs',
);

foreach ( $tables as $table ) {
	$full_table_name = $wpdb->prefix . $table;
	$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_table_name ) );
	$diagnostics['tables'][$table] = array(
		'name' => $full_table_name,
		'exists' => ! empty( $table_exists ),
	);
	
	if ( ! empty( $table_exists ) ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $full_table_name" );
		$diagnostics['tables'][$table]['record_count'] = intval( $count );
	}
}

// 2. Test API connection
$base_url = get_option( 'etm_api_base_url' );
$diagnostics['api_connection']['base_url'] = $base_url;

$start_mem = memory_get_usage();
$start_time = microtime( true );

$response = \ETM\Includes\Edmingle_API::request( 'organization/students', 'GET', array( 'limit' => 5 ) );

$end_time = microtime( true );
$end_mem = memory_get_usage();

$diagnostics['api_connection']['time_taken_ms'] = round( ( $end_time - $start_time ) * 1000 );
$diagnostics['api_connection']['memory_used_kb'] = round( ( $end_mem - $start_mem ) / 1024 );

if ( is_wp_error( $response ) ) {
	$diagnostics['api_connection']['status'] = 'failed';
	$diagnostics['api_connection']['error'] = $response->get_error_message();
} else {
	$diagnostics['api_connection']['status'] = 'success';
	$diagnostics['api_connection']['status_code'] = isset( $response['status_code'] ) ? $response['status_code'] : 'unknown';
	$items = isset( $response['data']['data'] ) ? $response['data']['data'] : ( isset( $response['data']['students'] ) ? $response['data']['students'] : array() );
	$diagnostics['api_connection']['records_count'] = count( $items );
	
	// Test save to DB
	$diagnostics['db_write_test'] = array();
	try {
		$db_start_time = microtime( true );
		$stats = \ETM\Includes\ETM_Database::save_data( 'edmingle_students', $items, 'id' );
		$db_end_time = microtime( true );
		$diagnostics['db_write_test']['status'] = 'success';
		$diagnostics['db_write_test']['time_taken_ms'] = round( ( $db_end_time - $db_start_time ) * 1000 );
		$diagnostics['db_write_test']['stats'] = $stats;
	} catch ( \Exception $e ) {
		$diagnostics['db_write_test']['status'] = 'failed';
		$diagnostics['db_write_test']['error'] = $e->getMessage();
	}
}

// 3. Check error logs
$diagnostics['error_logs'] = array();
$log_files = array(
	'wp-content/debug.log' => WP_CONTENT_DIR . '/debug.log',
	'root error_log'       => ABSPATH . 'error_log',
	'wp-admin error_log'   => ABSPATH . 'wp-admin/error_log',
);

foreach ( $log_files as $label => $file_path ) {
	if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
		$diagnostics['error_logs'][$label] = array(
			'path' => $file_path,
			'size' => size_format( filesize( $file_path ) ),
			'last_lines' => array(),
		);
		
		// Read last 30 lines
		$file = new SplFileObject( $file_path, 'r' );
		$file->seek( PHP_INT_MAX );
		$total_lines = $file->key();
		
		$start_line = max( 0, $total_lines - 30 );
		$file->seek( $start_line );
		
		while ( ! $file->eof() ) {
			$line = trim( $file->current() );
			if ( ! empty( $line ) ) {
				$diagnostics['error_logs'][$label]['last_lines'][] = $line;
			}
			$file->next();
		}
	}
}

echo json_encode( array( 'diagnostics_info' => $diagnostics ), JSON_PRETTY_PRINT );
echo "\n\n--- AJAX SIMULATION START ---\n\n";

$_POST['action'] = 'edmingle_fetch_students';
$_POST['resume'] = 'false';
$_POST['nonce'] = wp_create_nonce( 'etm_admin_nonce' );
$_SERVER['HTTP_REFERER'] = admin_url();

$explorer = new \ETM\Admin\Data_Explorer();
$explorer->ajax_fetch_data();
exit;
