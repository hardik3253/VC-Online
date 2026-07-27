<?php
/**
 * Tutor LMS Custom Course Pricing and Countdown Timer Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Enqueue scripts and styles for the custom pricing section
 */
add_action( 'wp_enqueue_scripts', 'vca_enqueue_course_pricing_assets' );
function vca_enqueue_course_pricing_assets() {
	if ( ! is_singular( 'courses' ) ) {
		return;
	}

	// Register and enqueue CSS
	wp_enqueue_style(
		'vca-course-pricing-style',
		get_stylesheet_directory_uri() . '/css/course-pricing.css',
		array(),
		'1.0.0'
	);

	// Register and enqueue JS
	wp_enqueue_script(
		'vca-course-pricing-script',
		get_stylesheet_directory_uri() . '/js/course-pricing.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);

	// Retrieve current WooCommerce product for the course
	$course_id = get_the_ID();
	$product_id = tutor_utils()->get_course_product_id( $course_id );
	
	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	$product    = wc_get_product( $product_id );
	if ( $product ) {
		$regular_price = (float) $product->get_regular_price();
		$sale_price    = (float) $product->get_sale_price();
		$is_on_sale    = $product->is_on_sale() && $sale_price > 0 && $sale_price < $regular_price;

		// Pass pricing context variables to JavaScript
		wp_localize_script( 'vca-course-pricing-script', 'vcaCoursePricing', array(
			'courseId'     => $course_id,
			'productId'    => $product_id,
			'regularPrice' => $regular_price,
			'salePrice'    => $sale_price,
			'isOnSale'     => $is_on_sale,
		) );
	}
}

/**
 * Hook after the Tutor LMS WooCommerce pricing block to inject custom containers
 */
add_action( 'tutor_after_course_details_wc_cart_price', 'vca_inject_pricing_extras', 10, 2 );
function vca_inject_pricing_extras( $product, $course_id ) {
	if ( ! $product ) {
		return;
	}

	$regular_price = (float) $product->get_regular_price();
	$sale_price    = (float) $product->get_sale_price();
	$is_on_sale    = $product->is_on_sale() && $sale_price > 0 && $sale_price < $regular_price;

	// Render pricing extras wrapper
	?>
	<div class="vco-pricing-extras" data-course-id="<?php echo esc_attr( $course_id ); ?>">
		<?php if ( $is_on_sale ) : ?>
			<!-- Countdown Timer -->
			<div class="vco-countdown-wrapper tutor-mt-16">
				<span class="vco-countdown-label">
					<span class="tutor-icon-clock tutor-mr-8"></span>24 hours left at this price
				</span>
				<div class="vco-countdown-timer" id="vco-countdown-<?php echo esc_attr( $course_id ); ?>">24:00:00</div>
			</div>
		<?php endif; ?>

		<!-- Coupon Notice UI -->
		<div class="vco-coupon-notice tutor-mt-12">
			<div class="vco-coupon-left">
				<span class="vco-coupon-icon">✓</span>
				<span class="vco-coupon-code">VCWELCOME</span>
			</div>
			<span class="vco-coupon-applied-text">Applied!</span>
		</div>
	</div>
	<?php
}

/**
 * Auto-apply welcome coupon on Tutor LMS native checkout pages if eligible
 */
add_filter( 'tutor_checkout_coupon_code', 'vca_auto_apply_tutor_coupon', 10, 3 );
function vca_auto_apply_tutor_coupon( $coupon_code, $order_type, $item_ids ) {
	// If the coupon was manually removed, do not re-apply
	if ( '-1' === $coupon_code ) {
		return $coupon_code;
	}

	// If another coupon code is already manually entered, respect it
	if ( ! empty( $coupon_code ) ) {
		return $coupon_code;
	}

	// Check eligibility: cookie exists OR item is natively/WC on sale
	$is_eligible = ! empty( $_COOKIE['vco_eligible_welcome_offer'] );

	if ( ! $is_eligible && ! empty( $item_ids ) ) {
		foreach ( $item_ids as $course_id ) {
			// Check Tutor native sale price first
			$native_price = (float) get_post_meta( $course_id, '_tutor_course_price', true );
			$native_sale = (float) get_post_meta( $course_id, '_tutor_course_sale_price', true );
			if ( $native_sale > 0 && $native_sale < $native_price ) {
				$is_eligible = true;
				break;
			}

			// Check WooCommerce sale price fallback
			$product_id = tutor_utils()->get_course_product_id( $course_id );
			if ( $product_id && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product && $product->is_on_sale() ) {
					$is_eligible = true;
					break;
				}
			}
		}
	}

	if ( $is_eligible ) {
		return 'VCWELCOME';
	}

	return $coupon_code;
}
