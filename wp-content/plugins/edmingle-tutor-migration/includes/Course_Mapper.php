<?php
/**
 * Course Mapper Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Course_Mapper {

	/**
	 * Retrieve saved course mapping.
	 *
	 * @return array array( 'edmingle_id' => 'tutor_id' )
	 */
	public function get_mapping() {
		return get_option( 'etm_course_mapping', array() );
	}

	/**
	 * Fetch Tutor LMS Courses
	 *
	 * @return array
	 */
	public function get_tutor_courses() {
		$args = array(
			'post_type'      => 'courses', // standard Tutor LMS course post type
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		$query = new \WP_Query( $args );
		$courses = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$courses[] = array(
					'id'    => $post->ID,
					'title' => $post->post_title,
				);
			}
		}

		return $courses;
	}

	/**
	 * Fetch Edmingle Courses via ApiClient.
	 * Mocked for now until actual endpoints are provided.
	 *
	 * @return array
	 */
	public function get_edmingle_courses() {
		// In a real scenario:
		// $api = new ApiClient();
		// $response = $api->get( '/api/v1/courses' );
		// return $response;
		
		// For the scope of this module, we return mock data
		return array(
			array( 'id' => 'e101', 'title' => 'Introduction to PHP' ),
			array( 'id' => 'e102', 'title' => 'Advanced WordPress Development' ),
			array( 'id' => 'e103', 'title' => 'Missing Course in Tutor' ),
		);
	}

	/**
	 * Auto match courses by title.
	 *
	 * @return array
	 */
	public function auto_match() {
		$tutor_courses = $this->get_tutor_courses();
		$edmingle_courses = $this->get_edmingle_courses();

		// Index tutor courses by lowercase title
		$tutor_by_title = array();
		foreach ( $tutor_courses as $tc ) {
			$tutor_by_title[ strtolower( trim( $tc['title'] ) ) ] = $tc;
		}

		$matched = array();
		$missing = array();

		foreach ( $edmingle_courses as $ec ) {
			$title_lower = strtolower( trim( $ec['title'] ) );
			
			if ( isset( $tutor_by_title[ $title_lower ] ) ) {
				$matched[] = array(
					'edmingle' => $ec,
					'tutor'    => $tutor_by_title[ $title_lower ],
				);
			} else {
				$missing[] = $ec;
			}
		}

		return array(
			'matched' => $matched,
			'missing' => $missing,
			'tutor_options' => $tutor_courses // Used for dropdowns
		);
	}

	/**
	 * Save mapping and prevent duplicates.
	 *
	 * @param array $mappings Associative array of edmingle_id => tutor_id
	 * @return bool|\WP_Error
	 */
	public function save_mapping( $mappings ) {
		if ( ! is_array( $mappings ) ) {
			return new \WP_Error( 'invalid_data', 'Mapping data must be an array.' );
		}

		$clean_mapping = array();
		$used_tutor_ids = array();

		foreach ( $mappings as $edmingle_id => $tutor_id ) {
			$edmingle_id = sanitize_text_field( $edmingle_id );
			$tutor_id    = absint( $tutor_id );

			if ( empty( $tutor_id ) ) {
				continue;
			}

			// Prevent mapping multiple Edmingle courses to the same Tutor course
			if ( in_array( $tutor_id, $used_tutor_ids, true ) ) {
				return new \WP_Error( 'duplicate_tutor_id', sprintf( 'Tutor LMS Course ID %d is mapped multiple times.', $tutor_id ) );
			}

			$clean_mapping[ $edmingle_id ] = $tutor_id;
			$used_tutor_ids[] = $tutor_id;
		}

		update_option( 'etm_course_mapping', $clean_mapping );
		return true;
	}
}
