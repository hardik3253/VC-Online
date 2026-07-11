<?php
/**
 * Course Mapping View
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="wrap etm-mapping">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Course Mapping', 'edmingle-tutor-migration' ); ?></h1>
	<button id="etm-fetch-courses" class="button button-secondary"><?php esc_html_e( 'Fetch & Auto-Match', 'edmingle-tutor-migration' ); ?></button>
	<span class="spinner" id="etm-mapping-spinner"></span>
	<hr class="wp-header-end">

	<div id="etm-mapping-notice" class="notice" style="display:none;"><p></p></div>

	<div id="etm-mapping-container" style="display:none; margin-top:20px;">
		<form id="etm-mapping-form">
			<h2><?php esc_html_e( 'Matched Courses', 'edmingle-tutor-migration' ); ?></h2>
			<table class="wp-list-table widefat fixed striped" id="etm-matched-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Edmingle Course', 'edmingle-tutor-migration' ); ?></th>
						<th><?php esc_html_e( 'Tutor LMS Course', 'edmingle-tutor-migration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<!-- Populated via JS -->
				</tbody>
			</table>

			<h2 style="margin-top:40px;"><?php esc_html_e( 'Missing Courses', 'edmingle-tutor-migration' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These Edmingle courses could not be automatically matched by title. Select a Tutor LMS course manually.', 'edmingle-tutor-migration' ); ?></p>
			
			<table class="wp-list-table widefat fixed striped" id="etm-missing-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Edmingle Course', 'edmingle-tutor-migration' ); ?></th>
						<th><?php esc_html_e( 'Tutor LMS Course', 'edmingle-tutor-migration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<!-- Populated via JS -->
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" id="etm-save-mapping"><?php esc_html_e( 'Save Mapping', 'edmingle-tutor-migration' ); ?></button>
			</p>
		</form>
	</div>
</div>
