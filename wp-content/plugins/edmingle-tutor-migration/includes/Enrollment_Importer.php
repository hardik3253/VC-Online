<?php
/**
 * Enrollment Importer Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Enrollment_Importer {

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
	 * Import a batch of enrollments.
	 *
	 * @param int $page The page number for API pagination.
	 * @param int $limit Number of records per page.
	 * @return array Results summary (success, error counts, messages).
	 */
	public function import_batch( $page = 1, $limit = 50 ) {
		$results = array(
			'created' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'logs'    => array(),
		);

		// Fetch enrollments from Edmingle API
		$response = $this->api->get( '/api/v1/enrollments', array(
			'page'  => $page,
			'limit' => $limit,
		) );

		if ( is_wp_error( $response ) ) {
			\ETM\Includes\Logger::write_log( 'API Error fetching enrollments: ' . $response->get_error_message(), 'enrollments' );
			$results['errors']++;
			$results['logs'][] = 'API Error: ' . $response->get_error_message();
			return $results;
		}

		$enrollments = isset( $response['data'] ) ? $response['data'] : $response;

		if ( empty( $enrollments ) || ! is_array( $enrollments ) ) {
			\ETM\Includes\Logger::write_log( 'No enrollments found on page ' . $page, 'enrollments' );
			return $results;
		}

		$mapping = $this->mapper->get_mapping();

		foreach ( $enrollments as $enrollment ) {
			$edmingle_course_id = isset( $enrollment['course_id'] ) ? sanitize_text_field( $enrollment['course_id'] ) : '';
			$email              = isset( $enrollment['email'] ) ? sanitize_email( $enrollment['email'] ) : '';
			$enrollment_date    = isset( $enrollment['created_at'] ) ? sanitize_text_field( $enrollment['created_at'] ) : current_time( 'mysql' );
			
			// 1. Validate User
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$results['skipped']++;
				$msg = 'Skipped: User not found for email ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'enrollments' );
				$results['logs'][] = $msg;
				continue;
			}
			$user_id = $user->ID;

			// 2. Validate Course Mapping
			if ( ! isset( $mapping[ $edmingle_course_id ] ) ) {
				$results['skipped']++;
				$msg = 'Skipped: Edmingle Course ID ' . $edmingle_course_id . ' is not mapped to any Tutor LMS course.';
				\ETM\Includes\Logger::write_log( $msg, 'enrollments' );
				$results['logs'][] = $msg;
				continue;
			}
			
			$tutor_course_id = $mapping[ $edmingle_course_id ];

			// 3. Check for duplicates
			if ( function_exists( 'tutor_utils' ) ) {
				$is_enrolled = tutor_utils()->is_enrolled( $tutor_course_id, $user_id );
				if ( $is_enrolled ) {
					$results['skipped']++;
					$msg = 'Skipped: User ' . $email . ' is already enrolled in Course ID ' . $tutor_course_id;
					\ETM\Includes\Logger::write_log( $msg, 'enrollments' );
					$results['logs'][] = $msg;
					continue;
				}
			}

			// 4. Create Enrollment
			$enrollment_status = 'completed'; // Standard tutor_enrolled status
			
			// Tutor LMS native way to enroll a user creates a 'tutor_enrolled' post type.
			$enrollment_data = array(
				'post_type'     => 'tutor_enrolled',
				'post_title'    => 'Enrolled', // Tutor LMS standard title
				'post_status'   => $enrollment_status,
				'post_author'   => $user_id,
				'post_parent'   => $tutor_course_id,
				'post_date'     => date( 'Y-m-d H:i:s', strtotime( $enrollment_date ) ),
				'post_date_gmt' => get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $enrollment_date ) ) ),
			);

			$enrollment_id = wp_insert_post( $enrollment_data );

			if ( is_wp_error( $enrollment_id ) ) {
				$results['errors']++;
				$msg = 'Error creating enrollment for user ' . $email . ': ' . $enrollment_id->get_error_message();
				\ETM\Includes\Logger::write_log( $msg, 'enrollments' );
				$results['logs'][] = $msg;
			} else {
				// Mark as free or paid based on data if available, or just fallback to tutor_utils
				if ( function_exists( 'tutor_utils' ) ) {
					update_post_meta( $enrollment_id, '_tutor_enrolled_by_course_id', $tutor_course_id );
					update_post_meta( $enrollment_id, '_tutor_enrolled_by_order_id', 0 ); // 0 usually denotes manual/free enrollment
				}

				$results['created']++;
				$msg = 'Successfully enrolled user ' . $email . ' into course ' . $tutor_course_id;
				\ETM\Includes\Logger::write_log( $msg, 'enrollments' );
				$results['logs'][] = $msg;
			}
		}

		return $results;
	}
}
