<?php
/**
 * Logs & Verification View
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$logger = new \ETM\Includes\Logger();
$stats  = $logger->get_stats();
$data   = $logger->get_verification_data( 50 ); // Show last 50 on screen
?>
<div class="wrap etm-logs">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Migration Logs & Verification', 'edmingle-tutor-migration' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=etm_export_logs' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Export log CSV', 'edmingle-tutor-migration' ); ?></a>
	<hr class="wp-header-end">

	<!-- Summary Cards -->
	<div class="etm-grid-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
		<div class="postbox" style="margin-bottom:0;">
			<div class="inside" style="text-align:center;">
				<h3 style="margin: 0; color: #46b450; font-size: 2em;"><?php echo esc_html( $stats['imported'] ); ?></h3>
				<p style="margin: 5px 0 0; text-transform: uppercase; font-weight:bold;"><?php esc_html_e( 'Imported', 'edmingle-tutor-migration' ); ?></p>
			</div>
		</div>
		<div class="postbox" style="margin-bottom:0;">
			<div class="inside" style="text-align:center;">
				<h3 style="margin: 0; color: #2271b1; font-size: 2em;"><?php echo esc_html( $stats['updated'] ); ?></h3>
				<p style="margin: 5px 0 0; text-transform: uppercase; font-weight:bold;"><?php esc_html_e( 'Updated', 'edmingle-tutor-migration' ); ?></p>
			</div>
		</div>
		<div class="postbox" style="margin-bottom:0;">
			<div class="inside" style="text-align:center;">
				<h3 style="margin: 0; color: #f56e28; font-size: 2em;"><?php echo esc_html( $stats['skipped'] ); ?></h3>
				<p style="margin: 5px 0 0; text-transform: uppercase; font-weight:bold;"><?php esc_html_e( 'Skipped', 'edmingle-tutor-migration' ); ?></p>
			</div>
		</div>
		<div class="postbox" style="margin-bottom:0;">
			<div class="inside" style="text-align:center;">
				<h3 style="margin: 0; color: #d63638; font-size: 2em;"><?php echo esc_html( $stats['failed'] ); ?></h3>
				<p style="margin: 5px 0 0; text-transform: uppercase; font-weight:bold;"><?php esc_html_e( 'Failed', 'edmingle-tutor-migration' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Verification Table -->
	<h2 style="margin-top: 40px;"><?php esc_html_e( 'Recent Verification Data', 'edmingle-tutor-migration' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Student', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Course', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Progress', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Expiry', 'edmingle-tutor-migration' ); ?></th>
				<th><?php esc_html_e( 'Status', 'edmingle-tutor-migration' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $data ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No migration data found yet.', 'edmingle-tutor-migration' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $data as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['student'] ); ?></td>
						<td><strong><?php echo esc_html( $row['course'] ); ?></strong></td>
						<td><?php echo esc_html( $row['progress'] ); ?></td>
						<td><?php echo esc_html( $row['expiry'] ); ?></td>
						<td>
							<?php if ( 'Cancel' === $row['status'] ) : ?>
								<span style="color: #d63638; font-weight: bold;"><?php echo esc_html( $row['status'] ); ?> (Expired)</span>
							<?php elseif ( 'Completed' === $row['status'] ) : ?>
								<span style="color: #46b450; font-weight: bold;"><?php echo esc_html( $row['status'] ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $row['status'] ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'Showing the 50 most recent enrollment records. Export to CSV to view all records.', 'edmingle-tutor-migration' ); ?></p>
</div>
