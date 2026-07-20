<?php
/**
 * Migration Dashboard View
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

use ETM\Includes\ETM_Database;

$types = array(
	'students'    => 'Students',
	'courses'     => 'Courses',
	'enrollments' => 'Enrollments & Progress',
);

?>
<div class="wrap etm-data-explorer">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle Migration Engine', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">
	
	<p><?php esc_html_e( 'Use this module to import the locally cached Edmingle data directly into WordPress and Tutor LMS. Data must be synchronized in the Data Explorer first.', 'edmingle-tutor-migration' ); ?></p>

	<div class="etm-cards-container">
		<?php foreach ( $types as $type => $label ) : 
			// Enrollments uses batches table logic for total records
			$db_table = ( $type === 'enrollments' ) ? 'edmingle_batches' : 'edmingle_' . $type;
			$total_records = ETM_Database::get_total_records( $db_table );
		?>
		<div class="etm-card" data-type="<?php echo esc_attr( $type ); ?>">
			<h2><?php echo esc_html( $label ); ?></h2>
			
			<div class="etm-card-stats">
				<p><strong>Available Records:</strong> <span class="etm-stat-total"><?php echo intval( $total_records ); ?></span></p>
				<div class="etm-sync-progress" style="display:none; margin-top: 10px;">
					<p style="margin-bottom: 5px;"><strong>Progress:</strong> <span class="etm-progress-text">Starting...</span></p>
					<p style="margin-bottom: 5px; font-size: 0.9em; color: #555;">
						Imported: <span class="etm-stat-imported">0</span> | 
						Skipped/Updated: <span class="etm-stat-skipped">0</span> |
						Errors: <span class="etm-stat-errors">0</span>
					</p>
				</div>
			</div>

			<div class="etm-card-actions">
				<button type="button" class="button button-primary etm-btn-migrate" data-action="etm_migrate_<?php echo esc_attr( $type ); ?>" data-resume="false" <?php echo $total_records === 0 ? 'disabled' : ''; ?>>
					<?php esc_html_e( 'Migrate All', 'edmingle-tutor-migration' ); ?>
				</button>
				<button type="button" class="button button-primary etm-btn-resume" data-action="etm_migrate_<?php echo esc_attr( $type ); ?>" data-resume="true" style="display:none;">
					<?php esc_html_e( 'Resume Migration', 'edmingle-tutor-migration' ); ?>
				</button>
				<span class="spinner"></span>
			</div>
			<div class="etm-card-notice" style="display:none;"></div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Migration Logs Table -->
	<h2><?php esc_html_e( 'Recent Migration Logs', 'edmingle-tutor-migration' ); ?></h2>
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Action', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Record ID', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Status', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Message', 'edmingle-tutor-migration' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php 
			global $wpdb;
			$log_table = $wpdb->prefix . 'edmingle_migration_logs';
			$logs = $wpdb->get_results( "SELECT * FROM $log_table ORDER BY created_at DESC LIMIT 15" );
			
			if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No migration logs found.', 'edmingle-tutor-migration' ); ?></td>
				</tr>
			<?php else : 
				foreach ( $logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( $log->created_at ); ?></td>
					<td><?php echo esc_html( $log->action_type ); ?></td>
					<td><?php echo esc_html( $log->record_id ); ?></td>
					<td>
						<?php 
						$status_color = $log->status === 'failed' ? 'color: red;' : 'color: green;';
						echo '<span style="' . $status_color . '">' . esc_html( ucfirst( $log->status ) ) . '</span>'; 
						?>
					</td>
					<td><?php echo esc_html( $log->message ); ?></td>
				</tr>
			<?php 
				endforeach;
			endif; ?>
		</tbody>
	</table>
</div>
