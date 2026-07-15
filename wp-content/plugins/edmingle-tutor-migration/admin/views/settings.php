<?php
/**
 * Settings View (Phase 0)
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$base_url = get_option( 'etm_api_base_url', '' );
$email    = get_option( 'etm_admin_email', '' );
$password = get_option( 'etm_admin_password', '' ) ? '************' : ''; // Do not display raw password

// Read-only settings
$api_key        = get_option( 'etm_admin_token', '' );
$user_id        = get_option( 'etm_admin_user_id', '' );
$server_key     = get_option( 'etm_admin_server_key', '' );
$org_id         = get_option( 'etm_admin_orgid', '' );
$institution_id = get_option( 'etm_admin_institution_id', '' );

$is_connected = ! empty( $api_key ) && ! empty( $org_id );

?>
<div class="wrap etm-settings">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle Setup Wizard', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<div style="display: flex; gap: 40px; flex-wrap: wrap;">
		<!-- Configuration Form -->
		<div style="flex: 1; min-width: 400px; max-width: 600px;">
			<h2><?php esc_html_e( '1. Configuration', 'edmingle-tutor-migration' ); ?></h2>
			<form id="etm-setup-form">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="etm_api_base_url"><?php esc_html_e( 'Edmingle Base URL', 'edmingle-tutor-migration' ); ?></label></th>
						<td>
							<input type="url" id="etm_api_base_url" name="etm_api_base_url" value="<?php echo esc_attr( $base_url ); ?>" class="regular-text" placeholder="https://yourcompany.edmingle.com" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="etm_admin_email"><?php esc_html_e( 'Admin Email', 'edmingle-tutor-migration' ); ?></label></th>
						<td>
							<input type="email" id="etm_admin_email" name="etm_admin_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="etm_admin_password"><?php esc_html_e( 'Admin Password', 'edmingle-tutor-migration' ); ?></label></th>
						<td>
							<div style="position: relative; display: inline-block; width: 100%; max-width: 25em;">
								<input type="password" id="etm_admin_password" name="etm_admin_password" value="<?php echo esc_attr( $password ); ?>" class="regular-text" style="width: 100%; padding-right: 30px;" required />
								<span id="etm-toggle-password" class="dashicons dashicons-visibility" style="position: absolute; right: 5px; top: 5px; cursor: pointer; color: #888;"></span>
							</div>
							<p class="description"><?php esc_html_e( 'Password is encrypted before saving.', 'edmingle-tutor-migration' ); ?></p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<button type="button" id="etm-btn-initialize" class="button button-primary button-large">
						<?php esc_html_e( 'Initialize Edmingle', 'edmingle-tutor-migration' ); ?>
					</button>
					<span class="spinner" style="float: none; margin-top: 4px;"></span>
				</p>
			</form>

			<div id="etm-setup-error" class="notice notice-error inline" style="display:none; padding: 15px; margin-top: 20px;">
				<h3 style="margin-top: 0; color: #d63638;"><?php esc_html_e( 'Setup Failed', 'edmingle-tutor-migration' ); ?></h3>
				<p class="error-message"></p>
				<button type="button" class="button etm-btn-retry"><?php esc_html_e( 'Retry', 'edmingle-tutor-migration' ); ?></button>
			</div>
		</div>

		<!-- Status Panel -->
		<div style="flex: 1; min-width: 400px; max-width: 500px;">
			<h2><?php esc_html_e( '2. Setup Progress', 'edmingle-tutor-migration' ); ?></h2>
			<div class="etm-wizard-status" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<ul style="margin: 0; list-style: none;">
					<li id="step-authenticate" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Authentication', 'edmingle-tutor-migration' ); ?>
					</li>
					<li id="step-api-key" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'API Key', 'edmingle-tutor-migration' ); ?>
					</li>
					<li id="step-institution" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Institution', 'edmingle-tutor-migration' ); ?>
					</li>
					<li id="step-organization" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Organization', 'edmingle-tutor-migration' ); ?>
					</li>
					<li id="step-students" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Students API', 'edmingle-tutor-migration' ); ?>
					</li>
					<li id="step-enrollments" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Enrollment API', 'edmingle-tutor-migration' ); ?>
					</li>
					<li id="step-curriculum" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Curriculum API', 'edmingle-tutor-migration' ); ?>
					</li>
					<li id="step-progress" class="etm-step pending">
						<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Progress API', 'edmingle-tutor-migration' ); ?>
					</li>
				</ul>
			</div>

			<!-- Read Only Panel -->
			<div id="etm-read-only-panel" style="<?php echo $is_connected ? '' : 'display:none;'; ?> margin-top: 30px;">
				<h2 style="color: #00a32a;">
					<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Connected', 'edmingle-tutor-migration' ); ?>
				</h2>
				<table class="form-table" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #00a32a;">
					<tr>
						<th><?php esc_html_e( 'API Key', 'edmingle-tutor-migration' ); ?></th>
						<td id="ro-api-key">**************</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Organization ID', 'edmingle-tutor-migration' ); ?></th>
						<td id="ro-org-id"><?php echo esc_html( $org_id ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Institution ID', 'edmingle-tutor-migration' ); ?></th>
						<td id="ro-inst-id"><?php echo esc_html( $institution_id ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'User ID', 'edmingle-tutor-migration' ); ?></th>
						<td id="ro-user-id"><?php echo esc_html( $user_id ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Server Key', 'edmingle-tutor-migration' ); ?></th>
						<td id="ro-server-key">**************</td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>

<style>
.etm-step { padding: 8px 0; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; }
.etm-step:last-child { border-bottom: none; }
.etm-step .dashicons { margin-right: 10px; }
.etm-step.pending { color: #8c8f94; }
.etm-step.running { color: #2271b1; font-weight: bold; }
.etm-step.success { color: #00a32a; }
.etm-step.failed { color: #d63638; }
.etm-step.running .dashicons { animation: rotation 2s infinite linear; }
.etm-step.success .dashicons:before { content: "\f147"; } /* dashicons-yes */
.etm-step.failed .dashicons:before { content: "\f158"; } /* dashicons-no */
@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }
</style>
