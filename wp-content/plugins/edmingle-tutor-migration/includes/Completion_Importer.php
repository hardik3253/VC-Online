<?php
/**
 * Course Completion Importer Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Completion_Importer {

	/**
	 * API Client instance.
	 *
	 * @var ApiClient
	 */
	private $api;

	/**
	 * Course Mapper instance.
	 *
	 * @var Course_Mapper
	 */
	private $mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api    = new ApiClient();
		$this->mapper = new Course_Mapper();
	}

	/**
	 * Import a batch of course completions.
	 *
	 * @param int $page The page number for API pagination.
	 * @param int $limit Number of records per page.
	 * @return array Results summary.
	 */
	public function import_batch( $page = 1, $limit = 50 ) {
		$results = array(
			'completed' => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'logs'      => array(),
		);

		// Fetch completions from Edmingle API
		$response = $this->api->get( '/api/v1/completions', array(
			'page'  => $page,
			'limit' => $limit,
		) );

		if ( is_wp_error( $response ) ) {
			\ETM\Includes\Logger::write_log( 'API Error fetching completions: ' . $response->get_error_message(), 'completions' );
			$results['errors']++;
			$results['logs'][] = 'API Error: ' . $response->get_error_message();
			return $results;
		}

		$completion_records = isset( $response['data'] ) ? $response['data'] : $response;

		if ( empty( $completion_records ) || ! is_array( $completion_records ) ) {
			\ETM\Includes\Logger::write_log( 'No completion records found on page ' . $page, 'completions' );
			return $results;
		}

		$mapping = $this->mapper->get_mapping();

		foreach ( $completion_records as $record ) {
			$edmingle_course_id = isset( $record['course_id'] ) ? sanitize_text_field( $record['course_id'] ) : '';
			$email              = isset( $record['email'] ) ? sanitize_email( $record['email'] ) : '';
			$completion_date    = isset( $record['completed_at'] ) ? sanitize_text_field( $record['completed_at'] ) : current_time( 'mysql' );
			
			// 1. Validate User
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$results['skipped']++;
				$msg = 'Skipped: User not found for email ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'completions' );
				$results['logs'][] = $msg;
				continue;
			}
			$user_id = $user->ID;

			// 2. Validate Course Mapping
			if ( ! isset( $mapping[ $edmingle_course_id ] ) ) {
				$results['skipped']++;
				$msg = 'Skipped: Edmingle Course ID ' . $edmingle_course_id . ' is not mapped to any Tutor LMS course.';
				\ETM\Includes\Logger::write_log( $msg, 'completions' );
				$results['logs'][] = $msg;
				continue;
			}
			$tutor_course_id = $mapping[ $edmingle_course_id ];

			// 3. Ensure Enrollment exists
			if ( function_exists( 'tutor_utils' ) && ! tutor_utils()->is_enrolled( $tutor_course_id, $user_id ) ) {
				$results['skipped']++;
				$msg = 'Skipped: User ' . $email . ' is not enrolled in Tutor Course ID ' . $tutor_course_id;
				\ETM\Includes\Logger::write_log( $msg, 'completions' );
				$results['logs'][] = $msg;
				continue;
			}

			// 4. Skip if already completed
			if ( function_exists( 'tutor_utils' ) && tutor_utils()->is_completed_course( $tutor_course_id, $user_id ) ) {
				$results['skipped']++;
				$msg = 'Skipped: Course ' . $tutor_course_id . ' is already marked as completed for user ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'completions' );
				$results['logs'][] = $msg;
				continue;
			}

			// 5. Complete Course via Native Tutor LMS methods
			if ( function_exists( 'tutor_utils' ) ) {
				// We update the _tutor_course_progress metadata to 100 first to satisfy requirements
				update_user_meta( $user_id, '_tutor_course_progress_' . $tutor_course_id, 100 );
				
				// Mark the course itself complete
				tutor_utils()->mark_course_complete( $tutor_course_id, $user_id );
				
				// Some Tutor functions rely on standard completion actions to update reports,
				// do_action('tutor_course_complete_after') might be necessary if we bypass front-end forms,
				// but mark_course_complete() usually fires necessary hooks.
				
				$results['completed']++;
				$msg = 'Successfully completed course ' . $tutor_course_id . ' for user ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'completions' );
				$results['logs'][] = $msg;
			} else {
				$results['errors']++;
				$msg = 'Error: Tutor LMS utility functions not found.';
				\ETM\Includes\Logger::write_log( $msg, 'completions' );
				$results['logs'][] = $msg;
			}
		}

		return $results;
	}
}
