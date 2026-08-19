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
	$diagnostics['api_connection']['records_count'] = isset( $response['data']['data'] ) ? count( $response['data']['data'] ) : ( isset( $response['data']['students'] ) ? count( $response['data']['students'] ) : 0 );
}

echo json_encode( $diagnostics, JSON_PRETTY_PRINT );
exit;
