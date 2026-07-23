<?php
/**
 * Overridden Elementor Ratings template part to inject the Custom Badge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$course_id = get_the_ID();
$enable_badge = get_post_meta( $course_id, '_tutor_course_enable_badge', true );
$badge_text   = get_post_meta( $course_id, '_tutor_course_badge_text', true );
$text_color   = get_post_meta( $course_id, '_tutor_course_badge_text_color', true );
$bg_color     = get_post_meta( $course_id, '_tutor_course_badge_bg_color', true );

if ( 'yes' === $enable_badge && ! empty( $badge_text ) ) {
	$text_color = $text_color ? $text_color : '#ffffff';
	$bg_color   = $bg_color ? $bg_color : '#E03E3E';
	echo '<span class="tutor-course-custom-badge" style="color: ' . esc_attr( $text_color ) . '; background: ' . esc_attr( $bg_color ) . ';">' . esc_html( $badge_text ) . '</span>';
}

// Load the original ratings template
$original_template = ETLMS_DIR_PATH . 'templates/course/list/parts/ratings.php';
if ( file_exists( $original_template ) ) {
	include $original_template;
}
