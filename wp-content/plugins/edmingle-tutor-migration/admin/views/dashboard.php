<?php
/**
 * Dashboard View (Phase 0)
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="wrap etm-dashboard">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle Migration Dashboard', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<div class="postbox" style="max-width: 600px; margin-top: 20px;">
		<div class="postbox-header">
			<h2 class="hndle"><?php esc_html_e( 'Test Connection', 'edmingle-tutor-migration' ); ?></h2>
		</div>
		<div class="inside">
			<p><?php esc_html_e( 'Test your connection to the Edmingle API using the credentials provided in the Settings page.', 'edmingle-tutor-migration' ); ?></p>
			
			<div style="margin: 20px 0;">
				<button type="button" class="button button-primary button-hero" id="etm-test-connection-btn">
					<?php esc_html_e( 'Test Connection', 'edmingle-tutor-migration' ); ?>
				</button>
				<span class="spinner" id="etm-test-spinner" style="float:none; margin-top: 5px;"></span>
			</div>

			<div id="etm-test-results" style="display:none; padding: 15px; border-left: 4px solid #fff; background: #fff;">
				<h3 style="margin-top:0;" id="etm-test-status-heading"></h3>
				<ul style="margin-bottom:0;">
					<li><strong>HTTP Status:</strong> <span id="etm-test-http"></span></li>
					<li><strong>Response Time:</strong> <span id="etm-test-time"></span></li>
					<li><strong>Auth Method:</strong> <span id="etm-test-method"></span></li>
				</ul>
			</div>
		</div>
	</div>
</div>
