<?php
/**
 * Migration Engine AJAX Handlers
 *
 * @package Edmingle_Tutor_Migration\Admin
 */

namespace ETM\Admin;

use ETM\Includes\ETM_Database;
use ETM\Includes\Edmingle_API;

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

		if ( ! defined( 'ETM_MIGRATION_IN_PROGRESS' ) ) {
			define( 'ETM_MIGRATION_IN_PROGRESS', true );
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
			return;
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
			$reg_date = '';
			if ( ! empty( $data['date'] ) ) {
				$reg_time = ! empty( $data['time'] ) ? sanitize_text_field( $data['time'] ) : '00:00:00';
				$date_parts = explode( '/', $data['date'] );
				if ( count( $date_parts ) === 3 ) {
					$reg_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0] . ' ' . $reg_time;
				} else {
					$reg_date = $data['date'] . ' ' . $reg_time;
				}
			} elseif ( ! empty( $data['created_at'] ) ) {
				$reg_date = sanitize_text_field( $data['created_at'] );
			}

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
				$parsed_timestamp = strtotime( $reg_date );
				if ( $parsed_timestamp > 0 ) {
					$user_data['user_registered'] = date( 'Y-m-d H:i:s', $parsed_timestamp );
				}
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

		if ( ! defined( 'ETM_MIGRATION_IN_PROGRESS' ) ) {
			define( 'ETM_MIGRATION_IN_PROGRESS', true );
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
			return;
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

		if ( ! defined( 'ETM_MIGRATION_IN_PROGRESS' ) ) {
			define( 'ETM_MIGRATION_IN_PROGRESS', true );
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
			$state['total_records'] = ETM_Database::get_total_records( 'edmingle_students' ); // Loop through students to get enrollments
			$state['status'] = 'running';
		}

		if ( $state['total_records'] === 0 ) {
			wp_send_json_error( 'No local student records found. Please sync students first.' );
		}

		$limit = 10; // Use small batch size as we make external API requests per student
		global $wpdb;
		$table = $wpdb->prefix . 'edmingle_students';
		
		$records = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
			$limit, $state['offset']
		) );

		$stats = array( 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0 );

		if ( empty( $records ) ) {
			$state['status'] = 'completed';
			$this->send_progress( $state_key, $state, $stats );
			return;
		}

		foreach ( $records as $record ) {
			$edmingle_student_id = $record->edmingle_id;
			
			// Find WP User by edmingle_id
			$wp_users = get_users( array(
				'meta_key'   => '_etm_edmingle_id',
				'meta_value' => $edmingle_student_id,
				'number'     => 1,
				'fields'     => 'ID'
			) );

			if ( empty( $wp_users ) ) {
				$stats['errors']++;
				ETM_Database::log_migration( 'enrollment_failed', $edmingle_student_id, "WP User not found locally for Edmingle Student ID: {$edmingle_student_id}", 'failed' );
				continue;
			}

			$wp_user_id = $wp_users[0];
			$user_obj = get_userdata( $wp_user_id );
			$reg_date = $user_obj ? $user_obj->user_registered : '';

			// Fetch enrollments for this student
			$path = 'admin/student/enrollcourses/' . rawurlencode( $edmingle_student_id );
			$response = Edmingle_API::request( $path, 'GET', array(
				'include_archived_batches' => 0,
				'include_lastview_info'    => 0,
			) );

			if ( is_wp_error( $response ) ) {
				$stats['errors']++;
				ETM_Database::log_migration( 'enrollment_failed', $edmingle_student_id, "API Error fetching enrolled courses: " . $response->get_error_message(), 'failed' );
				continue;
			}

			$data = $response['data'];
			$batches = isset( $data['batches'] ) && is_array( $data['batches'] ) ? $data['batches'] : array();

			if ( empty( $batches ) ) {
				$stats['skipped']++;
				continue;
			}

			foreach ( $batches as $batch ) {
				$bundle_id = isset( $batch['bundle_id'] ) ? $batch['bundle_id'] : '';
				$master_batch_id = isset( $batch['master_batch_id'] ) ? $batch['master_batch_id'] : '';
				$course_name = isset( $batch['master_batch_name'] ) ? $batch['master_batch_name'] : '';
				if ( empty( $course_name ) && isset( $batch['actual_master_batch_name'] ) ) {
					$course_name = $batch['actual_master_batch_name'];
				}

				// Find local Tutor LMS Course ID
				$tutor_course_id = 0;

				// 1. Try to get mapping by bundle_id or master_batch_id
				if ( ! empty( $bundle_id ) ) {
					$tutor_course_id = ETM_Database::get_course_mapping( $bundle_id );
				}
				if ( ! $tutor_course_id && ! empty( $master_batch_id ) ) {
					$tutor_course_id = ETM_Database::get_course_mapping( $master_batch_id );
				}

				// 2. Try to match by title
				if ( ! $tutor_course_id && ! empty( $course_name ) ) {
					$course_post = get_page_by_title( $course_name, OBJECT, 'courses' );
					if ( $course_post ) {
						$tutor_course_id = $course_post->ID;
						// Save mapping for future reference
						if ( ! empty( $bundle_id ) ) {
							ETM_Database::save_course_mapping( $bundle_id, $tutor_course_id );
						}
					}
				}

				if ( ! $tutor_course_id ) {
					$stats['errors']++;
					ETM_Database::log_migration( 'enrollment_failed', $edmingle_student_id, "Tutor Course not found or mapped for Course: {$course_name}", 'failed' );
					continue;
				}

				if ( ! function_exists( 'tutor_utils' ) ) {
					$stats['errors']++;
					ETM_Database::log_migration( 'enrollment_failed', $edmingle_student_id, "Tutor LMS plugin is not active.", 'failed' );
					break;
				}

				// Enroll student in Tutor LMS
				$is_enrolled = tutor_utils()->is_enrolled( $tutor_course_id, $wp_user_id );
				if ( ! $is_enrolled ) {
					$enroll_post = array(
						'post_type'   => 'tutor_enrolled',
						'post_status' => 'completed',
						'post_author' => $wp_user_id,
						'post_parent' => $tutor_course_id,
						'post_title'  => 'Enrollment',
						'post_date'   => current_time('mysql'),
					);

					$enroll_id = wp_insert_post( $enroll_post );

					if ( $enroll_id ) {
						update_post_meta( $enroll_id, '_tutor_user_id', $wp_user_id );
						update_post_meta( $enroll_id, '_tutor_course_id', $tutor_course_id );
						update_post_meta( $enroll_id, 'course_id', $tutor_course_id );
						update_post_meta( $enroll_id, 'user_id', $wp_user_id );
						update_post_meta( $enroll_id, 'enrol_status', 'completed');
						update_post_meta( $enroll_id, '_is_manual_enrollment', 'no' );
						update_post_meta( $enroll_id, 'enrollment_date', current_time('mysql') );

						wp_update_post( array( 'ID' => $enroll_id, 'post_status' => 'completed' ) );
						
						$stats['imported']++;
						ETM_Database::log_migration( 'enrollment_created', $edmingle_student_id, "Enrolled user {$wp_user_id} in course {$tutor_course_id}" );
					} else {
						$stats['errors']++;
						continue;
					}
				} else {
					$stats['skipped']++;
				}

				// Expiry & Access
				// Check 1-Year subscription expiration from student registration date
				$is_expired = false;
				if ( ! empty( $reg_date ) ) {
					$reg_time = strtotime( $reg_date );
					if ( $reg_time > 0 ) {
						$expiry_time = strtotime( '+1 year', $reg_time );
						if ( $expiry_time < time() ) {
							$is_expired = true;
						}
					}
				}

				update_user_meta( $wp_user_id, 'tutor_course_access_' . $tutor_course_id, $is_expired ? 'no' : 'yes' );
				$exp_date = isset( $batch['enrollment_expiration_date'] ) ? $batch['enrollment_expiration_date'] : '';
				if ( ! empty( $exp_date ) && $exp_date > 0 ) {
					update_user_meta( $wp_user_id, 'tutor_course_expiry_' . $tutor_course_id, date( 'Y-m-d H:i:s', $exp_date ) );
				}

				// Import Purchase / Order Records for Paid Courses
				$order_id = ! empty( $batch['order_id'] ) ? $batch['order_id'] : ( ! empty( $batch['id'] ) ? $batch['id'] : 0 );
				$order_amount = 0;
				if ( ! empty( $batch['amount'] ) ) {
					$order_amount = floatval( $batch['amount'] );
				} elseif ( ! empty( $batch['order_amount'] ) ) {
					$order_amount = floatval( $batch['order_amount'] );
				}

				if ( $order_amount > 0 || ! empty( $order_id ) ) {
					$existing_order_id = $wpdb->get_var( $wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}tutor_orders WHERE transaction_id = %s OR (user_id = %d AND total_price = %f AND note LIKE %s)",
						$order_id, $wp_user_id, $order_amount, '%' . $course_name . '%'
					) );

					if ( ! $existing_order_id ) {
						$order_date = current_time('mysql');
						if ( ! empty( $batch['purchase_date'] ) ) {
							$order_date = date( 'Y-m-d H:i:s', is_numeric( $batch['purchase_date'] ) ? $batch['purchase_date'] : strtotime( $batch['purchase_date'] ) );
						} elseif ( ! empty( $batch['enroll_date'] ) ) {
							$order_date = date( 'Y-m-d H:i:s', is_numeric( $batch['enroll_date'] ) ? $batch['enroll_date'] : strtotime( $batch['enroll_date'] ) );
						}

						$wpdb->insert(
							$wpdb->prefix . 'tutor_orders',
							array(
								'parent_id'      => 0,
								'transaction_id' => $order_id,
								'user_id'        => $wp_user_id,
								'order_type'     => 'single_order',
								'order_status'   => 'completed',
								'payment_status' => 'paid',
								'subtotal_price' => $order_amount,
								'pre_tax_price'  => $order_amount,
								'total_price'    => $order_amount,
								'net_payment'    => $order_amount,
								'payment_method' => 'migrated',
								'note'           => 'Migrated Paid Course: ' . $course_name,
								'created_at_gmt' => get_gmt_from_date( $order_date ),
								'created_by'     => $wp_user_id,
								'updated_at_gmt' => get_gmt_from_date( $order_date ),
								'updated_by'     => $wp_user_id,
							)
						);

						$new_order_id = $wpdb->insert_id;

						if ( $new_order_id ) {
							$wpdb->insert(
								$wpdb->prefix . 'tutor_order_items',
								array(
									'order_id'      => $new_order_id,
									'item_id'       => $tutor_course_id,
									'regular_price' => $order_amount,
								)
							);
							
							if ( isset( $enroll_id ) ) {
								update_post_meta( $enroll_id, '_enrolled_by_order_id', $new_order_id );
								update_post_meta( $enroll_id, 'order_amount', $order_amount );
							}
						}
					}
				}

				// Import Progress
				$progress = 0;
				if ( isset( $batch['batch_progress'] ) ) {
					$progress = intval( $batch['batch_progress'] );
				} elseif ( isset( $batch['stats']['batch_progress'] ) ) {
					$progress = intval( $batch['stats']['batch_progress'] );
				}

				if ( $progress > 0 ) {
					update_user_meta( $wp_user_id, '_tutor_course_progress_' . $tutor_course_id, $progress );
					update_user_meta( $wp_user_id, 'tutor_course_progress_' . $tutor_course_id, $progress ); // Double update for compatibility
					
					if ( $progress >= 100 ) {
						if ( class_exists( '\Tutor\Models\CourseModel' ) ) {
							\Tutor\Models\CourseModel::mark_course_as_completed( $tutor_course_id, $wp_user_id );
						}
						ETM_Database::log_migration( 'progress_completed', $edmingle_student_id, "Marked course {$tutor_course_id} complete for user {$wp_user_id}." );
					}
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
