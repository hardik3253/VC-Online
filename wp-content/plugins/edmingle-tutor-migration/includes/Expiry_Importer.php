<?php
/**
 * Course Expiry Importer Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Expiry_Importer {

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
	 * Import a batch of course expirations.
	 *
	 * @param int $page The page number for API pagination.
	 * @param int $limit Number of records per page.
	 * @return array Results summary.
	 */
	public function import_batch( $page = 1, $limit = 50 ) {
		$results = array(
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'logs'    => array(),
		);

		// Fetch expiry data from Edmingle API
		$response = $this->api->get( '/api/v1/expiries', array(
			'page'  => $page,
			'limit' => $limit,
		) );

		if ( is_wp_error( $response ) ) {
			\ETM\Includes\Logger::write_log( 'API Error fetching expiries: ' . $response->get_error_message(), 'expiries' );
			$results['errors']++;
			$results['logs'][] = 'API Error: ' . $response->get_error_message();
			return $results;
		}

		$expiry_records = isset( $response['data'] ) ? $response['data'] : $response;

		if ( empty( $expiry_records ) || ! is_array( $expiry_records ) ) {
			\ETM\Includes\Logger::write_log( 'No expiry records found on page ' . $page, 'expiries' );
			return $results;
		}

		$mapping = $this->mapper->get_mapping();

		foreach ( $expiry_records as $record ) {
			$edmingle_course_id = isset( $record['course_id'] ) ? sanitize_text_field( $record['course_id'] ) : '';
			$email              = isset( $record['email'] ) ? sanitize_email( $record['email'] ) : '';
			
			// Expiry can be a date or boolean 'expired' flag
			$expiry_date = isset( $record['expiry_date'] ) ? sanitize_text_field( $record['expiry_date'] ) : '';
			$is_expired  = isset( $record['is_expired'] ) ? filter_var( $record['is_expired'], FILTER_VALIDATE_BOOLEAN ) : false;

			// 1. Validate User
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$results['skipped']++;
				$msg = 'Skipped: User not found for email ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'expiries' );
				$results['logs'][] = $msg;
				continue;
			}
			$user_id = $user->ID;

			// 2. Validate Course Mapping
			if ( ! isset( $mapping[ $edmingle_course_id ] ) ) {
				$results['skipped']++;
				$msg = 'Skipped: Edmingle Course ID ' . $edmingle_course_id . ' is not mapped to any Tutor course.';
				\ETM\Includes\Logger::write_log( $msg, 'expiries' );
				$results['logs'][] = $msg;
				continue;
			}
			$tutor_course_id = $mapping[ $edmingle_course_id ];

			// 3. Find Enrollment Record
			$enrollment = $this->get_enrollment_record( $tutor_course_id, $user_id );
			
			if ( ! $enrollment ) {
				$results['skipped']++;
				$msg = 'Skipped: Active enrollment not found for user ' . $email . ' in course ' . $tutor_course_id;
				\ETM\Includes\Logger::write_log( $msg, 'expiries' );
				$results['logs'][] = $msg;
				continue;
			}

			// 4. Update Expiry status and date
			$updated = false;

			// Save explicit expiry date if provided
			if ( ! empty( $expiry_date ) ) {
				update_post_meta( $enrollment->ID, '_tutor_enrolled_expiry_date', date( 'Y-m-d H:i:s', strtotime( $expiry_date ) ) );
				$updated = true;
				
				// Determine if currently expired based on date
				if ( strtotime( $expiry_date ) < time() ) {
					$is_expired = true;
				}
			}

			// Lock course if expired
			if ( $is_expired ) {
				// Setting post status to 'cancel' or 'trash' locks out the student from accessing the course content
				$update_args = array(
					'ID'          => $enrollment->ID,
					'post_status' => 'cancel'
				);
				wp_update_post( $update_args );
				
				$msg = 'Locked expired course ' . $tutor_course_id . ' for user ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'expiries' );
				$results['logs'][] = $msg;
				$updated = true;
			}

			if ( $updated ) {
				$results['updated']++;
			} else {
				$results['skipped']++;
				$msg = 'Skipped: No expiry updates needed for user ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'expiries' );
				$results['logs'][] = $msg;
			}
		}

		return $results;
	}

	/**
	 * Retrieve a specific Tutor LMS enrollment post object.
	 *
	 * @param int $course_id
	 * @param int $user_id
	 * @return \WP_Post|false
	 */
	private function get_enrollment_record( $course_id, $user_id ) {
		$args = array(
			'post_type'      => 'tutor_enrolled',
			'post_status'    => array( 'completed', 'processing', 'publish' ), // Only look for active/valid enrollments
			'post_parent'    => $course_id,
			'author'         => $user_id,
			'posts_per_page' => 1,
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}
}
