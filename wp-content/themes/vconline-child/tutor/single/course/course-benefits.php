<?php
/**
 * Template for displaying course benefits
 *
 * @package Tutor\Templates
 * @subpackage Single\Course
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 1.0.0
 */

do_action( 'tutor_course/single/before/benefits' );

$course_id    = get_the_ID();
$raw_benefits = get_post_meta( $course_id, '_tutor_course_benefits', true );

if ( empty( $raw_benefits ) && ! is_numeric( $raw_benefits ) ) {
	return;
}

// Check if content contains HTML tags (entered through rich editor)
$has_html = ( $raw_benefits !== strip_tags( $raw_benefits ) );
?>

<div class="tutor-course-details-widget tutor-course-details-widget-col-2 tutor-mt-lg-50 tutor-mt-32">
	<h3 class="tutor-course-details-widget-title tutor-fs-5 tutor-fw-bold tutor-color-black tutor-mb-16">
		<?php echo esc_html( apply_filters( 'tutor_course_benefit_title', __( 'What Will You Learn?', 'tutor' ) ) ); ?>
	</h3>

	<?php if ( $has_html ) : ?>
		<div class="tutor-course-benefits-content tutor-fs-6 tutor-color-secondary tutor-mt-16">
			<?php echo apply_filters( 'the_content', $raw_benefits ); ?>
		</div>
	<?php else : ?>
		<?php
		$course_benefits = tutor_course_benefits( $course_id );
		if ( is_array( $course_benefits ) && count( $course_benefits ) ) :
		?>
			<ul class="tutor-course-details-widget-list tutor-color-black tutor-fs-6 tutor-m-0 tutor-mt-16">
				<?php foreach ( $course_benefits as $benefit ) : ?>
					<li class="tutor-d-flex tutor-mb-12">
						<span class="tutor-icon-bullet-point tutor-color-muted tutor-mt-2 tutor-mr-8 tutor-fs-8" aria-hidden="true"></span>
						<span><?php echo esc_html( $benefit ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php do_action( 'tutor_course/single/after/benefits' ); ?>
