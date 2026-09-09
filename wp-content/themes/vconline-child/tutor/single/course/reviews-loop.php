<?php
/**
 * Template for review loop
 *
 * @package Tutor\Templates
 * @subpackage Single\Course
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 1.0.0
 */

$default_avatar_url = get_stylesheet_directory_uri() . '/images/avatar.svg';

foreach ( $reviews as $review ) : ?>
	<?php 
	$profile_url = tutor_utils()->profile_url( $review->user_id, false ); 
	$user = get_userdata( $review->user_id );
	$profile_photo = ( $user && is_a( $user, 'WP_User' ) ) ? get_user_meta( $user->ID, '_tutor_profile_photo', true ) : '';
	$has_custom_photo = ( $profile_photo && wp_get_attachment_image_url( $profile_photo ) );
	$avatar_image_src = $has_custom_photo ? wp_get_attachment_image_url( $profile_photo, 'thumbnail' ) : $default_avatar_url;
	?>
	<div class="tutor-review-list-item tutor-card-list-item tutor-p-24 tutor-p-lg-40">
		<div class="tutor-row">
			<div class="tutor-col-lg-3 tutor-mb-16 tutor-mb-lg-0">
				<div class="tutor-mb-12">
					<div class="tutor-avatar tutor-avatar-md tutor-review-avatar">
						<div class="tutor-ratio tutor-ratio-1x1">
							<img src="<?php echo esc_url( $avatar_image_src ); ?>" alt="<?php echo esc_attr( $review->display_name ); ?>" class="tutor-avatar-img" />
						</div>
					</div>
				</div>

				<div class="tutor-reviewer-name tutor-fs-6 tutor-mb-4">
					<a href="<?php echo esc_url( $profile_url ); ?>" class="tutor-color-black">
						<?php echo esc_html( $review->display_name ); ?>
					</a>
				</div>

				<div class="tutor-reviewed-on tutor-fs-7 tutor-color-muted">
					<?php
					/* translators: %s human-readable time difference. */
					echo esc_html( sprintf( __( '%s ago', 'tutor' ), human_time_diff( strtotime( $review->comment_date_gmt ) ) ) );
					?>
				</div>
			</div>

			<div class="tutor-col-lg-9">
				<?php if ( 'hold' == $review->comment_status ) : ?>
					<div style="position:absolute; right:15px">
						<span class="tutor-badge-label label-warning">
							<?php esc_html_e( 'Pending', 'tutor' ); ?>
						</span>
					</div>
				<?php endif; ?>

				<?php tutor_utils()->star_rating_generator_v2( $review->rating, null, true, 'tutor-is-sm' ); ?>
				
				<div class="tutor-fs-7 tutor-color-secondary tutor-mt-12 tutor-review-comment">
					<?php
					//phpcs:ignore
					echo tutor_utils()->clean_html_content(
						nl2br( stripslashes( $review->comment_content ) ),
						array( 'br' => array() )
					);
					?>
				</div>
			</div>
		</div>
	</div>
<?php endforeach; ?>
