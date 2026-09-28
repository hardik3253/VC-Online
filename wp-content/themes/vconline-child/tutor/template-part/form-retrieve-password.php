<?php
/**
 * Frontend New Password Form Template Override
 *
 * Provides:
 * - Option to enter custom password or generate a secure random password
 * - Real-time password strength meter and match validator
 * - "Save Password" submission directly redirecting to frontend login
 *
 * @package VC_Online_Child
 * @subpackage Tutor_Templates
 */

defined( 'ABSPATH' ) || exit;

use TUTOR\Input;

$reset_key  = Input::get( 'reset_key' ) ? Input::get( 'reset_key' ) : Input::get( 'key' );
$user_id    = Input::get( 'user_id', 0, Input::TYPE_INT );
$user_login = Input::get( 'login' );

// If login was passed instead of user_id, resolve user_id
if ( ! $user_id && $user_login ) {
	$user_obj = get_user_by( 'login', sanitize_user( wp_unslash( $user_login ) ) );
	if ( ! $user_obj && is_email( $user_login ) ) {
		$user_obj = get_user_by( 'email', sanitize_user( wp_unslash( $user_login ) ) );
	}
	if ( $user_obj ) {
		$user_id = $user_obj->ID;
	}
}

$user = $user_id ? get_user_by( 'ID', $user_id ) : false;
$login_url = class_exists( 'VCA_Frontend_Password_Reset' ) ? VCA_Frontend_Password_Reset::get_login_url() : home_url( '/tutor-login/' );
$request_new_url = class_exists( 'VCA_Frontend_Password_Reset' ) ? VCA_Frontend_Password_Reset::get_reset_url() : home_url( '/dashboard/retrieve-password/' );

// Validate key upfront
$is_key_valid = false;
if ( $user && $reset_key ) {
	$check_user = check_password_reset_key( $reset_key, $user->user_login );
	if ( ! is_wp_error( $check_user ) ) {
		$is_key_valid = true;
	}
}

do_action( 'tutor_before_reset_password_form' );
?>

<style type="text/css">
	.vca-password-card {
		max-width: 480px !important;
		width: 100% !important;
		margin: 50px auto !important;
		background: #ffffff !important;
		border-radius: 16px !important;
		box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08) !important;
		border: 1px solid #eef0f4 !important;
		padding: 40px 36px !important;
		box-sizing: border-box !important;
	}
	.vca-password-card * {
		box-sizing: border-box !important;
	}
	.vca-password-card .vca-card-header {
		text-align: center !important;
		margin-bottom: 28px !important;
	}
	.vca-password-card .vca-card-icon {
		width: 54px !important;
		height: 54px !important;
		margin: 0 auto 16px !important;
		background: #f4f4f5 !important;
		color: #000000 !important;
		border-radius: 50% !important;
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
	}
	.vca-password-card .vca-card-title {
		font-size: 24px !important;
		font-weight: 700 !important;
		color: #0f172a !important;
		margin: 0 0 10px !important;
		letter-spacing: -0.3px !important;
	}
	.vca-password-card .vca-card-desc {
		font-size: 14px !important;
		color: #64748b !important;
		margin: 0 !important;
		line-height: 1.6 !important;
	}
	.vca-password-card form {
		width: 100% !important;
		display: block !important;
		text-align: left !important;
		margin: 0 !important;
		padding: 0 !important;
	}
	.vca-password-card .vca-form-group {
		width: 100% !important;
		display: block !important;
		margin-bottom: 20px !important;
		text-align: left !important;
	}
	.vca-password-card label.vca-label {
		display: block !important;
		width: 100% !important;
		font-size: 14px !important;
		font-weight: 600 !important;
		color: #1e293b !important;
		margin-bottom: 8px !important;
		text-align: left !important;
		white-space: normal !important;
	}
	.vca-password-card .vca-input-wrap {
		width: 100% !important;
		display: flex !important;
		position: relative !important;
		align-items: center !important;
	}
	.vca-password-card input.vca-input {
		width: 100% !important;
		min-width: 100% !important;
		max-width: 100% !important;
		height: 50px !important;
		padding: 0 16px !important;
		font-size: 15px !important;
		color: #0f172a !important;
		background: #ffffff !important;
		border: 1.5px solid #cbd5e1 !important;
		border-radius: 8px !important;
		box-sizing: border-box !important;
		outline: none !important;
		transition: border-color 0.2s, box-shadow 0.2s !important;
	}
	.vca-password-card input.vca-input:focus {
		border-color: #000000 !important;
		box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08) !important;
	}
	.vca-password-card .vca-input-has-toggle {
		padding-right: 48px !important;
	}
	.vca-password-card .vca-toggle-pwd-btn {
		position: absolute !important;
		right: 12px !important;
		top: 50% !important;
		transform: translateY(-50%) !important;
		background: transparent !important;
		border: none !important;
		cursor: pointer !important;
		padding: 6px !important;
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
		color: #94a3b8 !important;
		transition: color 0.2s !important;
		z-index: 2 !important;
	}
	.vca-password-card .vca-toggle-pwd-btn:hover {
		color: #000000 !important;
	}
	.vca-password-card button.vca-submit-btn,
	.vca-password-card a.vca-submit-btn {
		width: 100% !important;
		min-width: 100% !important;
		max-width: 100% !important;
		height: 50px !important;
		background-color: #000000 !important;
		color: #ffffff !important;
		border: 1px solid #000000 !important;
		border-radius: 8px !important;
		font-size: 15px !important;
		font-weight: 600 !important;
		cursor: pointer !important;
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
		text-align: center !important;
		text-decoration: none !important;
		margin-top: 10px !important;
		transition: background-color 0.2s, transform 0.1s !important;
		letter-spacing: 0.2px !important;
	}
	.vca-password-card button.vca-submit-btn:hover,
	.vca-password-card a.vca-submit-btn:hover {
		background-color: #262626 !important;
		border-color: #262626 !important;
		color: #ffffff !important;
	}
	.vca-password-card .vca-generate-pwd-bar {
		display: flex !important;
		align-items: center !important;
		justify-content: space-between !important;
		background: #f8fafc !important;
		border: 1px solid #e2e8f0 !important;
		border-radius: 8px !important;
		padding: 10px 14px !important;
		margin-bottom: 22px !important;
		width: 100% !important;
		box-sizing: border-box !important;
	}
	.vca-password-card .vca-generate-btn {
		display: inline-flex !important;
		align-items: center !important;
		gap: 6px !important;
		background: #000000 !important;
		border: 1px solid #000000 !important;
		color: #ffffff !important;
		padding: 8px 16px !important;
		border-radius: 6px !important;
		font-size: 13px !important;
		font-weight: 600 !important;
		cursor: pointer !important;
		transition: all 0.2s ease !important;
	}
	.vca-password-card .vca-generate-btn:hover {
		background: #262626 !important;
		border-color: #262626 !important;
	}
	.vca-password-card .vca-copy-btn {
		display: inline-flex !important;
		align-items: center !important;
		gap: 5px !important;
		background: #ffffff !important;
		border: 1px solid #cbd5e1 !important;
		color: #475569 !important;
		padding: 7px 14px !important;
		border-radius: 6px !important;
		font-size: 12px !important;
		font-weight: 600 !important;
		cursor: pointer !important;
		transition: all 0.2s !important;
	}
	.vca-password-card .vca-copy-btn:hover {
		color: #000000 !important;
		border-color: #000000 !important;
	}
	.vca-password-card .vca-pwd-strength-container {
		margin-top: 8px !important;
		width: 100% !important;
	}
	.vca-password-card .vca-pwd-strength-bars {
		display: flex !important;
		gap: 6px !important;
		height: 4px !important;
		margin-bottom: 6px !important;
		width: 100% !important;
	}
	.vca-password-card .vca-pwd-strength-bar {
		flex: 1 !important;
		height: 100% !important;
		background: #e2e8f0 !important;
		border-radius: 2px !important;
		transition: background-color 0.3s !important;
	}
	.vca-password-card .vca-pwd-strength-label {
		font-size: 12px !important;
		color: #64748b !important;
		display: flex !important;
		justify-content: space-between !important;
		width: 100% !important;
	}
	.vca-password-card .vca-match-status {
		font-size: 12px !important;
		margin-top: 6px !important;
		min-height: 18px !important;
		font-weight: 500 !important;
	}
	.vca-password-card .vca-match-status.match {
		color: #10b981 !important;
	}
	.vca-password-card .vca-match-status.mismatch {
		color: #ef4444 !important;
	}
	.vca-password-card .vca-back-link-wrap {
		text-align: center !important;
		margin-top: 22px !important;
		width: 100% !important;
	}
	.vca-password-card .vca-back-link {
		display: inline-flex !important;
		align-items: center !important;
		justify-content: center !important;
		gap: 6px !important;
		font-size: 14px !important;
		color: #64748b !important;
		text-decoration: none !important;
		font-weight: 500 !important;
		transition: color 0.2s !important;
	}
	.vca-password-card .vca-back-link:hover {
		color: #000000 !important;
	}
</style>

<div class="vca-password-card">
	<?php if ( ! $is_key_valid ) : ?>
		<!-- Invalid or Expired Key State -->
		<div class="vca-card-header">
			<div class="vca-card-icon" style="background: #fef2f2 !important; color: #ef4444 !important;">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"></circle>
					<line x1="12" y1="8" x2="12" y2="12"></line>
					<line x1="12" y1="16" x2="12.01" y2="16"></line>
				</svg>
			</div>
			<h2 class="vca-card-title"><?php esc_html_e( 'Reset Link Expired or Invalid', 'vconline' ); ?></h2>
			<p class="vca-card-desc">
				<?php esc_html_e( 'This password reset link is invalid or has already been used. Please request a new password reset link below.', 'vconline' ); ?>
			</p>
		</div>

		<div class="vca-form-group" style="margin-top: 24px; margin-bottom: 0;">
			<a href="<?php echo esc_url( $request_new_url ); ?>" class="vca-submit-btn">
				<?php esc_html_e( 'Request New Reset Link', 'vconline' ); ?>
			</a>
		</div>

		<div class="vca-back-link-wrap">
			<a href="<?php echo esc_url( $login_url ); ?>" class="vca-back-link">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="19" y1="12" x2="5" y2="12"></line>
					<polyline points="12 19 5 12 12 5"></polyline>
				</svg>
				<?php esc_html_e( 'Back to Login', 'vconline' ); ?>
			</a>
		</div>

	<?php else : ?>
		<!-- Valid Key: New Password Form -->
		<div class="vca-card-header">
			<div class="vca-card-icon">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 2l-2 2m-1-1l-2 2m-2-2l-2 2m-2-2l-2 2m-2-2L3 14a4 4 0 0 0 0 5.66l1.34 1.34A4 4 0 0 0 10 21l11-11v-4l-2-2z"></path>
					<circle cx="7.5" cy="16.5" r="1.5"></circle>
				</svg>
			</div>
			<h2 class="vca-card-title"><?php esc_html_e( 'Create New Password', 'vconline' ); ?></h2>
			<p class="vca-card-desc">
				<?php
				echo sprintf(
					esc_html__( 'Choose a new password for %s or generate a secure one.', 'vconline' ),
					'<strong>' . esc_html( $user->user_login ) . '</strong>'
				);
				?>
			</p>
		</div>

		<div class="tutor-mb-16">
			<?php tutor_alert( null, 'any', true, true ); ?>
		</div>

		<!-- Generator Toolbar -->
		<div class="vca-generate-pwd-bar">
			<button type="button" id="vca_generate_btn" class="vca-generate-btn">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
				</svg>
				<?php esc_html_e( 'Generate Strong Password', 'vconline' ); ?>
			</button>
			<button type="button" id="vca_copy_btn" class="vca-copy-btn" style="display: none;">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
					<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
				</svg>
				<span id="vca_copy_text"><?php esc_html_e( 'Copy', 'vconline' ); ?></span>
			</button>
		</div>

		<form method="post" id="vca_reset_password_form" class="tutor-reset-password-form tutor-ResetPassword lost_reset_password">
			<?php tutor_nonce_field(); ?>
			<input type="hidden" name="tutor_action" value="tutor_process_reset_password">
			<input type="hidden" name="reset_key" value="<?php echo esc_attr( $reset_key ); ?>" />
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />

			<!-- New Password Field -->
			<div class="vca-form-group">
				<label class="vca-label" for="vca_new_password">
					<?php esc_html_e( 'New Password', 'vconline' ); ?>
				</label>
				<div class="vca-input-wrap">
					<input class="vca-input vca-input-has-toggle" type="password" name="password" id="vca_new_password" required autocomplete="new-password" placeholder="<?php esc_attr_e( 'Enter or generate new password', 'vconline' ); ?>">
					<button type="button" class="vca-toggle-pwd-btn" data-target="vca_new_password" title="<?php esc_attr_e( 'Show/Hide Password', 'vconline' ); ?>">
						<svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
							<circle cx="12" cy="12" r="3"></circle>
						</svg>
						<svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
							<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
							<line x1="1" y1="1" x2="23" y2="23"></line>
						</svg>
					</button>
				</div>

				<!-- Password Strength Indicator -->
				<div class="vca-pwd-strength-container">
					<div class="vca-pwd-strength-bars">
						<div class="vca-pwd-strength-bar" id="str_bar_1"></div>
						<div class="vca-pwd-strength-bar" id="str_bar_2"></div>
						<div class="vca-pwd-strength-bar" id="str_bar_3"></div>
						<div class="vca-pwd-strength-bar" id="str_bar_4"></div>
					</div>
					<div class="vca-pwd-strength-label">
						<span id="vca_strength_text"><?php esc_html_e( 'Password strength', 'vconline' ); ?></span>
						<span id="vca_strength_hint" style="color: #94a3b8;"><?php esc_html_e( 'Min 6 characters', 'vconline' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Confirm Password Field -->
			<div class="vca-form-group">
				<label class="vca-label" for="vca_confirm_password">
					<?php esc_html_e( 'Confirm New Password', 'vconline' ); ?>
				</label>
				<div class="vca-input-wrap">
					<input class="vca-input vca-input-has-toggle" type="password" name="confirm_password" id="vca_confirm_password" required autocomplete="new-password" placeholder="<?php esc_attr_e( 'Re-enter your new password', 'vconline' ); ?>">
					<button type="button" class="vca-toggle-pwd-btn" data-target="vca_confirm_password" title="<?php esc_attr_e( 'Show/Hide Password', 'vconline' ); ?>">
						<svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
							<circle cx="12" cy="12" r="3"></circle>
						</svg>
						<svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
							<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
							<line x1="1" y1="1" x2="23" y2="23"></line>
						</svg>
					</button>
				</div>
				<div class="vca-match-status" id="vca_match_status"></div>
			</div>

			<?php do_action( 'tutor_reset_password_form' ); ?>

			<!-- Submit Button: Save Password -->
			<div class="vca-form-group" style="margin-bottom: 0;">
				<button type="submit" id="vca_save_pwd_btn" class="vca-submit-btn" value="<?php esc_attr_e( 'Save Password', 'vconline' ); ?>">
					<?php esc_html_e( 'Save Password', 'vconline' ); ?>
				</button>
			</div>

			<div class="vca-back-link-wrap">
				<a href="<?php echo esc_url( $login_url ); ?>" class="vca-back-link">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="19" y1="12" x2="5" y2="12"></line>
						<polyline points="12 19 5 12 12 5"></polyline>
					</svg>
					<?php esc_html_e( 'Back to Login', 'vconline' ); ?>
				</a>
			</div>
		</form>

		<!-- Interactive Client-side Script -->
		<script type="text/javascript">
		(function() {
			var pwdInput     = document.getElementById('vca_new_password');
			var confirmInput = document.getElementById('vca_confirm_password');
			var generateBtn  = document.getElementById('vca_generate_btn');
			var copyBtn      = document.getElementById('vca_copy_btn');
			var copyText     = document.getElementById('vca_copy_text');
			var matchStatus  = document.getElementById('vca_match_status');
			var strengthText = document.getElementById('vca_strength_text');
			var strBars      = [
				document.getElementById('str_bar_1'),
				document.getElementById('str_bar_2'),
				document.getElementById('str_bar_3'),
				document.getElementById('str_bar_4')
			];
			var resetForm    = document.getElementById('vca_reset_password_form');

			// Cryptographically secure password generator
			function generatePassword(length) {
				length = length || 16;
				var charsetUpper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
				var charsetLower = 'abcdefghijklmnopqrstuvwxyz';
				var charsetNum   = '0123456789';
				var charsetSym   = '!@#$%^&*()_+~|}{[]:;?><,.-=';
				var all = charsetUpper + charsetLower + charsetNum + charsetSym;

				var pwd = '';
				var cryptoObj = window.crypto || window.msCrypto;

				// Guarantee at least 1 of each type
				pwd += charsetUpper[Math.floor(Math.random() * charsetUpper.length)];
				pwd += charsetLower[Math.floor(Math.random() * charsetLower.length)];
				pwd += charsetNum[Math.floor(Math.random() * charsetNum.length)];
				pwd += charsetSym[Math.floor(Math.random() * charsetSym.length)];

				if (cryptoObj && cryptoObj.getRandomValues) {
					var values = new Uint32Array(length - 4);
					cryptoObj.getRandomValues(values);
					for (var i = 0; i < values.length; i++) {
						pwd += all[values[i] % all.length];
					}
				} else {
					for (var j = 0; j < length - 4; j++) {
						pwd += all[Math.floor(Math.random() * all.length)];
					}
				}

				// Shuffle password
				return pwd.split('').sort(function() { return 0.5 - Math.random(); }).join('');
			}

			// Password strength evaluator
			function checkStrength(p) {
				if (!p || p.length === 0) return 0;
				var score = 0;
				if (p.length >= 6) score++;
				if (p.length >= 10) score++;
				if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
				if (/[0-9]/.test(p) && /[^A-Za-z0-9]/.test(p)) score++;
				return score;
			}

			function updateStrengthMeter(score) {
				var colors = ['#e2e8f0', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
				var labels = [
					'<?php echo esc_js( __( 'Password strength', 'vconline' ) ); ?>',
					'<?php echo esc_js( __( 'Weak', 'vconline' ) ); ?>',
					'<?php echo esc_js( __( 'Fair', 'vconline' ) ); ?>',
					'<?php echo esc_js( __( 'Good', 'vconline' ) ); ?>',
					'<?php echo esc_js( __( 'Strong', 'vconline' ) ); ?>'
				];

				for (var i = 0; i < 4; i++) {
					if (i < score) {
						strBars[i].style.backgroundColor = colors[score];
					} else {
						strBars[i].style.backgroundColor = '#e2e8f0';
					}
				}
				strengthText.textContent = labels[score];
				strengthText.style.color = score > 0 ? colors[score] : '#64748b';
			}

			function checkMatch() {
				var p1 = pwdInput.value;
				var p2 = confirmInput.value;

				if (!p2) {
					matchStatus.textContent = '';
					matchStatus.className = 'vca-match-status';
					return;
				}

				if (p1 === p2) {
					matchStatus.textContent = '✓ <?php echo esc_js( __( 'Passwords match', 'vconline' ) ); ?>';
					matchStatus.className = 'vca-match-status match';
				} else {
					matchStatus.textContent = '✕ <?php echo esc_js( __( 'Passwords do not match', 'vconline' ) ); ?>';
					matchStatus.className = 'vca-match-status mismatch';
				}
			}

			// Generate password button click
			generateBtn.addEventListener('click', function() {
				var newPwd = generatePassword(16);

				// Fill both fields
				pwdInput.value = newPwd;
				confirmInput.value = newPwd;

				// Temporarily show passwords so user sees what was generated
				pwdInput.type = 'text';
				confirmInput.type = 'text';
				document.querySelectorAll('.vca-toggle-pwd-btn').forEach(function(btn) {
					btn.querySelector('.eye-open').style.display = 'none';
					btn.querySelector('.eye-closed').style.display = 'inline-block';
				});

				updateStrengthMeter(4);
				checkMatch();

				// Show copy button
				copyBtn.style.display = 'inline-flex';
				copyText.textContent = '<?php echo esc_js( __( 'Copy', 'vconline' ) ); ?>';
			});

			// Copy password button click
			copyBtn.addEventListener('click', function() {
				if (!pwdInput.value) return;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(pwdInput.value).then(function() {
						copyText.textContent = '✓ <?php echo esc_js( __( 'Copied!', 'vconline' ) ); ?>';
						setTimeout(function() {
							copyText.textContent = '<?php echo esc_js( __( 'Copy', 'vconline' ) ); ?>';
						}, 2500);
					});
				} else {
					pwdInput.select();
					document.execCommand('copy');
					copyText.textContent = '✓ <?php echo esc_js( __( 'Copied!', 'vconline' ) ); ?>';
					setTimeout(function() {
						copyText.textContent = '<?php echo esc_js( __( 'Copy', 'vconline' ) ); ?>';
					}, 2500);
				}
			});

			// Show/Hide toggle button click
			document.querySelectorAll('.vca-toggle-pwd-btn').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					var targetId = this.getAttribute('data-target');
					var input = document.getElementById(targetId);
					if (!input) return;

					var eyeOpen = this.querySelector('.eye-open');
					var eyeClosed = this.querySelector('.eye-closed');

					if (input.type === 'password') {
						input.type = 'text';
						eyeOpen.style.display = 'none';
						eyeClosed.style.display = 'inline-block';
					} else {
						input.type = 'password';
						eyeOpen.style.display = 'inline-block';
						eyeClosed.style.display = 'none';
					}
				});
			});

			// Real-time input listeners
			pwdInput.addEventListener('input', function() {
				var score = checkStrength(this.value);
				updateStrengthMeter(score);
				checkMatch();
				if (this.value) {
					copyBtn.style.display = 'inline-flex';
				} else {
					copyBtn.style.display = 'none';
				}
			});

			confirmInput.addEventListener('input', checkMatch);

			// Form submission validation
			resetForm.addEventListener('submit', function(e) {
				if (pwdInput.value.length < 6) {
					e.preventDefault();
					alert('<?php echo esc_js( __( 'Password must be at least 6 characters long.', 'vconline' ) ); ?>');
					pwdInput.focus();
					return false;
				}
				if (pwdInput.value !== confirmInput.value) {
					e.preventDefault();
					alert('<?php echo esc_js( __( 'Passwords do not match. Please verify both fields.', 'vconline' ) ); ?>');
					confirmInput.focus();
					return false;
				}
			});
		})();
		</script>
	<?php endif; ?>
</div>

<?php do_action( 'tutor_after_reset_password_form' ); ?>
