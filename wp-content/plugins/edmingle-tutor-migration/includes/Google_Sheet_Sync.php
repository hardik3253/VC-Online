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

		$payload = array(
			'full_name'         => $full_name,
			'email'             => $user->user_email,
			'mobile'            => $mobile ? $mobile : '—',
			'registration_date' => $reg_date,
		);

		$response = wp_remote_post( $webhook_url, array(
			'method'      => 'POST',
			'timeout'     => 15,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'ETM Google Sheets Sync Failed for User ID ' . $user_id . ': ' . $response->get_error_message() );
			return $response;
		} else {
			$status_code = wp_remote_retrieve_response_code( $response );
			// If we got 200, 201, or 302, it is a success. Google Script returns 302/200 on Web App deployment redirects.
			if ( in_array( $status_code, array( 200, 201, 302 ), true ) ) {
				// Successfully synced
				update_user_meta( $user_id, '_etm_synced_to_gsheet', '1' );
				return true;
			} else {
				$body = wp_remote_retrieve_body( $response );
				$msg = 'ETM Google Sheets Sync Failed for User ID ' . $user_id . ' with status ' . $status_code . '. Response: ' . $body;
				error_log( $msg );
				return new \WP_Error( 'sync_failed', $msg );
			}
		}
	}
}
