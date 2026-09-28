<?php
/**
 * Frontend Password Reset Functionality for Tutor LMS Pro
 *
 * Implements a 100% frontend password reset workflow:
 * 1. Forgot password request form on frontend
 * 2. Tutor LMS branded HTML reset email with frontend reset link
 * 3. Frontend New Password page with manual entry and password generator
 * 4. Redirect to frontend login page after saving new password
 * 5. Complete prevention of WordPress admin dashboard/wp-login exposure
 *
 * @package VC_Online_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VCA_Frontend_Password_Reset {

	/**
	 * Flag to indicate when reset email is being dispatched
	 *
	 * @var bool
	 */
	public $is_sending_reset_email = false;

	/**
	 * Constructor: register all hooks
	 */
	public function __construct() {
		// 1. Redirect wp-login.php password reset actions to frontend
		add_action( 'login_init', array( $this, 'redirect_wp_login_actions' ), 1 );

		// 2. Filter lost password URLs across WordPress & Tutor LMS
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 999 );
		add_filter( 'tutor_lostpassword_url', array( $this, 'filter_lostpassword_url' ), 999 );

		// 3. Customize password reset email with Tutor LMS Pro HTML template & frontend link
		add_filter( 'retrieve_password_notification_email', array( $this, 'custom_password_reset_email' ), 999, 4 );
		add_filter( 'retrieve_password_message', array( $this, 'filter_retrieve_password_message' ), 999, 4 );

		// 4. Attach embedded logo image into PHPMailer for Gmail/Outlook compatibility
		add_action( 'phpmailer_init', array( $this, 'attach_embedded_logo' ) );

		// 5. Intercept password reset form submission (Save Password) before Tutor default handler
		add_action( 'tutor_action_tutor_process_reset_password', array( $this, 'handle_process_reset_password' ), 5 );

		// 6. Display success alert on frontend login page after password reset
		add_action( 'tutor_before_login_form', array( $this, 'display_login_success_banner' ) );

		// 7. Enqueue frontend password reset assets / styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_password_reset_assets' ) );
	}

	/**
	 * Get frontend login page URL
	 *
	 * @return string
	 */
	public static function get_login_url() {
		$login_page = get_page_by_path( 'tutor-login' );
		if ( $login_page ) {
			return get_permalink( $login_page->ID );
		}

		$tutor_login_page_id = tutor_utils()->get_option( 'tutor_login_page' );
		if ( $tutor_login_page_id && get_post( $tutor_login_page_id ) ) {
			return get_permalink( $tutor_login_page_id );
		}

		return home_url( '/tutor-login/' );
	}

	/**
	 * Get frontend password retrieve/reset URL
	 *
	 * @param string $reset_key Optional reset key.
	 * @param int    $user_id   Optional user ID.
	 * @return string
	 */
	public static function get_reset_url( $reset_key = '', $user_id = 0 ) {
		$url = trailingslashit( tutor_utils()->tutor_dashboard_url( 'retrieve-password' ) );

		if ( $reset_key && $user_id ) {
			$url = add_query_arg(
				array(
					'reset_key' => $reset_key,
					'user_id'   => (int) $user_id,
				),
				$url
			);
		}

		return $url;
	}

	/**
	 * Get site logo URL for email and templates
	 *
	 * @return string
	 */
	public static function get_site_logo_url() {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_src = wp_get_attachment_image_src( $custom_logo_id, 'full' );
			if ( ! empty( $logo_src[0] ) ) {
				return $logo_src[0];
			}
		}

		$tutor_logo = tutor_utils()->get_option( 'email_logo_src' );
		if ( $tutor_logo ) {
			return $tutor_logo;
		}

		// Fallback upload path if found
		$default_upload_logo = home_url( '/wp-content/uploads/2026/03/VC-LOGOS-ONLINE.webp' );
		return $default_upload_logo;
	}

	/**
	 * Redirect any wp-login.php password reset or lostpassword requests to frontend
	 */
	public function redirect_wp_login_actions() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		// Redirect wp-login.php?action=lostpassword
		if ( 'lostpassword' === $action ) {
			wp_safe_redirect( self::get_reset_url() );
			exit;
		}

		// Redirect wp-login.php?action=rp or action=resetpass
		if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
			$key   = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : '';
			$login = isset( $_REQUEST['login'] ) ? sanitize_user( wp_unslash( $_REQUEST['login'] ) ) : '';

			$user = $login ? get_user_by( 'login', $login ) : false;
			if ( ! $user && is_email( $login ) ) {
				$user = get_user_by( 'email', $login );
			}

			$user_id = $user ? $user->ID : 0;
			$redirect_url = self::get_reset_url( $key, $user_id );

			if ( ! $user_id && $login ) {
				$redirect_url = add_query_arg( 'login', rawurlencode( $login ), $redirect_url );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Filter lostpassword URL to ensure it always points to the frontend Tutor page
	 *
	 * @param string $url Default URL.
	 * @return string
	 */
	public function filter_lostpassword_url( $url ) {
		return self::get_reset_url();
	}

	/**
	 * Format the password reset notification email with Tutor LMS Pro HTML styling & frontend link
	 *
	 * @param array   $defaults   Notification email arguments (to, subject, message, headers).
	 * @param string  $key        The reset key.
	 * @param string  $user_login User login.
	 * @param WP_User $user_data  User data object.
	 * @return array
	 */
	public function custom_password_reset_email( $defaults, $key, $user_login, $user_data ) {
		if ( ! ( $user_data instanceof WP_User ) ) {
			$user_data = get_user_by( 'login', $user_login );
			if ( ! $user_data && is_email( $user_login ) ) {
				$user_data = get_user_by( 'email', $user_login );
			}
		}

		if ( ! ( $user_data instanceof WP_User ) ) {
			return $defaults;
		}

		$reset_url    = self::get_reset_url( $key, $user_data->ID );
		$site_name    = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$display_name = tutor_utils()->display_name( $user_data->ID );
		if ( empty( $display_name ) ) {
			$display_name = $user_data->first_name ? $user_data->first_name : $user_data->user_login;
		}

		// Email Subject
		$subject = sprintf( __( 'Password Reset Request for %s', 'vconline' ), $site_name );

		// Sender from Tutor settings or defaults
		$from_name    = tutor_utils()->get_option( 'email_from_name' ) ?: $site_name;
		$from_address = tutor_utils()->get_option( 'email_from_address' ) ?: get_option( 'admin_email' );

		// Build HTML message
		$message = $this->build_reset_email_html( array(
			'site_name'    => $site_name,
			'display_name' => $display_name,
			'username'     => $user_data->user_login,
			'reset_url'    => $reset_url,
			'logo_url'     => self::get_site_logo_url(),
		) );

		$this->is_sending_reset_email = true;

		$defaults['to']      = $user_data->user_email;
		$defaults['subject'] = $subject;
		$defaults['message'] = $message;
		$defaults['headers'] = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . sanitize_email( $from_address ) . '>',
		);

		return $defaults;
	}

	/**
	 * Attach inline CID logo image into PHPMailer so Gmail and other clients render it directly
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function attach_embedded_logo( $phpmailer ) {
		if ( $this->is_sending_reset_email ) {
			$logo_path = ABSPATH . 'wp-content/uploads/2026/04/VC-LOGOS-ONLINE-BLACK.png';
			if ( ! file_exists( $logo_path ) ) {
				$logo_path = ABSPATH . 'wp-content/uploads/2026/03/VC-LOGOS-ONLINE.webp';
			}
			if ( file_exists( $logo_path ) && method_exists( $phpmailer, 'AddEmbeddedImage' ) ) {
				$phpmailer->AddEmbeddedImage( $logo_path, 'vca_site_logo', 'vc-online-logo.png', 'base64', 'image/png' );
			}
			$this->is_sending_reset_email = false;
		}
	}

	/**
	 * Fallback filter for retrieve_password_message to replace wp-login URLs
	 *
	 * @param string  $message    Email message.
	 * @param string  $key        Reset key.
	 * @param string  $user_login User login.
	 * @param WP_User $user_data  User data object.
	 * @return string
	 */
	public function filter_retrieve_password_message( $message, $key, $user_login, $user_data = null ) {
		if ( ! $user_data ) {
			$user_data = get_user_by( 'login', $user_login );
			if ( ! $user_data && is_email( $user_login ) ) {
				$user_data = get_user_by( 'email', $user_login );
			}
		}

		if ( $user_data instanceof WP_User ) {
			$reset_url = self::get_reset_url( $key, $user_data->ID );
			// Replace any wp-login.php rp links with the frontend reset URL
			$message = preg_replace( '#https?://[^\s<>"]+action=rp[^\s<>"]*#i', $reset_url, $message );
		}

		return $message;
	}

	/**
	 * Build Tutor LMS branded HTML email template
	 *
	 * @param array $args Data arguments for email template.
	 * @return string HTML email content.
	 */
	private function build_reset_email_html( $args ) {
		$site_name    = esc_html( $args['site_name'] );
		$display_name = esc_html( $args['display_name'] );
		$username     = esc_html( $args['username'] );
		$reset_url    = esc_url( $args['reset_url'] );
		$logo_url     = esc_url( $args['logo_url'] );
		$year         = gmdate( 'Y' );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( sprintf( __( 'Password Reset - %s', 'vconline' ), $site_name ) ); ?></title>
	<style type="text/css">
		body {
			margin: 0;
			padding: 0;
			background-color: #f3f4f6;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			-webkit-font-smoothing: antialiased;
			color: #374151;
		}
		table {
			border-collapse: collapse;
		}
		.email-container {
			max-width: 600px;
			margin: 40px auto;
			background-color: #ffffff;
			border-radius: 12px;
			overflow: hidden;
			box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
			border: 1px solid #e5e7eb;
		}
		.email-header {
			background-color: #ffffff;
			padding: 32px 40px 24px;
			text-align: center;
			border-bottom: 1px solid #f0f2f5;
		}
		.email-header img {
			max-height: 48px;
			max-width: 220px;
			height: auto;
			display: inline-block;
		}
		.email-header .logo-text {
			font-size: 24px;
			font-weight: 700;
			color: #111827;
			text-decoration: none;
			letter-spacing: -0.5px;
		}
		.email-content {
			padding: 40px;
			line-height: 1.65;
		}
		.email-title {
			font-size: 22px;
			font-weight: 700;
			color: #111827;
			margin: 0 0 16px;
			letter-spacing: -0.3px;
		}
		.email-text {
			font-size: 15px;
			color: #4b5563;
			margin: 0 0 18px;
			line-height: 1.6;
		}
		.btn-wrap {
			text-align: center;
			margin: 32px 0;
		}
		.btn-primary {
			background-color: #000000 !important;
			color: #ffffff !important;
			padding: 14px 34px !important;
			font-size: 15px !important;
			font-weight: 600 !important;
			text-decoration: none !important;
			border-radius: 8px !important;
			display: inline-block !important;
			letter-spacing: 0.2px !important;
			border: 1px solid #000000 !important;
		}
		.btn-primary:hover {
			background-color: #262626 !important;
			border-color: #262626 !important;
		}
		.user-info-box {
			background-color: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			padding: 16px 20px;
			margin: 20px 0 24px;
			font-size: 14px;
			color: #334155;
		}
		.user-info-box p {
			margin: 0 0 4px;
		}
		.user-info-box p:last-child {
			margin-bottom: 0;
		}
		.link-fallback {
			font-size: 13px;
			color: #6b7280;
			word-break: break-all;
			background-color: #f9fafb;
			padding: 12px;
			border-radius: 6px;
			margin-top: 10px;
			border: 1px dashed #d1d5db;
		}
		.security-note {
			font-size: 13px;
			color: #6b7280;
			margin-top: 28px;
			padding-top: 20px;
			border-top: 1px solid #f0f2f5;
		}
		.email-footer {
			background-color: #fafbfc;
			padding: 24px 40px;
			text-align: center;
			font-size: 13px;
			color: #9ca3af;
			border-top: 1px solid #f0f2f5;
		}
	</style>
</head>
<body>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
		<tr>
			<td align="center" style="padding: 20px 10px;">
				<div class="email-container">
					<!-- Header -->
					<div class="email-header" style="background-color: #ffffff; padding: 28px 40px; text-align: center; border-bottom: 1px solid #f0f2f5;">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" style="display: inline-block; text-decoration: none;">
							<img src="cid:vca_site_logo" alt="<?php echo esc_attr( $site_name ); ?>" width="180" style="max-height: 48px; max-width: 220px; width: auto; height: auto; display: block; margin: 0 auto; border: 0; outline: none; text-decoration: none;" />
						</a>
					</div>

					<!-- Content -->
					<div class="email-content">
						<h2 class="email-title"><?php echo esc_html__( 'Password Reset Request', 'vconline' ); ?></h2>
						
						<p class="email-text">
							<?php echo sprintf( esc_html__( 'Hi %s,', 'vconline' ), '<strong>' . $display_name . '</strong>' ); ?>
						</p>

						<p class="email-text">
							<?php echo sprintf( esc_html__( 'We received a request to reset the password for your %s account. Click the button below to choose a new password:', 'vconline' ), '<strong>' . $site_name . '</strong>' ); ?>
						</p>

						<div class="user-info-box">
							<p><strong><?php esc_html_e( 'Account Username:', 'vconline' ); ?></strong> <?php echo $username; ?></p>
						</div>

						<div class="btn-wrap" style="text-align: center; margin: 32px 0;">
							<a href="<?php echo $reset_url; ?>" target="_blank" class="btn-primary" style="background-color: #000000 !important; color: #ffffff !important; padding: 14px 34px !important; font-size: 15px !important; font-weight: 600 !important; text-decoration: none !important; border-radius: 8px !important; display: inline-block !important; letter-spacing: 0.2px !important; border: 1px solid #000000 !important;">
								<?php esc_html_e( 'Set New Password', 'vconline' ); ?>
							</a>
						</div>

						<p class="email-text" style="font-size: 13px; color: #6b7280; margin-bottom: 6px;">
							<?php esc_html_e( 'If the button above does not work, copy and paste this link into your web browser:', 'vconline' ); ?>
						</p>
						<div class="link-fallback">
							<a href="<?php echo $reset_url; ?>" target="_blank" style="color: #000000; text-decoration: underline; font-weight: 500;"><?php echo esc_html( esc_url_raw( $args['reset_url'] ) ); ?></a>
						</div>

						<div class="security-note">
							<p style="margin: 0 0 6px;">
								<strong><?php esc_html_e( 'Security Notice:', 'vconline' ); ?></strong>
								<?php esc_html_e( 'This password reset link is valid for 24 hours. If you did not request a password reset, you can safely ignore this email. Your current password will remain unchanged and your account is secure.', 'vconline' ); ?>
							</p>
						</div>
					</div>

					<!-- Footer -->
					<div class="email-footer">
						<p style="margin: 0 0 4px;">&copy; <?php echo esc_html( $year . ' ' . $site_name ); ?>. <?php esc_html_e( 'All rights reserved.', 'vconline' ); ?></p>
						<p style="margin: 0;"><?php esc_html_e( 'This is an automated system notification.', 'vconline' ); ?></p>
					</div>
				</div>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Intercept reset password process, update password, and redirect directly to frontend login
	 */
	public function handle_process_reset_password() {
		tutils()->checking_nonce();

		$reset_key        = isset( $_POST['reset_key'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_key'] ) ) : '';
		$user_id          = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$password         = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '';

		// If user_id missing, check if login was passed
		if ( ! $user_id && ! empty( $_POST['user_login'] ) ) {
			$found_user = get_user_by( 'login', sanitize_user( wp_unslash( $_POST['user_login'] ) ) );
			if ( $found_user ) {
				$user_id = $found_user->ID;
			}
		}

		$user = $user_id ? get_user_by( 'ID', $user_id ) : false;

		if ( ! $user ) {
			tutor_flash_set( 'danger', __( 'User account could not be found.', 'vconline' ) );
			return false;
		}

		$check_user = check_password_reset_key( $reset_key, $user->user_login );

		if ( is_wp_error( $check_user ) ) {
			tutor_flash_set( 'danger', __( 'This password reset link is invalid or has expired. Please request a new one.', 'vconline' ) );
			return false;
		}

		if ( empty( $password ) ) {
			tutor_flash_set( 'danger', __( 'Please enter your new password.', 'vconline' ) );
			return false;
		}

		if ( strlen( $password ) < 6 ) {
			tutor_flash_set( 'danger', __( 'Password must be at least 6 characters long.', 'vconline' ) );
			return false;
		}

		if ( $password !== $confirm_password ) {
			tutor_flash_set( 'danger', __( 'Passwords do not match. Please verify and try again.', 'vconline' ) );
			return false;
		}

		// Successfully reset the password using WordPress core reset_password
		reset_password( $check_user, $password );

		do_action( 'tutor_user_reset_password', $check_user );

		// Make sure any active sessions or temp auth are cleared
		if ( is_user_logged_in() ) {
			wp_logout();
		}

		// Redirect directly to frontend login page with success indicator (Requirement 5 & 6)
		$login_url = self::get_login_url();
		$redirect_url = add_query_arg( 'password_reset', 'success', $login_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Display success alert on frontend login page after password reset
	 */
	public function display_login_success_banner() {
		if ( isset( $_GET['password_reset'] ) && 'success' === $_GET['password_reset'] ) {
			?>
			<div class="vca-password-reset-success-alert tutor-mb-24" style="background: #ecfdf5; border: 1px solid #a7f3d0; border-left: 5px solid #10b981; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px;">
				<div style="display: flex; align-items: flex-start; gap: 12px;">
					<span style="display: inline-flex; justify-content: center; align-items: center; width: 24px; height: 24px; background: #10b981; color: #ffffff; border-radius: 50%; font-size: 14px; font-weight: bold; flex-shrink: 0; margin-top: 1px;">✓</span>
					<div>
						<h4 style="margin: 0 0 4px; font-size: 15px; font-weight: 600; color: #065f46; line-height: 1.4;">
							<?php esc_html_e( 'Password Reset Complete!', 'vconline' ); ?>
						</h4>
						<p style="margin: 0; font-size: 14px; color: #047857; line-height: 1.5;">
							<?php esc_html_e( 'Your password has been changed successfully. You can now log in below using your new password.', 'vconline' ); ?>
						</p>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue password reset styles and frontend scripts
	 */
	public function enqueue_password_reset_assets() {
		// Only enqueue on retrieve-password page or login page
		$is_retrieve_password = false;
		global $wp_query;

		if ( isset( $wp_query->query_vars['tutor_dashboard_page'] ) && 'retrieve-password' === $wp_query->query_vars['tutor_dashboard_page'] ) {
			$is_retrieve_password = true;
		}

		if ( $is_retrieve_password || is_page( 'tutor-login' ) ) {
			wp_add_inline_style( 'child-style', self::get_inline_css() );
		}
	}

	/**
	 * Inline CSS for frontend password reset and generator styling
	 *
	 * @return string CSS rules
	 */
	public static function get_inline_css() {
		return '
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
		';
	}
}

// Initialize the Frontend Password Reset handler
new VCA_Frontend_Password_Reset();
