<?php
/**
 * Google Sheets Synchronization Class
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Google_Sheet_Sync {

	/**
	 * List of user IDs and their sync parameters queued during this request.
	 *
	 * @var array
	 */
	private static $users_to_sync = array();

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 99 );
		add_action( 'tutor_after_enrolled', array( __CLASS__, 'on_tutor_after_enrolled' ), 20, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_enrollment_status_transition' ), 20, 3 );
		add_action( 'shutdown', array( __CLASS__, 'process_queued_syncs' ) );
	}

	/**
	 * Hook callback on user registration.
	 *
	 * @param int $user_id
	 */
	public static function on_user_register( $user_id ) {
		self::queue_user_sync( $user_id, true );
	}

	/**
	 * Hook callback when student is enrolled in Tutor LMS (Free, Paid, or Manual).
	 *
	 * @param int $course_id
	 * @param int $user_id
	 * @param int $enrolled_id
	 */
	public static function on_tutor_after_enrolled( $course_id, $user_id, $enrolled_id ) {
		self::queue_user_sync( $user_id, true, $course_id, $enrolled_id );
	}

	/**
	 * Hook callback on enrollment post status transition (e.g. pending to completed).
	 *
	 * @param string   $new_status
	 * @param string   $old_status
	 * @param \WP_Post $post
	 */
	public static function on_enrollment_status_transition( $new_status, $old_status, $post ) {
		if ( ! empty( $post ) && 'tutor_enrolled' === $post->post_type && 'completed' === $new_status && 'completed' !== $old_status ) {
			$user_id   = (int) $post->post_author;
			$course_id = (int) $post->post_parent;
			if ( $user_id > 0 ) {
				self::queue_user_sync( $user_id, true, $course_id, $post->ID );
			}
		}
	}

	/**
	 * Queue user ID for sync.
	 *
	 * @param int  $user_id
	 * @param bool $force Force sync even if already marked as synced (used for enrollments/updates).
	 * @param int  $course_id Optional specific course ID.
	 * @param int  $enrollment_id Optional specific enrollment post ID.
	 */
	public static function queue_user_sync( $user_id, $force = false, $course_id = 0, $enrollment_id = 0 ) {
		if ( empty( $user_id ) ) {
			return;
		}

		self::$users_to_sync[ $user_id ] = array(
			'force'         => $force,
			'course_id'     => $course_id,
			'enrollment_id' => $enrollment_id,
		);
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

		foreach ( self::$users_to_sync as $user_id => $data ) {
			self::sync_user( $user_id, $data['force'], $data['course_id'], $data['enrollment_id'] );
		}
	}

	/**
	 * Sync a single user to Google Sheets.
	 *
	 * @param int  $user_id
	 * @param bool $force Whether to force sync even if _etm_synced_to_gsheet is set.
	 * @param int  $course_id Optional course ID.
	 * @param int  $enrollment_id Optional enrollment ID.
	 * @return bool|\WP_Error True on success, WP_Error or false on failure.
	 */
	public static function sync_user( $user_id, $force = false, $course_id = 0, $enrollment_id = 0 ) {
		// If not forced and already marked as synced (batch sync check), skip
		if ( ! $force && get_user_meta( $user_id, '_etm_synced_to_gsheet', true ) ) {
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

		// Retrieve ALL completed course enrollment details for this user
		$course_names     = array();
		$course_types     = array();
		$enrollment_dates = array();
		$seen_course_ids  = array();

		$enrollment_args = array(
			'post_type'      => 'tutor_enrolled',
			'post_status'    => 'completed',
			'posts_per_page' => -1,
			'author'         => $user_id,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);
		$enrollments = get_posts( $enrollment_args );

		if ( ! empty( $enrollments ) ) {
			foreach ( $enrollments as $enrollment ) {
				$c_id = (int) $enrollment->post_parent;
				if ( in_array( $c_id, $seen_course_ids, true ) ) {
					continue;
				}
				$seen_course_ids[] = $c_id;

				$course_post = get_post( $c_id );
				if ( $course_post ) {
					$course_names[] = $course_post->post_title;
					$price_type     = get_post_meta( $course_post->ID, '_tutor_course_price_type', true );
					$course_types[] = ( 'paid' === $price_type ) ? 'Paid' : 'Free';
				}

				// Format Enrollment/Purchase Date (local time)
				if ( ! empty( $enrollment->post_date_gmt ) && '0000-00-00 00:00:00' !== $enrollment->post_date_gmt ) {
					$enrollment_dates[] = get_date_from_gmt( $enrollment->post_date_gmt, 'Y-m-d H:i:s' );
				} elseif ( ! empty( $enrollment->post_date ) ) {
					$enrollment_dates[] = $enrollment->post_date;
				}
			}
		}

		// If a specific course ID was passed and was not in the query results yet
		if ( $course_id > 0 && ! in_array( $course_id, $seen_course_ids, true ) ) {
			$course_post = get_post( $course_id );
			if ( $course_post ) {
				array_unshift( $course_names, $course_post->post_title );
				$price_type = get_post_meta( $course_post->ID, '_tutor_course_price_type', true );
				array_unshift( $course_types, ( 'paid' === $price_type ) ? 'Paid' : 'Free' );
			}
		}

		$course_name_str = ! empty( $course_names ) ? implode( ', ', $course_names ) : '—';

		if ( ! empty( $course_types ) ) {
			$has_paid = in_array( 'Paid', $course_types, true );
			$has_free = in_array( 'Free', $course_types, true );
			if ( $has_paid && $has_free ) {
				$course_type_str = 'Paid, Free';
			} elseif ( $has_paid ) {
				$course_type_str = 'Paid';
			} else {
				$course_type_str = 'Free';
			}
		} else {
			$course_type_str = '—';
		}

		$latest_enrollment_date = ! empty( $enrollment_dates ) ? $enrollment_dates[0] : '';

		// Action date: use latest enrollment/purchase date if enrolled, fallback to registration date
		$action_date = ! empty( $latest_enrollment_date ) ? $latest_enrollment_date : $reg_date;

		$payload = array(
			'full_name'         => $full_name,
			'email'             => $user->user_email,
			'mobile'            => $mobile ? $mobile : '—',
			'registration_date' => $reg_date,
			'enrollment_date'   => $latest_enrollment_date ? $latest_enrollment_date : '—',
			'date'              => $action_date,
			'course_name'       => $course_name_str,
			'course_type'       => $course_type_str,
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

		$res         = curl_exec( $ch );
		$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error  = curl_error( $ch );
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

