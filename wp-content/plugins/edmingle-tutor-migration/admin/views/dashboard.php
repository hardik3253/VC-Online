<?php
/**
 * Admin Dashboard View
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="wrap etm-dashboard">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle to Tutor LMS Migration', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<div class="etm-actions-bar" style="margin: 20px 0; display: flex; align-items: center; gap: 15px;">
		<button type="button" class="button button-secondary"><?php esc_html_e( 'Verify Data', 'edmingle-tutor-migration' ); ?></button>
		<button type="button" class="button button-primary" id="etm-run-migration"><?php esc_html_e( 'Run Complete Migration', 'edmingle-tutor-migration' ); ?></button>
		<button type="button" class="button button-secondary" id="etm-resume-migration" style="display:none;"><?php esc_html_e( 'Resume Migration', 'edmingle-tutor-migration' ); ?></button>
		
		<div class="etm-batch-config">
			<label for="etm-batch-size"><strong><?php esc_html_e( 'Batch Size:', 'edmingle-tutor-migration' ); ?></strong></label>
			<input type="number" id="etm-batch-size" value="50" min="1" max="500" style="width: 70px;">
		</div>
	</div>

	<div id="etm-migration-progress-container" style="display:none; margin-bottom: 20px;">
		<p id="etm-migration-status-text" style="font-weight: bold; margin-bottom: 5px;"><?php esc_html_e( 'Preparing migration...', 'edmingle-tutor-migration' ); ?></p>
		<progress id="etm-migration-progress-bar" value="0" max="100" style="width: 100%; height: 25px;"></progress>
		<div id="etm-migration-error" class="notice notice-error" style="display:none; margin-top:10px;"><p></p></div>
	</div>

	<div class="etm-grid-container">
		
		<!-- Card: API Connection -->
		<div class="postbox etm-card">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'API Connection', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<p id="etm-connection-status"><?php esc_html_e( 'Status: Not Connected', 'edmingle-tutor-migration' ); ?></p>
				<button type="button" class="button button-secondary" id="etm-test-connection"><?php esc_html_e( 'Test Connection', 'edmingle-tutor-migration' ); ?></button>
				<span class="spinner" id="etm-connection-spinner" style="float:none; margin-top:0;"></span>
			</div>
		</div>

		<!-- Card: Students -->
		<div class="postbox etm-card">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Students', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Migrated: 0 / 0', 'edmingle-tutor-migration' ); ?></p>
				<button type="button" class="button button-secondary"><?php esc_html_e( 'Sync Students', 'edmingle-tutor-migration' ); ?></button>
			</div>
		</div>

		<!-- Card: Enrollments -->
		<div class="postbox etm-card">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Enrollments', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Migrated: 0 / 0', 'edmingle-tutor-migration' ); ?></p>
				<button type="button" class="button button-secondary"><?php esc_html_e( 'Sync Enrollments', 'edmingle-tutor-migration' ); ?></button>
			</div>
		</div>

		<!-- Card: Progress -->
		<div class="postbox etm-card">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Progress', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Migrated: 0 / 0', 'edmingle-tutor-migration' ); ?></p>
				<button type="button" class="button button-secondary"><?php esc_html_e( 'Sync Progress', 'edmingle-tutor-migration' ); ?></button>
			</div>
		</div>

		<!-- Card: Expiry -->
		<div class="postbox etm-card">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Course Expiry', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Migrated: 0 / 0', 'edmingle-tutor-migration' ); ?></p>
				<button type="button" class="button button-secondary"><?php esc_html_e( 'Sync Expiry', 'edmingle-tutor-migration' ); ?></button>
			</div>
		</div>

		<!-- Card: Migration Logs -->
		<div class="postbox etm-card">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Migration Logs', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'No recent activity.', 'edmingle-tutor-migration' ); ?></p>
				<a href="#" class="button button-secondary"><?php esc_html_e( 'View Logs', 'edmingle-tutor-migration' ); ?></a>
			</div>
		</div>

	</div>
</div>
