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
	 * Run the plugin.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Execution handled by constructor/hooks.
	}
}
