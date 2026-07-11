<?php
/**
 * Logger & Verification Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Logger {

	/**
	 * Write a log message to a specific file type.
	 *
	 * @param string $message
	 * @param string $type
	 */
	public static function write_log( $message, $type = 'general' ) {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/edmingle-migration/logs';
		$log_file = $log_dir . '/' . sanitize_key( $type ) . '-' . date( 'Y-m-d' ) . '.log';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
		}

		$time = current_time( 'mysql' );
		$log_entry = "[$time] $message\n";

		error_log( $log_entry, 3, $log_file );
	}

	/**
	 * Get log summary statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		$stats = array(
			'imported' => 0,
			'updated'  => 0,
			'failed'   => 0,
			'skipped'  => 0,
		);

		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/edmingle-migration/logs';

		if ( ! is_dir( $log_dir ) ) {
			return $stats;
		}

		$files = glob( $log_dir . '/*.log' );

		foreach ( $files as $file ) {
			$handle = fopen( $file, 'r' );
			if ( $handle ) {
				while ( ( $line = fgets( $handle ) ) !== false ) {
					$line_lower = strtolower( $line );
					if ( strpos( $line_lower, 'created' ) !== false || strpos( $line_lower, 'successfully' ) !== false ) {
						$stats['imported']++;
					} elseif ( strpos( $line_lower, 'updated' ) !== false ) {
						$stats['updated']++;
					} elseif ( strpos( $line_lower, 'skipped' ) !== false ) {
						$stats['skipped']++;
					} elseif ( strpos( $line_lower, 'error' ) !== false || strpos( $line_lower, 'failed' ) !== false ) {
						$stats['failed']++;
					}
				}
				fclose( $handle );
			}
		}

		return $stats;
	}

	/**
	 * Get verification data (enrollments).
	 *
	 * @param int $limit
	 * @return array
	 */
	public function get_verification_data( $limit = 100 ) {
		$data = array();
		
		$args = array(
			'post_type'      => 'tutor_enrolled',
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$user   = get_userdata( $post->post_author );
				$course = get_post( $post->post_parent );
				
				$progress = get_user_meta( $post->post_author, '_tutor_course_progress_' . $post->post_parent, true );
				$progress = $progress ? $progress . '%' : '0%';
				
				$expiry = get_post_meta( $post->ID, '_tutor_enrolled_expiry_date', true );
				$expiry = $expiry ? date( 'Y-m-d', strtotime( $expiry ) ) : 'Lifetime';

				$data[] = array(
					'student'  => $user ? $user->display_name . ' (' . $user->user_email . ')' : 'Unknown',
					'course'   => $course ? $course->post_title : 'Unknown',
					'progress' => $progress,
					'expiry'   => $expiry,
					'status'   => ucfirst( $post->post_status ),
				);
			}
		}

		return $data;
	}

	/**
	 * Export verification data as CSV.
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$data = $this->get_verification_data( -1 ); // Get all for export

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=migration-verification-' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array( 'Student', 'Course', 'Progress', 'Expiry', 'Status' ) );

		foreach ( $data as $row ) {
			fputcsv( $output, array(
				$row['student'],
				$row['course'],
				$row['progress'],
				$row['expiry'],
				$row['status'],
			) );
		}

		fclose( $output );
		exit;
	}
}
