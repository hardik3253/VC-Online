<?php
/**
 * Data Explorer View
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

use ETM\Includes\ETM_Database;

$types = array(
	'students'      => 'Students',
	'courses'       => 'Courses',
	'batches'       => 'Batches',
	'curriculum'    => 'Curriculum',
	'materials'     => 'Materials',
	'certificates'  => 'Certificates',
	'notifications' => 'Notifications'
);

?>
<div class="wrap etm-data-explorer">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle Data Explorer', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<div class="etm-cards-container">
		<?php foreach ( $types as $type => $label ) : 
			$total_records = ETM_Database::get_total_records( 'edmingle_' . $type );
			$last_sync     = ETM_Database::get_last_sync( 'edmingle_' . $type );
		?>
		<div class="etm-card" data-type="<?php echo esc_attr( $type ); ?>">
			<h2><?php echo esc_html( $label ); ?></h2>
			
			<div class="etm-card-stats">
				<p><strong>Total Records:</strong> <span class="etm-stat-total"><?php echo intval( $total_records ); ?></span></p>
				<p><strong>Last Sync:</strong> <span class="etm-stat-sync"><?php echo esc_html( $last_sync ); ?></span></p>
			</div>

			<div class="etm-card-actions">
				<button type="button" class="button button-primary etm-btn-fetch" data-action="edmingle_fetch_<?php echo esc_attr( $type ); ?>">
					<?php esc_html_e( 'Fetch', 'edmingle-tutor-migration' ); ?>
				</button>
				<button type="button" class="button button-secondary etm-btn-view" data-type="<?php echo esc_attr( $type ); ?>" <?php echo $total_records === 0 ? 'disabled' : ''; ?>>
					<?php esc_html_e( 'View JSON', 'edmingle-tutor-migration' ); ?>
				</button>
				<span class="spinner"></span>
			</div>
			<div class="etm-card-notice" style="display:none;"></div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Logs Table -->
	<h2><?php esc_html_e( 'Recent API Logs', 'edmingle-tutor-migration' ); ?></h2>
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'URL', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Status Code', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Execution Time (ms)', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Rows Saved', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Error Message', 'edmingle-tutor-migration' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php 
			$logs = ETM_Database::get_recent_logs( 10 );
			if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No logs found.', 'edmingle-tutor-migration' ); ?></td>
				</tr>
			<?php else : 
				foreach ( $logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( $log->created_at ); ?></td>
					<td><?php echo esc_html( $log->url ); ?></td>
					<td>
						<?php 
						$status_color = $log->status_code >= 400 ? 'color: red;' : 'color: green;';
						echo '<span style="' . $status_color . '">' . intval( $log->status_code ) . '</span>'; 
						?>
					</td>
					<td><?php echo intval( $log->execution_time ); ?></td>
					<td><?php echo intval( $log->rows_saved ); ?></td>
					<td><?php echo esc_html( $log->error_message ); ?></td>
				</tr>
			<?php 
				endforeach;
			endif; ?>
		</tbody>
	</table>
</div>

<!-- JSON Modal Template -->
<div id="etm-json-modal" class="etm-modal" style="display:none;">
	<div class="etm-modal-content">
		<span class="etm-close-modal">&times;</span>
		<h2><?php esc_html_e( 'JSON Viewer', 'edmingle-tutor-migration' ); ?> <span id="etm-modal-type-title"></span></h2>
		<pre id="etm-json-viewer"></pre>
	</div>
</div>
