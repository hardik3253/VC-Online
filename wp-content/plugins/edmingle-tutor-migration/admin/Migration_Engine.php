<?php
/**
 * Migration Engine AJAX Handlers
 *
 * @package Edmingle_Tutor_Migration\Admin
 */

namespace ETM\Admin;

use ETM\Includes\ETM_Database;

class Migration_Engine {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'wp_ajax_etm_migrate_students', array( $this, 'ajax_migrate_students' ) );
		add_action( 'wp_ajax_etm_migrate_courses', array( $this, 'ajax_migrate_courses' ) );
		add_action( 'wp_ajax_etm_migrate_enrollments', array( $this, 'ajax_migrate_enrollments' ) );
	}

	/**
	 * Helper to send structured response.
	 */
	private function send_progress( $state_key, $state, $stats ) {
		update_option( $state_key, $state );
		wp_send_json_success( array(
			'state' => $state,
			'stats' => $stats,
		) );
	}

	/**
	 * Migrate Students AJAX
	 */
	public function ajax_migrate_students() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$resume = isset( $_POST['resume'] ) && $_POST['resume'] === 'true';
		$state_key = 'etm_migrate_state_students';
		
		$state = get_option( $state_key, array(
			'offset'          => 0,
			'records_migrated'=> 0,
			'total_records'   => 0,
			'status'          => 'idle',
		) );

		if ( ! $resume ) {
			$state['offset'] = 0;
			$state['records_migrated'] = 0;
			$state['total_records'] = ETM_Database::get_total_records( 'edmingle_students' );
			$state['status'] = 'running';
		}

		if ( $state['total_records'] === 0 ) {
			wp_send_json_error( 'No local records found. Please sync students first.' );
		}

		$limit = 50;
		global $wpdb;
		$table = $wpdb->prefix . 'edmingle_students';
		
		// Fetch batch
		$records = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
			$limit, $state['offset']
		) );

		$stats = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => 0
		);

		if ( empty( $records ) ) {
			$state['status'] = 'completed';
			$this->send_progress( $state_key, $state, $stats );
		}

		foreach ( $records as $record ) {
			$data = json_decode( $record->json_data, true );
			if ( ! $data ) {
				$stats['errors']++;
				continue;
			}

			// Edmingle specific student data mapping
			$email      = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
			$first_name = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : ( isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : 'Student' );
			$last_name  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
			$phone      = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : ( isset( $data['mobile'] ) ? sanitize_text_field( $data['mobile'] ) : '' );
			$status     = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active';
			$reg_date   = isset( $data['created_at'] ) ? sanitize_text_field( $data['created_at'] ) : ''; // or registered_at

			if ( empty( $email ) ) {
				$email = $record->edmingle_id . '@migrated.edmingle.local';
			}

			// Check if WP user exists
			$user = get_user_by( 'email', $email );
			
			$user_data = array(
				'user_email' => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			);
			
			if ( ! empty( $reg_date ) ) {
				$user_data['user_registered'] = gmdate( 'Y-m-d H:i:s', strtotime( $reg_date ) );
			}

			if ( $user ) {
				// User exists. Update user details
				$user_data['ID'] = $user->ID;
				wp_update_user( $user_data );

				update_user_meta( $user->ID, '_etm_edmingle_id', $record->edmingle_id );
				update_user_meta( $user->ID, 'billing_phone', $phone );
				update_user_meta( $user->ID, '_etm_student_status', $status );

				$stats['updated']++;
				ETM_Database::log_migration( 'student_updated', $record->edmingle_id, "Updated user {$email}" );
			} else {
				$password = wp_generate_password( 12, false );
				$user_data['user_login'] = $email;
				$user_data['user_pass']  = $password;
				$user_data['role']       = 'subscriber';
				
				$user_id = wp_insert_user( $user_data );

				if ( is_wp_error( $user_id ) ) {
					$stats['errors']++;
					ETM_Database::log_migration( 'student_failed', $record->edmingle_id, "Failed to create user {$email}: " . $user_id->get_error_message(), 'failed' );
				} else {
					update_user_meta( $user_id, '_etm_edmingle_id', $record->edmingle_id );
					update_user_meta( $user_id, 'billing_phone', $phone );
					update_user_meta( $user_id, '_etm_student_status', $status );
					
					$stats['imported']++;
					ETM_Database::log_migration( 'student_created', $record->edmingle_id, "Created user {$email}" );
				}
			}
		}

		$state['offset'] += count( $records );
		$state['records_migrated'] += count( $records );
		
		if ( count( $records ) < $limit || $state['records_migrated'] >= $state['total_records'] ) {
			$state['status'] = 'completed';
		} else {
			$state['status'] = 'running';
		}

		$this->send_progress( $state_key, $state, $stats );
	}

	/**
	 * Migrate Courses AJAX
	 */
	public function ajax_migrate_courses() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$resume = isset( $_POST['resume'] ) && $_POST['resume'] === 'true';
		$state_key = 'etm_migrate_state_courses';
		
		$state = get_option( $state_key, array(
			'offset'          => 0,
			'records_migrated'=> 0,
			'total_records'   => 0,
			'status'          => 'idle',
		) );

		if ( ! $resume ) {
			$state['offset'] = 0;
			$state['records_migrated'] = 0;
			$state['total_records'] = ETM_Database::get_total_records( 'edmingle_courses' );
			$state['status'] = 'running';
		}

		if ( $state['total_records'] === 0 ) {
			wp_send_json_error( 'No local course records found. Please sync courses first.' );
		}

		$limit = 50;
		global $wpdb;
		$table = $wpdb->prefix . 'edmingle_courses';
		
		$records = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
			$limit, $state['offset']
		) );

		$stats = array( 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0 );

		if ( empty( $records ) ) {
			$state['status'] = 'completed';
			$this->send_progress( $state_key, $state, $stats );
		}

		foreach ( $records as $record ) {
			$data = json_decode( $record->json_data, true );
			if ( ! $data ) {
				$stats['errors']++;
				continue;
			}

			$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : ( isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : 'Course ' . $record->edmingle_id );
			$description = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';

			// Check if already mapped
			$mapped_tutor_id = ETM_Database::get_course_mapping( $record->edmingle_id );
			
			if ( $mapped_tutor_id && get_post( $mapped_tutor_id ) ) {
				// Already mapped and post exists, skip or update? Just skip for now to preserve manual edits.
				$stats['skipped']++;
				continue;
			}

			// Try to find by title auto-match
			$existing_course = get_page_by_title( $title, OBJECT, 'courses' );
			
			if ( $existing_course ) {
				ETM_Database::save_course_mapping( $record->edmingle_id, $existing_course->ID );
				update_post_meta( $existing_course->ID, '_etm_edmingle_course_id', $record->edmingle_id );
				$stats['updated']++; // Auto-mapped
				ETM_Database::log_migration( 'course_mapped', $record->edmingle_id, "Auto-mapped course to Tutor ID: {$existing_course->ID}" );
			} else {
				// Create new Course
				$post_data = array(
					'post_title'   => $title,
					'post_content' => $description,
					'post_status'  => 'publish',
					'post_type'    => 'courses',
					'post_author'  => get_current_user_id()
				);
				
				$course_id = wp_insert_post( $post_data );
				
				if ( is_wp_error( $course_id ) ) {
					$stats['errors']++;
					ETM_Database::log_migration( 'course_failed', $record->edmingle_id, "Failed to create course: " . $course_id->get_error_message(), 'failed' );
				} else {
					ETM_Database::save_course_mapping( $record->edmingle_id, $course_id );
					update_post_meta( $course_id, '_etm_edmingle_course_id', $record->edmingle_id );
					$stats['imported']++;
					ETM_Database::log_migration( 'course_created', $record->edmingle_id, "Created new Tutor Course ID: {$course_id}" );
				}
			}
		}

		$state['offset'] += count( $records );
		$state['records_migrated'] += count( $records );
		
		if ( count( $records ) < $limit || $state['records_migrated'] >= $state['total_records'] ) {
			$state['status'] = 'completed';
		} else {
			$state['status'] = 'running';
		}

		$this->send_progress( $state_key, $state, $stats );
	}

	/**
	 * Migrate Enrollments AJAX
	 */
	public function ajax_migrate_enrollments() {
		check_ajax_referer( 'etm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$resume = isset( $_POST['resume'] ) && $_POST['resume'] === 'true';
		$state_key = 'etm_migrate_state_enrollments';
		
		$state = get_option( $state_key, array(
			'offset'          => 0,
			'records_migrated'=> 0,
			'total_records'   => 0,
			'status'          => 'idle',
		) );

		if ( ! $resume ) {
			$state['offset'] = 0;
			$state['records_migrated'] = 0;
			$state['total_records'] = ETM_Database::get_total_records( 'edmingle_batches' ); // Assuming batches represent enrollments or students have courses attached
			$state['status'] = 'running';
		}

		if ( $state['total_records'] === 0 ) {
			// Some APIs return enrollments under students. If batches is empty, try students.
			wp_send_json_error( 'No batch records found to process enrollments.' );
		}

		$limit = 50;
		global $wpdb;
		$table = $wpdb->prefix . 'edmingle_batches';
		
		$records = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
			$limit, $state['offset']
		) );

		$stats = array( 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0 );

		if ( empty( $records ) ) {
			$state['status'] = 'completed';
			$this->send_progress( $state_key, $state, $stats );
		}

		foreach ( $records as $record ) {
			$data = json_decode( $record->json_data, true );
			if ( ! $data ) {
				$stats['errors']++;
				continue;
			}

			// Example parsing. Actual API structure might differ.
			// Batch usually has 'course_id' and a list of 'student_ids' or 'enrollments'
			$edmingle_course_id = isset( $data['course_id'] ) ? $data['course_id'] : '';
			$students = isset( $data['students'] ) ? $data['students'] : array();

			if ( empty( $edmingle_course_id ) || empty( $students ) ) {
				$stats['skipped']++;
				continue;
			}

			$tutor_course_id = ETM_Database::get_course_mapping( $edmingle_course_id );
			if ( ! $tutor_course_id ) {
				$stats['errors']++;
				ETM_Database::log_migration( 'enrollment_failed', $record->edmingle_id, "Course not mapped for Edmingle ID: {$edmingle_course_id}", 'failed' );
				continue;
			}

			foreach ( $students as $student ) {
				$edmingle_student_id = isset( $student['id'] ) ? $student['id'] : ( isset( $student['user_id'] ) ? $student['user_id'] : '' );
				
				if ( empty( $edmingle_student_id ) ) continue;

				// Find WP User by edmingle_id
				$wp_users = get_users( array(
					'meta_key'   => '_etm_edmingle_id',
					'meta_value' => $edmingle_student_id,
					'number'     => 1,
					'fields'     => 'ID'
				) );

				if ( empty( $wp_users ) ) {
					$stats['errors']++;
					ETM_Database::log_migration( 'enrollment_failed', $record->edmingle_id, "WP User not found for Edmingle Student ID: {$edmingle_student_id}", 'failed' );
					continue;
				}

				$wp_user_id = $wp_users[0];

				if ( function_exists( 'tutor_utils' ) ) {
					$is_enrolled = tutor_utils()->is_enrolled( $tutor_course_id, $wp_user_id );
					if ( ! $is_enrolled ) {
						tutor_utils()->do_enroll( $tutor_course_id, 0, $wp_user_id );
						
						// Import Course Access (Expiry)
						$access_until = isset( $student['access_until'] ) ? $student['access_until'] : '';
						if ( ! empty( $access_until ) ) {
							update_user_meta( $wp_user_id, '_tutor_course_expired_date_' . $tutor_course_id, strtotime( $access_until ) );
						}

						// Import Progress
						$progress = isset( $student['progress'] ) ? intval( $student['progress'] ) : 0;
						if ( $progress > 0 ) {
							update_user_meta( $wp_user_id, '_tutor_course_progress_' . $tutor_course_id, $progress );
							
							if ( $progress >= 100 ) {
								tutor_utils()->mark_course_complete( $tutor_course_id, $wp_user_id );
								ETM_Database::log_migration( 'progress_completed', $edmingle_student_id, "Marked course {$tutor_course_id} complete." );
							}
						}

						$stats['imported']++;
						ETM_Database::log_migration( 'enrollment_created', $edmingle_student_id, "Enrolled user {$wp_user_id} in course {$tutor_course_id}" );
					} else {
						$stats['skipped']++; // Already enrolled
					}
				} else {
					$stats['errors']++;
					ETM_Database::log_migration( 'enrollment_failed', $record->edmingle_id, "Tutor LMS plugin is not active.", 'failed' );
				}
			}
		}

		$state['offset'] += count( $records );
		$state['records_migrated'] += count( $records );
		
		if ( count( $records ) < $limit || $state['records_migrated'] >= $state['total_records'] ) {
			$state['status'] = 'completed';
		} else {
			$state['status'] = 'running';
		}

		$this->send_progress( $state_key, $state, $stats );
	}
}
