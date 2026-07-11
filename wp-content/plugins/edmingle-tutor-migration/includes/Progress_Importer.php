<?php
/**
 * Progress Importer Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Progress_Importer {

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
	 * Import a batch of course progress records.
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

		// Fetch progress from Edmingle API
		$response = $this->api->get( '/api/v1/progress', array(
			'page'  => $page,
			'limit' => $limit,
		) );

		if ( is_wp_error( $response ) ) {
			\ETM\Includes\Logger::write_log( 'API Error fetching progress: ' . $response->get_error_message(), 'progress' );
			$results['errors']++;
			$results['logs'][] = 'API Error: ' . $response->get_error_message();
			return $results;
		}

		$progress_records = isset( $response['data'] ) ? $response['data'] : $response;

		if ( empty( $progress_records ) || ! is_array( $progress_records ) ) {
			\ETM\Includes\Logger::write_log( 'No progress records found on page ' . $page, 'progress' );
			return $results;
		}

		$mapping = $this->mapper->get_mapping();

		foreach ( $progress_records as $record ) {
			$edmingle_course_id = isset( $record['course_id'] ) ? sanitize_text_field( $record['course_id'] ) : '';
			$email              = isset( $record['email'] ) ? sanitize_email( $record['email'] ) : '';
			
			// Extract progress data: either exact lesson array or a percentage
			$completed_lessons  = isset( $record['completed_lessons'] ) && is_array( $record['completed_lessons'] ) ? $record['completed_lessons'] : array();
			$progress_percent   = isset( $record['progress_percentage'] ) ? floatval( $record['progress_percentage'] ) : 0;

			// 1. Validate User
			$user = get_user_by( 'email', $email );
			if ( ! $user ) {
				$results['skipped']++;
				$msg = 'Skipped: User not found for email ' . $email;
				\ETM\Includes\Logger::write_log( $msg, 'progress' );
				$results['logs'][] = $msg;
				continue;
			}
			$user_id = $user->ID;

			// 2. Validate Course Mapping
			if ( ! isset( $mapping[ $edmingle_course_id ] ) ) {
				$results['skipped']++;
				$msg = 'Skipped: Edmingle Course ID ' . $edmingle_course_id . ' is not mapped to any Tutor LMS course.';
				\ETM\Includes\Logger::write_log( $msg, 'progress' );
				$results['logs'][] = $msg;
				continue;
			}
			$tutor_course_id = $mapping[ $edmingle_course_id ];

			// 3. Ensure Enrollment exists
			if ( function_exists( 'tutor_utils' ) && ! tutor_utils()->is_enrolled( $tutor_course_id, $user_id ) ) {
				$results['skipped']++;
				$msg = 'Skipped: User ' . $email . ' is not enrolled in Tutor Course ID ' . $tutor_course_id;
				\ETM\Includes\Logger::write_log( $msg, 'progress' );
				$results['logs'][] = $msg;
				continue;
			}

			// 4. Update Progress
			// Fetch all lessons for this course in Tutor LMS to calculate
			$course_lessons = $this->get_tutor_course_lessons( $tutor_course_id );
			$total_lessons  = count( $course_lessons );

			if ( $total_lessons === 0 ) {
				$results['skipped']++;
				$msg = 'Skipped: Tutor Course ID ' . $tutor_course_id . ' has no lessons configured.';
				\ETM\Includes\Logger::write_log( $msg, 'progress' );
				$results['logs'][] = $msg;
				continue;
			}

			$lessons_to_mark = array();

			if ( ! empty( $completed_lessons ) ) {
				// Option A: Specific lessons are provided.
				// For the sake of this migration, we assume they are provided in chronological order
				// or we just mark the first N lessons if we can't map exact lesson IDs between systems.
				// We'll mark N lessons corresponding to the count of completed lessons.
				$count_to_mark = min( count( $completed_lessons ), $total_lessons );
				$lessons_to_mark = array_slice( $course_lessons, 0, $count_to_mark );

			} elseif ( $progress_percent > 0 ) {
				// Option B: Calculate based on percentage
				$count_to_mark = round( ( $progress_percent / 100 ) * $total_lessons );
				$lessons_to_mark = array_slice( $course_lessons, 0, $count_to_mark );
			}

			if ( ! empty( $lessons_to_mark ) && function_exists( 'tutor_utils' ) ) {
				foreach ( $lessons_to_mark as $lesson_id ) {
					// Mark lesson complete in Tutor LMS
					tutor_utils()->mark_lesson_complete( $lesson_id, $user_id );
				}
				
				// Update overall course progress metadata
				tutor_utils()->update_course_progress( $tutor_course_id, $user_id );
				
				$results['updated']++;
				$msg = 'Updated progress for ' . $email . ' in course ' . $tutor_course_id . ' (' . count( $lessons_to_mark ) . ' lessons marked).';
				\ETM\Includes\Logger::write_log( $msg, 'progress' );
				$results['logs'][] = $msg;
			} else {
				$results['skipped']++;
				$msg = 'Skipped: No progress to mark for ' . $email . ' in course ' . $tutor_course_id;
				\ETM\Includes\Logger::write_log( $msg, 'progress' );
				$results['logs'][] = $msg;
			}
		}

		return $results;
	}

	/**
	 * Fetch all lesson IDs for a specific Tutor LMS course.
	 *
	 * @param int $course_id
	 * @return array Array of lesson post IDs.
	 */
	private function get_tutor_course_lessons( $course_id ) {
		// Tutor LMS organizes topics, and inside topics are lessons.
		$lessons = array();

		// Get Topics
		$topics = get_posts( array(
			'post_type'      => 'topics',
			'post_parent'    => $course_id,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );

		foreach ( $topics as $topic ) {
			// Get Lessons in Topic
			$topic_lessons = get_posts( array(
				'post_type'      => 'lesson',
				'post_parent'    => $topic->ID,
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			) );

			foreach ( $topic_lessons as $lesson ) {
				$lessons[] = $lesson->ID;
			}
		}

		return $lessons;
	}
}
