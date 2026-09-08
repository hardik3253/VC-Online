<?php
/**
 * Core Plugin Class
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Plugin {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		\ETM\Includes\Google_Sheet_Sync::init();
	}

	/**
	 * Load required dependencies.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		require_once ETM_PLUGIN_DIR . 'admin/Admin.php';
		require_once ETM_PLUGIN_DIR . 'admin/Migration_Engine.php';
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since 1.0.0
	 */
	private function define_admin_hooks() {
		$plugin_admin = new \ETM\Admin\Admin();

		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );

		// New AJAX Endpoints for Phase 0
		add_action( 'wp_ajax_etm_test_connection', array( $plugin_admin, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_etm_execute_api', array( $plugin_admin, 'ajax_execute_api' ) );
		add_action( 'wp_ajax_etm_delete_history', array( $plugin_admin, 'ajax_delete_history' ) );
		add_action( 'wp_ajax_etm_toggle_debug', array( $plugin_admin, 'ajax_toggle_debug' ) );
		add_action( 'wp_ajax_etm_get_unsynced_users_count', array( $plugin_admin, 'ajax_get_unsynced_users_count' ) );
		add_action( 'wp_ajax_etm_sync_existing_users_batch', array( $plugin_admin, 'ajax_sync_existing_users_batch' ) );
		add_action( 'wp_ajax_etm_reset_gsheet_sync', array( $plugin_admin, 'ajax_reset_gsheet_sync' ) );

		// Display newly registered users first by default on users list screen
		add_action( 'pre_get_users', array( $this, 'sort_users_by_registration_date' ) );

		// Ensure Tutor LMS Orders & Students lists display newest items first by date
		add_filter( 'query', array( $this, 'filter_tutor_admin_queries_order' ) );

		// Data Explorer Registration
		$data_explorer = new \ETM\Admin\Data_Explorer();
		$data_explorer->register();

		// Setup Wizard Registration
		$setup_wizard = new \ETM\Admin\Setup_Wizard();
		$setup_wizard->register();

		// Migration Engine Registration
		$migration_engine = new \ETM\Admin\Migration_Engine();
		$migration_engine->register();

		// Custom filter to render migrated progress percentage correctly in Tutor LMS
		add_filter( 'tutor_course_completed_percent', array( $this, 'filter_course_completed_percent' ), 10, 4 );
	}

	/**
	 * Render migrated progress percent values correctly inside Tutor LMS.
	 */
	public function filter_course_completed_percent( $result, $course_id, $user_id, $get_stats ) {
		$progress = (int) get_user_meta( $user_id, '_tutor_course_progress_' . $course_id, true );
		if ( $progress > 0 ) {
			if ( $get_stats ) {
				$total_lessons = count( tutor_utils()->get_course_content_ids_by( 'lesson', 'courses', $course_id ) );
				$completed_lessons = round( ( $progress / 100 ) * $total_lessons );
				return array(
					'completed_percent' => $progress,
					'completed_count'   => $completed_lessons,
					'total_count'       => $total_lessons
				);
			}
			return $progress;
		}
		return $result;
	}

	/**
	 * Sort WordPress Users List by registration date descending by default.
	 *
	 * @param \WP_User_Query $query
	 */
	public function sort_users_by_registration_date( $query ) {
		global $pagenow;
		if ( is_admin() && 'users.php' === $pagenow ) {
			if ( ! isset( $_GET['orderby'] ) ) {
				$query->set( 'orderby', 'registered' );
				$query->set( 'order', 'DESC' );
			}
		}
	}

	/**
	 * Filter SQL queries in Tutor LMS admin to ensure newest orders and students appear first.
	 *
	 * @param string $query
	 * @return string
	 */
	public function filter_tutor_admin_queries_order( $query ) {
		global $wpdb;

		// 1. Tutor LMS Orders list: Order by created_at_gmt DESC by default instead of o.id DESC
		if ( strpos( $query, 'tutor_orders' ) !== false && strpos( $query, 'ORDER BY o.id' ) !== false ) {
			$order_dir = ( isset( $_GET['order'] ) && strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) === 'asc' ) ? 'ASC' : 'DESC';
			$query = preg_replace( '/ORDER BY\s+o\.id\s+(DESC|ASC)/i', "ORDER BY o.created_at_gmt {$order_dir}, o.id {$order_dir}", $query );
		}

		// 2. Tutor LMS Students list query: include all students (even with 0 courses) ordered by user_registered DESC
		if ( strpos( $query, 'tutor_enrolled' ) !== false && strpos( $query, 'GROUP BY post_author' ) !== false && strpos( $query, 'SELECT user.* FROM' ) !== false ) {
			$course_join  = '';
			$course_query = '';
			if ( preg_match( '/posts\.post_parent\s*=\s*(\d+)/', $query, $matches ) ) {
				$course_id    = intval( $matches[1] );
				$course_join  = "INNER JOIN {$wpdb->posts} posts ON user.ID = posts.post_author AND posts.post_type = 'tutor_enrolled' AND posts.post_status = 'completed'";
				$course_query = "AND posts.post_parent = {$course_id}";
			}

			$date_query = '';
			if ( preg_match( '/DATE\(user\.user_registered\)\s*=\s*CAST\(([^)]+)\s*AS DATE\)/', $query, $matches ) ) {
				$date_query = "AND DATE(user.user_registered) = CAST({$matches[1]} AS DATE)";
			}

			$order_dir   = ( isset( $_GET['order'] ) && strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) === 'asc' ) ? 'ASC' : 'DESC';
			$order_query = "ORDER BY user.user_registered {$order_dir}";

			preg_match( '/LIMIT\s+(\d+),\s*(\d+)/i', $query, $limit_matches );
			$limit_clause = ! empty( $limit_matches ) ? "LIMIT {$limit_matches[1]}, {$limit_matches[2]}" : '';

			preg_match( '/AND\s*\((user\.display_name\s+LIKE[^\)]+)\)/is', $query, $search_matches );
			$search_clause = ! empty( $search_matches ) ? 'AND (' . $search_matches[1] . ')' : '';

			$query = "SELECT user.* FROM {$wpdb->users} AS user
				LEFT JOIN {$wpdb->usermeta} AS meta 
					ON user.ID = meta.user_id AND meta.meta_key = '{$wpdb->prefix}capabilities'
				{$course_join}
				WHERE (meta.meta_value IS NULL OR (meta.meta_value NOT LIKE '%administrator%' AND meta.meta_value NOT LIKE '%tutor_instructor%'))
					{$course_query}
					{$date_query}
					{$search_clause}
				GROUP BY user.ID
				{$order_query}
				{$limit_clause}";
		}

		// 3. Tutor LMS Students total count query
		if ( strpos( $query, 'tutor_enrolled' ) !== false && strpos( $query, 'SELECT user.ID FROM' ) !== false && strpos( $query, 'GROUP BY user.ID' ) !== false ) {
			$course_join  = '';
			$course_query = '';
			if ( preg_match( '/posts\.post_parent\s*=\s*(\d+)/', $query, $matches ) ) {
				$course_id    = intval( $matches[1] );
				$course_join  = "INNER JOIN {$wpdb->posts} posts ON user.ID = posts.post_author AND posts.post_type = 'tutor_enrolled' AND posts.post_status = 'completed'";
				$course_query = "AND posts.post_parent = {$course_id}";
			}

			$date_query = '';
			if ( preg_match( '/DATE\(user\.user_registered\)\s*=\s*CAST\(([^)]+)\s*AS DATE\)/', $query, $matches ) ) {
				$date_query = "AND DATE(user.user_registered) = CAST({$matches[1]} AS DATE)";
			}

			preg_match( '/AND\s*\((user\.display_name\s+LIKE[^\)]+)\)/is', $query, $search_matches );
			$search_clause = ! empty( $search_matches ) ? 'AND (' . $search_matches[1] . ')' : '';

			$query = "SELECT user.ID FROM {$wpdb->users} AS user
				LEFT JOIN {$wpdb->usermeta} AS meta 
					ON user.ID = meta.user_id AND meta.meta_key = '{$wpdb->prefix}capabilities'
				{$course_join}
				WHERE (meta.meta_value IS NULL OR (meta.meta_value NOT LIKE '%administrator%' AND meta.meta_value NOT LIKE '%tutor_instructor%'))
					{$course_query}
					{$date_query}
					{$search_clause}
				GROUP BY user.ID";
		}

		return $query;
	}


	/**
	 * Run the plugin.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Execution handled by constructor/hooks.
	}
}
