<?php
/**
 * Checkout Template - High-Converting Friction-Free Google Pay & UPI Funnel
 * Theme Override: wp-content/themes/vconline-child/tutor/ecommerce/checkout.php
 *
 * @package Tutor\Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Tutor\Ecommerce\CheckoutController;
use Tutor\Ecommerce\CartController;
use Tutor\GDPR\Controllers\LegalConsent;
use TUTOR\Input;

$user_id = apply_filters( 'tutor_checkout_user_id', get_current_user_id() );

$tutor_toc_page_link     = tutor_utils()->get_toc_page_link();
$tutor_privacy_page_link = tutor_utils()->get_privacy_page_link();

$cart_controller = new CartController();
$get_cart        = $cart_controller->get_cart_items();
$courses         = $get_cart['courses'];
$total_count     = $courses['total_count'];
$course_list     = $courses['results'];
$subtotal        = 0;
$course_ids      = implode( ', ', array_values( array_column( $course_list, 'ID' ) ) );
$plan_id         = Input::get( 'plan', 0, Input::TYPE_INT );

// Automatic recovery: If course_id is missing and cart is empty, restore from cookie
$req_course_id = (int) Input::get( 'course_id', 0 );
if ( ! $req_course_id && 0 === (int) $total_count && ! empty( $_COOKIE['vco_last_checkout_course_id'] ) ) {
	$saved_course_id = (int) $_COOKIE['vco_last_checkout_course_id'];
	if ( $saved_course_id > 0 && 'publish' === get_post_status( $saved_course_id ) ) {
		$target_url = add_query_arg( 'course_id', $saved_course_id, tutor_utils()->get_current_url() );
		?>
		<script>
			window.location.replace( <?php echo wp_json_encode( $target_url ); ?> );
		</script>
		<?php
		return;
	}
}

$is_checkout_page = true;
$gpay_svg_file    = get_stylesheet_directory() . '/images/google-pay-mark.svg';
$gpay_svg_url     = get_stylesheet_directory_uri() . '/images/google-pay-mark.svg?v=' . ( file_exists( $gpay_svg_file ) ? filemtime( $gpay_svg_file ) : '1.1' );
?>

<div class="tutor-checkout-page vco-checkout-funnel-wrapper">
	<!-- Minimal Distraction-Free Header Bar -->
	<div class="vco-checkout-header-bar">
		<div class="tutor-container">
			<div class="vco-header-inner">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vco-checkout-logo-link" title="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<?php
					$custom_logo_id = get_theme_mod( 'custom_logo' );
					$logo_src       = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
					if ( $logo_src ) :
						?>
						<img src="<?php echo esc_url( $logo_src ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="vco-checkout-logo-img">
					<?php else : ?>
						<span class="vco-logo-text">VC <span>ONLINE</span></span>
					<?php endif; ?>
				</a>
			</div>
		</div>
	</div>

	<div class="tutor-container vco-main-checkout-container">
		<div class="tutor-checkout-container">
			<?php
			$echo_before_return    = true;
			$user_has_subscription = apply_filters( 'tutor_checkout_user_has_subscription', false, $plan_id, $echo_before_return );
			if ( $user_has_subscription ) {
				return;
			}
			?>

			<!-- Nonce Failure Alert -->
			<?php
			$nonce_alert = get_transient( CheckoutController::PAY_NOW_ALERT_MSG_TRANSIENT_KEY . 'pay_now_nonce_alert' );
			if ( $nonce_alert ) {
				?>
				<div class="tutor-alert tutor-danger vco-alert-box">
					<div class="tutor-color-danger"><?php echo esc_html( $nonce_alert[0] ); ?></div>
				</div>
				<?php
				delete_transient( CheckoutController::PAY_NOW_ALERT_MSG_TRANSIENT_KEY . 'pay_now_nonce_alert' );
			}
			?>

			<form method="post" action="<?php echo esc_url( tutor_utils()->get_current_url() ); ?>" id="tutor-checkout-form" class="vco-checkout-form">
				<?php tutor_nonce_field(); ?>
				<input type="hidden" name="tutor_action" value="tutor_pay_now">
				<input type="hidden" name="payment_type" value="automate">
				<input type="hidden" name="payment_method" value="razorpay" id="vco-payment-method-input">
				<input type="hidden" name="vco_payment_intent" id="vco-payment-intent" value="gpay">

				<div class="tutor-row tutor-g-4 vco-checkout-grid">
					
					<!-- Left Column: Order Summary & Course Card -->
					<div class="tutor-col-lg-6 tutor-col-12 vco-left-col" tutor-checkout-details>
						<div class="vco-card vco-order-card">
							<?php
							$details_file = tutor()->path . 'templates/ecommerce/checkout-details.php';
							if ( file_exists( $details_file ) ) {
								include $details_file;
							}
							?>

							<!-- Trust Badges Strip -->
							<div class="vco-trust-badges-grid">
								<div class="vco-trust-badge-item">
									<div class="vco-badge-icon">⚡</div>
									<div class="vco-badge-text">
										<strong>Instant Access</strong>
										<span>Start learning immediately</span>
									</div>
								</div>
								<div class="vco-trust-badge-item">
									<div class="vco-badge-icon">📜</div>
									<div class="vco-badge-text">
										<strong>Official Certificate</strong>
										<span>Included upon completion</span>
									</div>
								</div>
								<div class="vco-trust-badge-item">
									<div class="vco-badge-icon">📱</div>
									<div class="vco-badge-text">
										<strong>Lifetime Access</strong>
										<span>Watch on mobile & laptop</span>
									</div>
								</div>
								<div class="vco-trust-badge-item">
									<div class="vco-badge-icon">🔒</div>
									<div class="vco-badge-text">
										<strong>Secure Checkout</strong>
										<span>Verified by Razorpay & UPI</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Right Column: Simplified Details & Google Pay CTA -->
					<div class="tutor-col-lg-6 tutor-col-12 vco-right-col">
						<div class="vco-card vco-payment-card">
							
							<!-- Login Prompt if Guest -->
							<?php if ( ! is_user_logged_in() ) : ?>
								<?php $login_url = tutor_utils()->get_option( 'enable_tutor_native_login', null ) ? '' : wp_login_url( tutor()->current_url ); ?>
								<div class="vco-login-banner">
									<span>Already have a VC Online account?</span>
									<button type="button" class="vco-link-btn tutor-open-login-modal" data-login_url="<?php echo esc_url( $login_url ); ?>">
										Log In
									</button>
								</div>
							<?php endif; ?>

							<div class="vco-card-header">
								<div class="vco-step-indicator">
									<span class="vco-step-num">1</span>
									<h2 class="vco-section-title">Your Details</h2>
								</div>
								<span class="vco-privacy-hint"><i class="tutor-icon-lock-bold"></i> Kept private & safe</span>
							</div>

							<!-- Simplified 3-Field Billing Area -->
							<div class="tutor-billing-fields vco-billing-wrapper">
								<?php
								$child_billing_file = __DIR__ . '/checkout-billing-form-fields.php';
								if ( file_exists( $child_billing_file ) ) {
									require $child_billing_file;
								} else {
									require tutor()->path . 'templates/ecommerce/checkout-billing-form-fields.php';
								}
								?>
							</div>

							<!-- Payment Section -->
							<div class="vco-payment-action-section">
								<div class="vco-step-indicator vco-mt-24">
									<span class="vco-step-num">2</span>
									<h2 class="vco-section-title">Complete Payment</h2>
								</div>

								<!-- Terms & Conditions Consent -->
								<?php
								$consents = LegalConsent::get_consent_by_display_key( LegalConsent::DISPLAY_ON_CHECKOUT );
								if ( tutor_utils()->count( $consents ) ) :
									foreach ( $consents as $consent ) :
										LegalConsent::render_consent_field( $consent, 'tutor-mt-16 vco-consent-wrap' );
									endforeach;
								elseif ( null !== $tutor_toc_page_link ) :
									?>
									<div class="tutor-mt-16 vco-consent-wrap">
										<label class="vco-terms-label" for="tutor_checkout_agree_to_terms">
											<input type="checkbox" id="tutor_checkout_agree_to_terms" name="agree_to_terms" class="tutor-form-check-input" checked required>
											<span>
												I agree to the <a target="_blank" href="<?php echo esc_url( $tutor_toc_page_link ); ?>">Terms of Service</a>
												<?php if ( null !== $tutor_privacy_page_link ) : ?>
													&amp; <a target="_blank" href="<?php echo esc_url( $tutor_privacy_page_link ); ?>">Privacy Policy</a>
												<?php endif; ?>
											</span>
										</label>
									</div>
								<?php endif; ?>

								<!-- Backend Error Handling -->
								<?php
								$pay_now_errors    = get_transient( CheckoutController::PAY_NOW_ERROR_TRANSIENT_KEY . $user_id );
								$pay_now_alert_msg = get_transient( CheckoutController::PAY_NOW_ALERT_MSG_TRANSIENT_KEY . $user_id );

								delete_transient( CheckoutController::PAY_NOW_ALERT_MSG_TRANSIENT_KEY . $user_id );
								delete_transient( CheckoutController::PAY_NOW_ERROR_TRANSIENT_KEY . $user_id );
								if ( $pay_now_errors || $pay_now_alert_msg ) :
									?>
									<div class="tutor-break-word tutor-mt-16">
										<?php if ( ! empty( $pay_now_alert_msg ) ) :
											list( $alert, $message ) = array_values( $pay_now_alert_msg );
											?>
											<div class="tutor-alert tutor-<?php echo esc_attr( $alert ); ?>">
												<div class="tutor-color-<?php echo esc_attr( $alert ); ?>"><?php echo esc_html( $message ); ?></div>
											</div>
										<?php endif; ?>

										<?php if ( is_array( $pay_now_errors ) && count( $pay_now_errors ) ) : ?>
											<div class="tutor-alert tutor-danger">
												<ul class="tutor-mb-0">
													<?php foreach ( $pay_now_errors as $pay_now_err ) : ?>
														<li class="tutor-color-danger"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $pay_now_err ) ) ); ?></li>
													<?php endforeach; ?>
												</ul>
											</div>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<!-- PRIMARY GOOGLE PAY CTA BUTTON -->
								<?php
								$total_amount_formatted = isset( $checkout_data->total_price ) ? tutor_get_formatted_price( $checkout_data->total_price ) : '₹990.00';
								$enable_pay_now_btn     = apply_filters( 'tutor_checkout_enable_pay_now_btn', true, $checkout_data ?? null );
								?>
								<div class="vco-cta-container tutor-mt-20">
									<button
										type="button"
										<?php echo $enable_pay_now_btn ? '' : 'disabled'; ?>
										id="tutor-checkout-pay-now-button"
										class="vco-gpay-btn"
										onclick="triggerVcoPayment('gpay', event)"
									>
										<span class="vco-gpay-btn-label">Pay <span class="vco-dynamic-price"><?php echo esc_html( $total_amount_formatted ); ?></span> with</span>
										<span class="vco-gpay-brand">
											<svg class="vco-g-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
												<path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
												<path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.35 24 12 24z"/>
												<path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.14-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
												<path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.35 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
											</svg>
											<span class="vco-gpay-text">Pay</span>
										</span>
									</button>

									<!-- Secondary Option: Pay with Cards, Netbanking & Others -->
									<button
										type="button"
										class="vco-other-payment-btn"
										id="vco-other-payment-btn"
										onclick="triggerVcoPayment('cards', event)"
									>
										<span class="vco-other-icon">💳</span>
										<span>Pay with Cards, Netbanking &amp; Other Options</span>
									</button>
								</div>

								<!-- Razorpay Trust & Security Guarantee -->
								<div class="vco-security-footer">
									<div class="vco-sec-text">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
										<span>Guaranteed Safe &amp; Secure 256-Bit SSL Checkout</span>
									</div>
									<div class="vco-powered-by">
										<span>Processed securely via</span>
										<span class="vco-razorpay-badge">
											<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
												<path d="M14.2587 10.2861L10.9889 22.8019H3.00049L4.6203 16.6446L14.2587 10.2861Z" fill="#072654"/>
												<path d="M21.5122 1.2002L15.8378 22.801H11.9442L15.7774 8.17242L9.90186 12.0459L10.9482 8.19255L21.5122 1.2002Z" fill="#3395FF"/>
											</svg>
											<strong>Razorpay</strong>
										</span>
									</div>
								</div>

							</div>

						</div>
					</div>

				</div>
			</form>
		</div>
	</div>

	<!-- Mobile Sticky Bottom Bar (< 768px) -->
	<div class="vco-mobile-sticky-bar" id="vco-mobile-sticky-bar">
		<div class="vco-sticky-inner">
			<div class="vco-sticky-price-wrap">
				<span class="vco-sticky-label">Total to Pay</span>
				<span class="vco-sticky-price vco-dynamic-price"><?php echo esc_html( $total_amount_formatted ); ?></span>
			</div>
			<button type="button" class="vco-sticky-cta-btn" id="vco-sticky-trigger" onclick="triggerVcoPayment('gpay', event)">
				<span>Pay with</span>
				<span class="vco-gpay-brand">
					<svg class="vco-g-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
						<path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
						<path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.35 24 12 24z"/>
						<path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.14-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
						<path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.35 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
					</svg>
					<span class="vco-gpay-text">Pay</span>
				</span>
			</button>
		</div>
	</div>

	<!-- Minimal Distraction-Free Footer -->
	<footer class="vco-checkout-footer-minimal">
		<div class="tutor-container">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> VC Online. All rights reserved. &bull; Secure Digital Learning Platform</p>
		</div>
	</footer>
</div>

<?php
if ( ! is_user_logged_in() ) {
	tutor_load_template_from_custom_path( tutor()->path . '/views/modal/login.php' );
}
?>

<script>
function triggerVcoPayment(intent, e) {
	if (e && e.preventDefault) {
		e.preventDefault();
	}

	var form = document.getElementById('tutor-checkout-form');
	if (!form) return;

	var intentInput = document.getElementById('vco-payment-intent');
	if (intentInput) {
		intentInput.value = intent;
	}

	// Also attach query parameter to form action
	try {
		var actionUrl = new URL(form.action || window.location.href);
		actionUrl.searchParams.set('vco_payment_intent', intent);
		form.action = actionUrl.toString();
	} catch (err) {}

	// Native HTML5 field validation
	if (form.reportValidity && !form.reportValidity()) {
		return;
	}

	var payBtn = document.getElementById('tutor-checkout-pay-now-button');
	var otherBtn = document.getElementById('vco-other-payment-btn');
	var stickyBtn = document.getElementById('vco-sticky-trigger');

	if (intent === 'cards' && otherBtn) {
		otherBtn.classList.add('is-loading');
	} else {
		if (payBtn) payBtn.classList.add('is-loading');
		if (stickyBtn) stickyBtn.classList.add('is-loading');
	}

	form.submit();
}

document.addEventListener('DOMContentLoaded', function() {
	// Sync last course_id into cookie so it can be restored if user navigates back
	var urlParams = new URLSearchParams(window.location.search);
	var courseId = urlParams.get('course_id');
	if (courseId) {
		document.cookie = "vco_last_checkout_course_id=" + encodeURIComponent(courseId) + "; path=/; max-age=86400; SameSite=Lax";
	}
});
</script>
