<?php
/**
 * Logs & Debug View (Phase 0)
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$is_debug = get_option( 'etm_debug_mode', 'no' ) === 'yes';
?>
<div class="wrap etm-logs">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Logs & Debug Mode', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<div class="postbox" style="max-width: 600px; margin-top: 20px;">
		<div class="postbox-header">
			<h2 class="hndle"><?php esc_html_e( 'Debug Mode Configuration', 'edmingle-tutor-migration' ); ?></h2>
		</div>
		<div class="inside">
			<p><?php esc_html_e( 'When debug mode is enabled, all API requests (URL, Headers, Payload, Response, Errors) will be logged to the server disk.', 'edmingle-tutor-migration' ); ?></p>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Debug Mode', 'edmingle-tutor-migration' ); ?></th>
					<td>
						<label class="etm-switch">
							<input type="checkbox" id="etm-toggle-debug" <?php checked( $is_debug, true ); ?>>
							<span class="etm-slider round"></span>
						</label>
						<span id="etm-debug-status-text" style="margin-left: 10px; vertical-align: super; font-weight: bold;">
							<?php echo $is_debug ? esc_html__( 'Enabled', 'edmingle-tutor-migration' ) : esc_html__( 'Disabled', 'edmingle-tutor-migration' ); ?>
						</span>
					</td>
				</tr>
			</table>
			<p class="description">
				<?php esc_html_e( 'Log files are saved securely inside:', 'edmingle-tutor-migration' ); ?> <br>
				<code>wp-content/uploads/edmingle-migration/logs/</code>
			</p>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#etm-toggle-debug').on('change', function() {
		var status = $(this).is(':checked') ? 'yes' : 'no';
		$('#etm-debug-status-text').text(status === 'yes' ? 'Enabled' : 'Disabled');
		
		$.post(ajaxurl, {
			action: 'etm_toggle_debug',
			nonce: '<?php echo wp_create_nonce("etm_admin_nonce"); ?>',
			status: status
		});
	});
});
</script>
