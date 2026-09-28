<?php
/**
 * Frontend Password Retrieve Template Override
 *
 * @package VC_Online_Child
 * @subpackage Tutor_Templates
 */

defined( 'ABSPATH' ) || exit;

use TUTOR\Input;

$reset_key  = Input::get( 'reset_key' ) ? Input::get( 'reset_key' ) : Input::get( 'key' );
$user_id    = Input::get( 'user_id', 0, Input::TYPE_INT );
$user_login = Input::get( 'login' );

// If login passed without user_id, resolve user_id
if ( ! $user_id && $user_login ) {
	$user = get_user_by( 'login', sanitize_user( wp_unslash( $user_login ) ) );
	if ( ! $user && is_email( $user_login ) ) {
		$user = get_user_by( 'email', sanitize_user( wp_unslash( $user_login ) ) );
	}
	if ( $user ) {
		$user_id = $user->ID;
	}
}

if ( $reset_key && $user_id ) {
	tutor_load_template( 'template-part.form-retrieve-password' );
} else {
	do_action( 'tutor_before_retrieve_password_form' );
	$login_url = class_exists( 'VCA_Frontend_Password_Reset' ) ? VCA_Frontend_Password_Reset::get_login_url() : home_url( '/tutor-login/' );
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
		.vca-password-card button.vca-submit-btn {
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
		.vca-password-card button.vca-submit-btn:hover {
			background-color: #262626 !important;
			border-color: #262626 !important;
			color: #ffffff !important;
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
		<div class="vca-card-header">
			<div class="vca-card-icon">
				<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
					<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
				</svg>
			</div>
			<h2 class="vca-card-title"><?php esc_html_e( 'Forgot Password?', 'vconline' ); ?></h2>
			<p class="vca-card-desc">
				<?php echo apply_filters( 'tutor_lost_password_message', esc_html__( 'Please enter your username or email address. You will receive a secure link to create a new password via email.', 'vconline' ) ); ?>
			</p>
		</div>

		<form method="post" class="tutor-forgot-password-form tutor-ResetPassword lost_reset_password">
			<div class="tutor-mb-16">
				<?php tutor_alert( null, 'any', true, true ); ?>
			</div>

			<?php tutor_nonce_field(); ?>
			<input type="hidden" name="tutor_action" value="tutor_retrieve_password">

			<div class="vca-form-group">
				<label class="vca-label" for="user_login">
					<?php esc_html_e( 'Username or Email Address', 'vconline' ); ?>
				</label>
				<div class="vca-input-wrap">
					<input class="vca-input" type="text" name="user_login" id="user_login" autocomplete="username" placeholder="<?php esc_attr_e( 'Enter your username or email', 'vconline' ); ?>" required>
				</div>
			</div>

			<?php do_action( 'tutor_lostpassword_form' ); ?>

			<div class="vca-form-group" style="margin-bottom: 0;">
				<button type="submit" class="vca-submit-btn" value="<?php esc_attr_e( 'Reset password', 'vconline' ); ?>">
					<?php esc_html_e( 'Reset password', 'vconline' ); ?>
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
	</div>
	<?php
	do_action( 'tutor_after_retrieve_password_form' );
}
