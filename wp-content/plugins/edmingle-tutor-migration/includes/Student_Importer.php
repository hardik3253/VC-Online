<?php
/**
 * Student Importer Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Student_Importer {

	/**
	 * API Client instance.
	 *
	 * @var ApiClient
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api = new ApiClient();
	}

	/**
	 * Import a batch of students.
	 *
	 * @param int $page The page number for API pagination.
	 * @param int $limit Number of records per page.
	 * @return array Results summary (success, error counts, messages).
	 */
	public function import_batch( $page = 1, $limit = 50 ) {
		$results = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'logs'    => array(),
		);

		// Fetch students from Edmingle API
		// Note: Using a standard endpoint format, adjust to actual Edmingle API
		$response = $this->api->get( '/api/v1/learners', array(
			'page'  => $page,
			'limit' => $limit,
		) );

		if ( is_wp_error( $response ) ) {
			\ETM\Includes\Logger::write_log( 'API Error fetching students: ' . $response->get_error_message(), 'students' );
			$results['errors']++;
			$results['logs'][] = 'API Error: ' . $response->get_error_message();
			return $results;
		}

		// Assume the API returns an array of student objects in a 'data' key, or directly as the array.
		$students = isset( $response['data'] ) ? $response['data'] : $response;

		if ( empty( $students ) || ! is_array( $students ) ) {
			\ETM\Includes\Logger::write_log( 'No students found on page ' . $page, 'students' );
			return $results;
		}

		foreach ( $students as $student ) {
			$email = isset( $student['email'] ) ? sanitize_email( $student['email'] ) : '';
			
			if ( empty( $email ) ) {
				$results['skipped']++;
				$msg = 'Skipped: Missing email for student ID: ' . ( isset( $student['id'] ) ? $student['id'] : 'Unknown' );
				\ETM\Includes\Logger::write_log( $msg, 'students' );
				$results['logs'][] = $msg;
				continue;
			}

			$user_id = email_exists( $email );

			$first_name = isset( $student['first_name'] ) ? sanitize_text_field( $student['first_name'] ) : '';
			$last_name  = isset( $student['last_name'] ) ? sanitize_text_field( $student['last_name'] ) : '';
			// Fallback if API provides a single 'name' field
			if ( empty( $first_name ) && isset( $student['name'] ) ) {
				$name_parts = explode( ' ', sanitize_text_field( $student['name'] ), 2 );
				$first_name = $name_parts[0];
				$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';
			}
			
			$phone = isset( $student['phone'] ) ? sanitize_text_field( $student['phone'] ) : '';

			$user_data = array(
				'user_email' => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'role'       => 'subscriber', // Tutor LMS uses the standard subscriber role for students
			);

			if ( ! $user_id ) {
				// Create new user
				$user_data['user_login'] = $email;
				$user_data['user_pass']  = wp_generate_password( 12, false ); // Random password

				$user_id = wp_insert_user( $user_data );

				if ( is_wp_error( $user_id ) ) {
					$results['errors']++;
					$msg = 'Error creating user ' . $email . ': ' . $user_id->get_error_message();
					\ETM\Includes\Logger::write_log( $msg, 'students' );
					$results['logs'][] = $msg;
				} else {
					$results['created']++;
					if ( ! empty( $phone ) ) {
						update_user_meta( $user_id, 'billing_phone', $phone ); // Standard meta, or custom if needed
					}
					$msg = 'Created user: ' . $email;
					\ETM\Includes\Logger::write_log( $msg, 'students' );
					$results['logs'][] = $msg;
				}

			} else {
				// Update existing user
				$user_data['ID'] = $user_id;
				
				// Optional: Depending on business rules, we might skip updates if we consider WP the source of truth
				// For now, we update the user.
				$update_result = wp_update_user( $user_data );

				if ( is_wp_error( $update_result ) ) {
					$results['errors']++;
					$msg = 'Error updating user ' . $email . ': ' . $update_result->get_error_message();
					\ETM\Includes\Logger::write_log( $msg, 'students' );
					$results['logs'][] = $msg;
				} else {
					$results['updated']++;
					if ( ! empty( $phone ) ) {
						update_user_meta( $user_id, 'billing_phone', $phone );
					}
					$msg = 'Updated user: ' . $email;
					\ETM\Includes\Logger::write_log( $msg, 'students' );
					$results['logs'][] = $msg;
				}
			}
		}

		return $results;
	}
}
