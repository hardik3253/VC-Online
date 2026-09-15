<?php
/**
 * Simplified Billing Form Fields (Friction-Free Checkout)
 * Theme Override: wp-content/themes/vconline-child/tutor/ecommerce/checkout-billing-form-fields.php
 *
 * Captures only the 3 essential conversion fields:
 * 1. Name (First & Last)
 * 2. Mobile Phone (+91 with numeric keypad)
 * 3. Email Address
 *
 * Passes India defaults as hidden fields to satisfy Tutor LMS & Razorpay backend requirements.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Tutor\Ecommerce\BillingController;

$billing_controller = new BillingController( false );
$billing_info       = $billing_controller->get_billing_info();

$billing_first_name = $billing_info->billing_first_name ?? tutor_utils()->input_old( 'billing_first_name', '' );
$billing_last_name  = $billing_info->billing_last_name ?? tutor_utils()->input_old( 'billing_last_name', '' );
$billing_email      = $billing_info->billing_email ?? tutor_utils()->input_old( 'billing_email', '' );
$billing_phone      = $billing_info->billing_phone ?? tutor_utils()->input_old( 'billing_phone', '' );

// Auto-fill from logged in user if available
if ( is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	if ( empty( $billing_first_name ) ) {
		$billing_first_name = ! empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;
	}
	if ( empty( $billing_last_name ) ) {
		$billing_last_name = $current_user->last_name;
	}
	if ( empty( $billing_email ) ) {
		$billing_email = $current_user->user_email;
	}
}

// Clean phone if it starts with +91 or 91
if ( ! empty( $billing_phone ) ) {
	$billing_phone = preg_replace( '/^\+?91/', '', trim( $billing_phone ) );
}
?>

<div class="vco-simplified-billing-fields">
	<!-- Row 1: Name Fields -->
	<div class="vco-form-row">
		<div class="vco-form-group vco-col-6">
			<label class="vco-field-label" for="vco_billing_first_name">
				<?php esc_html_e( 'First Name', 'tutor' ); ?> <span class="vco-req">*</span>
			</label>
			<div class="vco-input-wrapper">
				<input
					class="vco-form-control tutor-form-control"
					type="text"
					id="vco_billing_first_name"
					name="billing_first_name"
					placeholder="<?php esc_attr_e( 'First name', 'tutor' ); ?>"
					value="<?php echo esc_attr( $billing_first_name ); ?>"
					autocomplete="given-name"
					required
				>
			</div>
		</div>

		<div class="vco-form-group vco-col-6">
			<label class="vco-field-label" for="vco_billing_last_name">
				<?php esc_html_e( 'Last Name', 'tutor' ); ?> <span class="vco-req">*</span>
			</label>
			<div class="vco-input-wrapper">
				<input
					class="vco-form-control tutor-form-control"
					type="text"
					id="vco_billing_last_name"
					name="billing_last_name"
					placeholder="<?php esc_attr_e( 'Last name', 'tutor' ); ?>"
					value="<?php echo esc_attr( $billing_last_name ); ?>"
					autocomplete="family-name"
					required
				>
			</div>
		</div>
	</div>

	<!-- Row 2: Mobile Number with +91 prefix -->
	<div class="vco-form-group">
		<label class="vco-field-label" for="vco_billing_phone">
			<?php esc_html_e( 'WhatsApp / Mobile Number', 'tutor' ); ?> <span class="vco-req">*</span>
			<span class="vco-field-hint"><?php esc_html_e( '(For instant course access & updates)', 'tutor' ); ?></span>
		</label>
		<div class="vco-phone-input-wrap">
			<span class="vco-phone-prefix">
				<span class="vco-flag">🇮🇳</span> +91
			</span>
			<input
				class="vco-form-control vco-phone-control tutor-form-control"
				type="tel"
				id="vco_billing_phone"
				name="billing_phone"
				inputmode="numeric"
				pattern="[0-9]{10}"
				maxlength="10"
				placeholder="<?php esc_attr_e( '10-digit mobile number', 'tutor' ); ?>"
				value="<?php echo esc_attr( $billing_phone ); ?>"
				autocomplete="tel-national"
				required
			>
		</div>
	</div>

	<!-- Row 3: Email Address -->
	<div class="vco-form-group">
		<label class="vco-field-label" for="vco_billing_email">
			<?php esc_html_e( 'Email Address', 'tutor' ); ?> <span class="vco-req">*</span>
			<span class="vco-field-hint"><?php esc_html_e( '(Your login & invoices will be sent here)', 'tutor' ); ?></span>
		</label>
		<div class="vco-input-wrapper">
			<input
				class="vco-form-control tutor-form-control"
				type="email"
				id="vco_billing_email"
				name="billing_email"
				placeholder="<?php esc_attr_e( 'you@example.com', 'tutor' ); ?>"
				value="<?php echo esc_attr( $billing_email ); ?>"
				autocomplete="email"
				required
			>
		</div>
	</div>

	<!-- Hidden default billing fields required by Tutor LMS & Razorpay -->
	<input type="hidden" name="billing_country" value="India">
	<input type="hidden" name="billing_state" value="Maharashtra">
	<input type="hidden" name="billing_city" value="Mumbai">
	<input type="hidden" name="billing_zip_code" value="400001">
	<input type="hidden" name="billing_address" value="Online Course Access">
</div>
