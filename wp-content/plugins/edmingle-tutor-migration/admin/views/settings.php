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

			<!-- Google Sheets Sync Configuration -->
			<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc;">
				<h2><?php esc_html_e( '3. Google Sheets Integration', 'edmingle-tutor-migration' ); ?></h2>
				<form method="post" action="options.php">
					<?php settings_fields( 'etm_gsheet_settings_group' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="etm_gsheet_sync_enabled"><?php esc_html_e( 'Enable Sync', 'edmingle-tutor-migration' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="etm_gsheet_sync_enabled" name="etm_gsheet_sync_enabled" value="1" <?php checked( 1, get_option( 'etm_gsheet_sync_enabled', 0 ) ); ?> />
								<span class="description"><?php esc_html_e( 'Automatically sync new registrations to Google Sheet.', 'edmingle-tutor-migration' ); ?></span>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="etm_gsheet_webhook_url"><?php esc_html_e( 'Apps Script URL', 'edmingle-tutor-migration' ); ?></label>
							</th>
							<td>
								<input type="url" id="etm_gsheet_webhook_url" name="etm_gsheet_webhook_url" value="<?php echo esc_url( get_option( 'etm_gsheet_webhook_url', '' ) ); ?>" class="regular-text" placeholder="https://script.google.com/macros/s/.../exec" style="width: 100%;" />
								<p class="description"><?php esc_html_e( 'The Web App URL deployed from your Google Apps Script.', 'edmingle-tutor-migration' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Google Sheets Settings', 'edmingle-tutor-migration' ) ); ?>
				</form>

				<!-- Bulk Sync Existing Users -->
				<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Sync Existing Registered Users', 'edmingle-tutor-migration' ); ?></h3>
					<p><?php esc_html_e( 'You can sync all previously registered users who are not yet present in your Google Sheet.', 'edmingle-tutor-migration' ); ?></p>
					
					<div id="etm-gsheet-bulk-status" style="margin-bottom: 15px;">
						<p><strong><?php esc_html_e( 'Unsynced Users:', 'edmingle-tutor-migration' ); ?></strong> <span id="etm-unsynced-count">...</span></p>
					</div>

					<div id="etm-gsheet-bulk-progress" style="display:none; margin-bottom: 15px; background: #f0f0f1; padding: 10px; border-left: 4px solid #2271b1;">
						<p style="margin: 0 0 5px 0;"><strong><?php esc_html_e( 'Syncing...', 'edmingle-tutor-migration' ); ?></strong></p>
						<p style="margin: 0; font-size: 0.9em; color: #555;">
							<?php esc_html_e( 'Successfully Synced:', 'edmingle-tutor-migration' ); ?> <span id="etm-bulk-synced-count">0</span> | 
							<?php esc_html_e( 'Failed:', 'edmingle-tutor-migration' ); ?> <span id="etm-bulk-failed-count">0</span> | 
							<?php esc_html_e( 'Remaining:', 'edmingle-tutor-migration' ); ?> <span id="etm-bulk-remaining-count">0</span>
						</p>
					</div>

					<?php $has_webhook = ! empty( get_option( 'etm_gsheet_webhook_url', '' ) ); ?>
					<button type="button" id="etm-btn-sync-existing" class="button button-secondary" <?php echo $has_webhook ? '' : 'disabled'; ?>>
						<?php esc_html_e( 'Sync Existing Users Now', 'edmingle-tutor-migration' ); ?>
					</button>
					<button type="button" id="etm-btn-reset-sync" class="button button-link-delete" style="margin-left: 10px; vertical-align: middle;" <?php echo $has_webhook ? '' : 'disabled'; ?>>
						<?php esc_html_e( 'Reset & Re-sync All Users', 'edmingle-tutor-migration' ); ?>
					</button>
					<span class="spinner" id="etm-bulk-sync-spinner" style="float: none; margin-top: 4px;"></span>
				</div>

				<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Google Apps Script Instructions', 'edmingle-tutor-migration' ); ?></h3>
					<p><?php esc_html_e( 'To set up your Google Sheet sync, follow these steps:', 'edmingle-tutor-migration' ); ?></p>
					<ol style="margin-left: 20px; list-style-type: decimal;">
						<li><?php esc_html_e( 'Create a new Google Sheet.', 'edmingle-tutor-migration' ); ?></li>
						<li><?php esc_html_e( 'Go to Extensions -> Apps Script.', 'edmingle-tutor-migration' ); ?></li>
						<li><?php esc_html_e( 'Replace the code in the editor with the following code:', 'edmingle-tutor-migration' ); ?></li>
					</ol>
					<pre style="background: #f6f6f6; padding: 10px; overflow-x: auto; font-size: 11px; border: 1px solid #e5e5e5; border-radius: 3px;">
function doPost(e) {
  var lock = LockService.getScriptLock();
  try {
    // Acquire lock for up to 30 seconds to prevent concurrent write collisions
    lock.waitLock(30000);

    var data = JSON.parse(e.postData.contents);
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    
    // Add headers if sheet is empty
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(["Full Name", "Email Address", "Mobile Number", "Registration / Enrollment Date", "Course Name", "Course Type"]);
    }
    
    var emailToMatch = (data.email || '').toString().trim().toLowerCase();
    var lastRow = sheet.getLastRow();
    
    // Search for existing row with the same email (Column B) and remove duplicate
    if (emailToMatch !== '' && lastRow > 1) {
      var emailValues = sheet.getRange(2, 2, lastRow - 1, 1).getValues();
      for (var i = emailValues.length - 1; i >= 0; i--) {
        var cellEmail = (emailValues[i][0] || '').toString().trim().toLowerCase();
        if (cellEmail === emailToMatch) {
          sheet.deleteRow(i + 2);
        }
      }
    }
    
    // Insert new row at row 2 (at the very top, directly below the header row)
    sheet.insertRowBefore(2);
    
    // Determine the date to display: enrollment/purchase date if available, fallback to registration date
    var displayDate = (data.enrollment_date && data.enrollment_date !== '—') ? data.enrollment_date : (data.date || data.registration_date || '');
    
    // Set the values of the updated row
    sheet.getRange(2, 1, 1, 6).setValues([[
      data.full_name || '',
      data.email || '',
      data.mobile || '—',
      displayDate,
      data.course_name || '—',
      data.course_type || '—'
    ]]);
    
    return ContentService.createTextOutput(JSON.stringify({status: "success"}))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({status: "error", message: error.toString()}))
      .setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}
					</pre>
					<ol style="margin-left: 20px; list-style-type: decimal;" start="4">
						<li><?php esc_html_e( 'Click Deploy -> New deployment.', 'edmingle-tutor-migration' ); ?></li>
						<li><?php esc_html_e( 'Select type: Web app.', 'edmingle-tutor-migration' ); ?></li>
						<li><?php esc_html_e( 'Set Execute as: "Me", Who has access: "Anyone".', 'edmingle-tutor-migration' ); ?></li>
						<li><?php esc_html_e( 'Deploy, authorize access, and copy the Web App URL here.', 'edmingle-tutor-migration' ); ?></li>
					</ol>
				</div>
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
