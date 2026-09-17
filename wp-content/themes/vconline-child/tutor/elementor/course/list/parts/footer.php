<?php
/**
 * Overridden Elementor Footer template part to ensure Course Price and action button are displayed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$course_id = get_the_ID();
?>
<div class="tutor-card-footer">
	<?php
	$monitize_by    = tutor_utils()->get_option( 'monetize_by' );
	$is_purchasable = tutor_utils()->is_course_purchasable();
	if ( 'edd' === $monitize_by && $is_purchasable ) {
		ob_start();
		tutor_load_template( 'single.course.add-to-cart-edd' );
		echo apply_filters( 'tutor/course/single/entry-box/purchasable', ob_get_clean(), $course_id );
	} else {
		tutor_course_loop_price();
	}
	do_action( 'tutor_course_loop_footer_bottom', $course_id );
	?>
</div>
