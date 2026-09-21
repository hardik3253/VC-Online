<?php
/**
 * Tutor create review modal with Course Preview Banner & Access Button
 *
 * @package VCOnlineChild
 * @subpackage Tutor\Templates
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\Constants\Size;
use Tutor\Components\Constants\Variant;
use Tutor\Components\InputField;
use Tutor\Components\StarRatingInput;

// Get global variables.
global $current_user_id,
$tutor_course_id;

$form_id = 'create-review-form';

$course = $tutor_course_id ? get_post( $tutor_course_id ) : null;
$course_thumb = $tutor_course_id ? get_tutor_course_thumbnail_src( 'thumbnail', $tutor_course_id ) : '';
$course_url = $tutor_course_id ? get_permalink( $tutor_course_id ) : '#';
?>
<div
	x-data="tutorReviewModal()"
	<?php echo ! empty( $data['clear_review_popup_data'] ) ? 'x-on:tutor-modal-closed.document="if ($event.detail.id === \'create-review-modal\') clearReviewPopupData(' . esc_js( $tutor_course_id ) . ')"' : ''; ?>
>
	<?php if ( $course ) : ?>
		<div class="vco-review-course-banner" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin: 16px 24px 0 24px; padding: 12px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
			<div style="display:flex; align-items:center; gap:12px; min-width:0;">
				<?php if ( $course_thumb ) : ?>
					<img src="<?php echo esc_url( $course_thumb ); ?>" alt="<?php echo esc_attr( $course->post_title ); ?>" style="width:48px; height:48px; object-fit:cover; border-radius:8px; flex-shrink:0;" />
				<?php endif; ?>
				<div style="min-width:0;">
					<div style="font-size:11px; font-weight:600; text-transform:uppercase; color:#ff5500; letter-spacing:0.5px;"><?php esc_html_e( 'Course Completed', 'vconline' ); ?></div>
					<a href="<?php echo esc_url( $course_url ); ?>" style="font-size:14px; font-weight:600; color:#0f172a; text-decoration:none; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
						<?php echo esc_html( $course->post_title ); ?>
					</a>
				</div>
			</div>
			<a href="<?php echo esc_url( $course_url ); ?>" class="tutor-btn tutor-btn-outline-primary tutor-btn-xs" style="border-radius:6px; font-weight:600; padding:6px 12px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; text-decoration:none; flex-shrink:0;">
				<span class="tutor-icon-play-circle"></span>
				<span><?php esc_html_e( 'Access Course', 'vconline' ); ?></span>
			</a>
		</div>
	<?php endif; ?>

	<form
		class="tutor-flex tutor-flex-column tutor-gap-6"
		id="<?php echo esc_attr( $form_id ); ?>"
		x-data='tutorForm({
			id: "<?php echo esc_attr( $form_id ); ?>",
			mode: "onChange",
			defaultValues: {
				comment_post_ID: <?php echo esc_html( $tutor_course_id ); ?>,
				clear_review_popup_data: <?php echo ! empty( $data['clear_review_popup_data'] ) ? 'true' : 'false'; ?>
			},
		})'
		x-bind="getFormBindings()"
		@submit.prevent="handleSubmit(
			(data) => handleReviewSubmit(data),
		)($event)"
	>
		<div class="tutor-p-7">
			<?php
			StarRatingInput::make()
				->field_name( 'rating' )
				->current_rating( $review->rating ?? 0 )
				->view( 'emoji' )
				->attr( 'x-bind', "register('rating', { required: '" . esc_js( __( 'Rating is required', 'tutor' ) ) . "' })" )
				->render();
			?>
		</div>

		<div class="tutor-pt-7 tutor-px-7 tutor-border-t">
			<?php
			InputField::make()
				->type( 'textarea' )
				->label( __( 'Write Your Review', 'tutor' ) )
				->placeholder( __( 'Tell us about your experience. Was it a good match for you?', 'tutor' ) )
				->name( 'comment_content' )
				->required()
				->clearable()
				->attr( 'x-bind', "register('comment_content', { required: '" . esc_js( __( 'Review content is required', 'tutor' ) ) . "' })" )
				->render();
			?>
		</div>

		<div class="tutor-flex tutor-justify-between tutor-gap-6 tutor-pb-6 tutor-px-7">
			<?php
			Button::make()
				->label( __( 'Cancel', 'tutor' ) )
				->variant( Variant::SECONDARY )
				->size( Size::SMALL )
				->attr( 'x-on:click', "TutorCore.modal.closeModal('create-review-modal')" )
				->attr( 'type', 'button' )
				->attr( 'class', 'tutor-w-full' )
				->render();

			Button::make()
				->label( __( 'Submit Review', 'tutor' ) )
				->variant( Variant::PRIMARY )
				->size( Size::SMALL )
				->attr( 'type', 'submit' )
				->attr( 'class', 'tutor-w-full' )
				->attr( ':class', "{ 'tutor-btn-loading': saveRatingMutation?.isPending }" )
				->attr( ':disabled', 'saveRatingMutation?.isPending' )
				->render();
			?>
		</div>
	</form>
</div>
