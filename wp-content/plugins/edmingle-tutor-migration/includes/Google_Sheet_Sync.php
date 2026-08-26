<?php
/**
 * Google Sheets Synchronization Class
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Google_Sheet_Sync {

	/**
	 * List of user IDs registered during this request.
	 *
	 * @var array
	 */
	private static $users_to_sync = array();

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'queue_user_sync' ), 99 );
		add_action( 'shutdown', array( __CLASS__, 'process_queued_syncs' ) );
	}

	/**
	 * Queue user ID for sync.
	 *
	 * @param int $user_id
	 */
	public static function queue_user_sync( $user_id ) {
		if ( ! in_array( $user_id, self::$users_to_sync, true ) ) {
			self::$users_to_sync[] = $user_id;
		}
	}

	/**
	 * Process all queued user synchronizations on shutdown.
	 */
	public static function process_queued_syncs() {
		if ( empty( self::$users_to_sync ) ) {
			return;
		}

		// Prevent syncing during migrations to avoid timeouts and API exhaustion
		if ( defined( 'ETM_MIGRATION_IN_PROGRESS' ) && ETM_MIGRATION_IN_PROGRESS ) {
			return;
		}

		if ( ! get_option( 'etm_gsheet_sync_enabled', 0 ) ) {
			return;
		}

		foreach ( self::$users_to_sync as $user_id ) {
			self::sync_user( $user_id );
		}
	}

	/**
	 * Sync a single user to Google Sheets.
	 *
	 * @param int $user_id
	 * @return bool|\WP_Error True on success, WP_Error or false on failure.
	 */
	public static function sync_user( $user_id ) {
		// Ensure each user is synced only once
		if ( get_user_meta( $user_id, '_etm_synced_to_gsheet', true ) ) {
			return true;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Exclude administrators and instructors (sync only students/subscribers)
		$user_roles = (array) $user->roles;
		if ( in_array( 'administrator', $user_roles, true ) || in_array( 'tutor_instructor', $user_roles, true ) ) {
			update_user_meta( $user_id, '_etm_synced_to_gsheet', '1' ); // Mark as processed to exclude from future queries
			return true;
		}

		$webhook_url = get_option( 'etm_gsheet_webhook_url', '' );
		if ( empty( $webhook_url ) ) {
			return new \WP_Error( 'no_webhook', 'Google Sheets Apps Script URL not configured.' );
		}

		// Get Full Name
		$full_name = trim( $user->first_name . ' ' . $user->last_name );
		if ( empty( $full_name ) ) {
			$full_name = $user->display_name;
		}

		// Get Mobile Number (check different common meta keys)
		$mobile = get_user_meta( $user_id, 'phone_number', true );
		if ( empty( $mobile ) ) {
			$mobile = get_user_meta( $user_id, 'billing_phone', true );
		}
		if ( empty( $mobile ) ) {
			$mobile = get_user_meta( $user_id, 'mobile_number', true );
		}
		// Check POST just in case meta is not yet updated on DB but exists in POST
		if ( empty( $mobile ) ) {
			if ( isset( $_POST['mobile_number'] ) ) {
				$mobile = sanitize_text_field( wp_unslash( $_POST['mobile_number'] ) );
			} elseif ( isset( $_POST['phone_number'] ) ) {
				$mobile = sanitize_text_field( wp_unslash( $_POST['phone_number'] ) );
			}
		}

		// Format Registration Date (local time)
		$reg_date = get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' );

		// Retrieve latest course enrollment details
		$course_name = '—';
		$course_type = '—';
		$enrollment_args = array(
			'post_type'      => 'tutor_enrolled',
			'post_status'    => 'completed',
			'posts_per_page' => 1,
			'author'         => $user_id,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);
		$enrollments = get_posts( $enrollment_args );
		if ( ! empty( $enrollments ) ) {
			$latest_enrollment = $enrollments[0];
			$course_post = get_post( $latest_enrollment->post_parent );
			if ( $course_post ) {
				$course_name = $course_post->post_title;
				$price_type = get_post_meta( $course_post->ID, '_tutor_course_price_type', true );
				$course_type = ( $price_type === 'paid' ) ? 'Paid' : 'Free';
			}
		}

		$payload = array(
			'full_name'         => $full_name,
			'email'             => $user->user_email,
			'mobile'            => $mobile ? $mobile : '—',
			'registration_date' => $reg_date,
			'course_name'       => $course_name,
			'course_type'       => $course_type,
		);

		// Execute via Native cURL to ensure Google Apps Script redirect tracking resolves (status 200)
		$ch = curl_init( $webhook_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $payload ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // Bypass SSL validation checks for local testing/XAMPP
		
		$res = curl_exec( $ch );
		$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $ch );
		curl_close( $ch );

		if ( $status_code === 0 && ! empty( $curl_error ) ) {
			error_log( 'ETM Google Sheets Sync Failed for User ID ' . $user_id . ': ' . $curl_error );
			return new \WP_Error( 'curl_error', $curl_error );
		} else {
			if ( in_array( $status_code, array( 200, 201, 302 ), true ) ) {
				// Successfully synced
				update_user_meta( $user_id, '_etm_synced_to_gsheet', '1' );
				return true;
			} else {
				$msg = 'ETM Google Sheets Sync Failed for User ID ' . $user_id . ' with status ' . $status_code . '. Response: ' . $res;
				error_log( $msg );
				return new \WP_Error( 'sync_failed', $msg );
			}
		}
	}
}
