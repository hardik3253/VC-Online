<?php
/**
 * Order order placement failed template
 * Theme Override: wp-content/themes/vconline-child/tutor/ecommerce/order-placement-failed.php
 *
 * @package Tutor\templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.0.0
 */

use Tutor\Ecommerce\CheckoutController;
use TUTOR\Input;

tutor_utils()->tutor_custom_header();

$order_status = isset( $order_status ) ? $order_status : 'failed';
$order_id     = isset( $order_id ) ? (int) $order_id : Input::get( 'order_id', 0, Input::TYPE_INT );
$error_msg    = Input::get( 'error_message' );

// Retrieve course_id to guarantee "Back to Checkout" never displays price as 0
$course_id = Input::get( 'course_id', 0, Input::TYPE_INT );
if ( ! $course_id && $order_id ) {
	if ( class_exists( '\Tutor\Models\OrderModel' ) ) {
		$order_model = new \Tutor\Models\OrderModel();
		if ( method_exists( $order_model, 'get_order_items_by_id' ) ) {
			$order_items = $order_model->get_order_items_by_id( (int) $order_id );
			if ( ! empty( $order_items ) && isset( $order_items[0]->item_id ) ) {
				$course_id = (int) $order_items[0]->item_id;
			}
		}
	}
}
if ( ! $course_id && ! empty( $_COOKIE['vco_last_checkout_course_id'] ) ) {
	$course_id = (int) $_COOKIE['vco_last_checkout_course_id'];
}

$checkout_base_url = CheckoutController::get_page_url();
if ( $course_id ) {
	$back_url = add_query_arg( 'course_id', $course_id, $checkout_base_url );
} else {
	$back_url = wp_get_referer() ? wp_get_referer() : $checkout_base_url;
}
?>

<div class="tutor-container">
	<div class="tutor-order-status-wrapper" style="padding: 60px 20px; text-align: center;">
		<div class="tutor-d-flex tutor-flex-column tutor-align-center tutor-gap-4">
			<div class="tutor-order-status-icon">
				<img src="<?php echo esc_attr( tutor()->url . 'assets/images/orders/payment-failed.svg' ); ?>" alt="<?php esc_html_e( 'payment failed', 'tutor' ); ?>">
			</div>

			<div class="tutor-order-status-content">
				<h2 class="tutor-fs-4 tutor-fw-medium tutor-color-black tutor-mb-4">
					<?php esc_html_e( 'Payment cancelled or incomplete', 'tutor' ); ?>
				</h2>
				<p class="tutor-fs-6 tutor-color-secondary tutor-mb-0">
					<?php echo $error_msg ? esc_html( $error_msg ) : esc_html__( 'Your payment was not completed. Click below to return to checkout and complete your enrollment.', 'tutor' ); ?>
				</p>
			</div>
		</div>

		<div class="tutor-order-status-actions" style="margin-top: 24px;">
			<a href="<?php echo esc_url( $back_url ); ?>" class="tutor-btn tutor-btn-primary" style="padding: 12px 28px; font-weight: 600; font-size: 15px; border-radius: 8px;">
				<?php esc_html_e( 'Back to Checkout', 'tutor' ); ?>
			</a>
		</div>
	</div>
</div>
<?php tutor_utils()->tutor_custom_footer(); ?>
