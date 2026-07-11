<?php
/**
 * Batch Processor Logic
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class Batch_Processor {

	/**
	 * Run a specific migration step and page.
	 *
	 * @param string $step The migration step (e.g., 'students', 'enrollments').
	 * @param int    $page Current batch page.
	 * @param int    $batch_size Number of items to process in this batch.
	 * @return array Results payload.
	 */
	public function run_step( $step, $page, $batch_size = 50 ) {
		// Optimization for long-running scripts
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // Prevent timeouts on large batches if server allows
		}
		
		// Suspend cache additions to prevent memory leaks during heavy inserts
		wp_suspend_cache_addition( true );

		$results = array();

		try {
			switch ( $step ) {
				case 'students':
					$importer = new Student_Importer();
					$results = $importer->import_batch( $page, $batch_size );
					break;

				case 'enrollments':
					$importer = new Enrollment_Importer();
					$results = $importer->import_batch( $page, $batch_size );
					break;

				case 'progress':
					$importer = new Progress_Importer();
					$results = $importer->import_batch( $page, $batch_size );
					break;

				case 'expiry':
					$importer = new Expiry_Importer();
					$results = $importer->import_batch( $page, $batch_size );
					break;

				case 'completions':
					$importer = new Completion_Importer();
					$results = $importer->import_batch( $page, $batch_size );
					break;

				default:
					return new \WP_Error( 'invalid_step', __( 'Invalid migration step provided.', 'edmingle-tutor-migration' ) );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'batch_error', $e->getMessage() );
		}

		wp_suspend_cache_addition( false );

		return $results;
	}
}
