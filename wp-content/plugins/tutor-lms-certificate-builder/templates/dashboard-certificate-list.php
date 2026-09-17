<?php
/**
 * Frontend Dashboard List Template
 *
 * @package  Tutor\Certificate\Builder
 * @author   Themeum <support@themeum.com>
 * @license  GPLv2 or later
 * @link     https://www.themeum.com/product/tutor-lms/
 * @since    1.3.1
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Certificate\Builder\Helper;
use Tutor\Certificate\Builder\Utils;
use Tutor\Components\ConfirmationModal;
use Tutor\Components\DropdownFilter;
use Tutor\Components\EmptyState;
use Tutor\Components\Pagination;
use Tutor\Components\Popover;
use Tutor\Components\Button;
use Tutor\Components\Constants\Color;
use Tutor\Components\Constants\Positions;
use Tutor\Components\Constants\Size;
use Tutor\Components\Constants\Variant;
use Tutor\Components\Sorting;
use Tutor\Components\StatusSelect;
use TUTOR\Icon;
use TUTOR\Input;

$status        = Input::get( 'data' );
$order         = Input::get( 'order', 'DESC' );
$current_page  = max( 1, Input::get( 'current_page', 1, Input::TYPE_INT ) );
$item_per_page = tutor_utils()->get_option( 'pagination_per_page', 10 );

$status_filter = array( 'publish', 'draft' );
if ( ! empty( $status ) ) {
	$status_filter = $status;
}

$author_id          = tutor_utils()->has_user_role( 'administrator' ) ? null : get_current_user_id();
$certificates       = Utils::get_certificates( $current_page, $item_per_page, $author_id, $status_filter, false, false, array(), $order );
$certificates_total = Utils::get_certificates( 0, -1, $author_id, $status_filter, true );

$filter_items = array(
	array(
		'label' => __( 'All Certificates', 'tutor-lms-certificate-builder' ),
		'value' => '',
	),
	array(
		'label' => __( 'Publish', 'tutor-lms-certificate-builder' ),
		'value' => 'publish',
	),
	array(
		'label' => __( 'Draft', 'tutor-lms-certificate-builder' ),
		'value' => 'draft',
	),
);

$status_options = array(
	'publish' => array(
		'label'   => __( 'Published', 'tutor-lms-certificate-builder' ),
		'variant' => 'success',
		'icon'    => Icon::EYE_LINE,
	),
	'draft'   => array(
		'label'   => __( 'Draft', 'tutor-lms-certificate-builder' ),
		'variant' => 'default',
		'icon'    => Icon::RESOURCES,
	),
);

?>
<div class="tutor-cb-templates" x-data="{
	deleteMutation: null,

	init() {
		const { query, modal, toast } = window.TutorCore;
		const config = window._tutorobject;

		this.deleteMutation = query.useMutation(async (id) => {
			const formData = new FormData();
			formData.append('action', 'tutor_delete_certificate_template');
			formData.append('certificate_id', id);
			
			if (config.nonce_key && config[config.nonce_key]) {
				formData.append(config.nonce_key, config[config.nonce_key]);
			}

			const response = await fetch(config.ajaxurl, {
				method: 'POST',
				body: formData
			});

			const result = await response.json();
			if (!result.success) {
				throw new Error(result.data?.message || '<?php esc_html_e( 'Failed to delete certificate', 'tutor-lms-certificate-builder' ); ?>');
			}
			return result.data;
		}, {
			onSuccess: () => {
				modal.closeModal('tutor-certificate-delete-modal');
				toast.success('<?php esc_html_e( 'Certificate deleted successfully', 'tutor-lms-certificate-builder' ); ?>');
				window.location.reload();
			},
			onError: (error) => {
				toast.error(error.message);
			}
		});
	}
}">
	<div class="tutor-card tutor-mb-8 tutor-p-9 tutor-flex tutor-sm-flex-column tutor-gap-9 tutor-items-center tutor-sm-items-start tutor-justify-between">
		<div style="max-width: 360px;">
			<div class="tutor-h4 tutor-font-medium tutor-mb-4">
				<?php _e( 'Create Your Certificate In 3 Steps', 'tutor-lms-certificate-builder' ); ?>
			</div>
			<div class="tutor-p2 tutor-text-secondary tutor-mb-8">
				<?php _e( 'Add instructor, Benefits, Requirements/Instructions, Targeted Audience, Materials Included.', 'tutor-lms-certificate-builder' ); ?>
			</div>
			<a class="tutor-btn tutor-btn-primary tutor-btn-small" href="<?php echo Helper::certificate_builder_url(); ?>">
				<?php tutor_utils()->render_svg_icon( Icon::CERTIFICATE_2 ); ?>
				<?php _e( 'Create certificate', 'tutor-lms-certificate-builder' ); ?>
			</a>
		</div>
		<div>
			<img src="<?php echo esc_attr( TUTOR_CB_PLUGIN_URL . 'assets/images/certificate-illustrations-front.svg' ); ?>" alt="<?php esc_html_e( 'Certificate illustration', 'tutor-lms-certificate-builder' ); ?>" />
		</div>
	</div>

	<div class="tutor-card tutor-p-none">
		<div class="tutor-flex tutor-items-center tutor-justify-between tutor-py-5 tutor-px-6 tutor-border-b">
			<?php DropdownFilter::make()->options( $filter_items )->count( $certificates_total )->query_param( 'data' )->render(); ?>
			<?php Sorting::make()->order( $order )->render(); ?>
		</div>

		<?php if ( ! empty( $certificates ) ) : ?>
		<div class="tutor-card-list">
			<?php foreach ( $certificates as $key => $certificate ) : ?>
				<?php
					$row_id           = 'tutor-cb-row-id-' . $certificate->ID;
					$delete_id        = 'tutor-modal-tutor-cb-row-del-' . $certificate->ID;
					$certificate_size = get_post_meta( $certificate->ID, 'tutor_certificate_size', true );
					$size_text        = 'a4' === $certificate_size ? __( 'A4', 'tutor-lms-certificate-builder' ) : __( 'Letter', 'tutor-lms-certificate-builder' );
					$is_last_item     = $key === array_key_last( $certificates )
				?>
				<div class="tutor-py-5 tutor-px-6 <?php echo esc_attr( ! $is_last_item ? 'tutor-border-b' : '' ); ?>">
					<div class="tutor-flex tutor-items-center tutor-justify-between">
						<div class="tutor-flex tutor-gap-4 tutor-items-center">
							<?php if ( $certificate->thumbnail_url ) : ?>
								<img style="width:56px; height:auto;" src="<?php echo esc_url( $certificate->thumbnail_url ); ?>" alt="<?php echo esc_attr( $certificate->post_title ); ?>"/>
							<?php endif; ?>
							<div class="tutor-flex tutor-flex-column tutor-gap-1">
								<div class="tutor-small tutor-font-medium"><?php echo esc_html( $certificate->post_title ); ?></div>
								<div class="tutor-tiny tutor-text-secondary"><?php esc_html_e( 'Size:', 'tutor-lms-certificate-builder' ); ?> <span class="tutor-color-subdued"><?php echo esc_html( $size_text ); ?></span></div>
							</div>
						</div>

						<div class="tutor-flex tutor-gap-2">
							<?php
							StatusSelect::make()
							->options( $status_options )
							->selected( $certificate->post_status )
							->action( 'tutor_cb_template_status_update' )
							->data( array( 'template_id' => $certificate->ID ) )
							->render();
							?>

							<?php
							Popover::make()
								->placement( Positions::BOTTOM_END )
								->menu_min_width( '120px' )
								->trigger(
									Button::make()
										->label( __( 'More options', 'tutor' ) )
										->variant( Variant::GHOST )
										->size( Size::X_SMALL )
										->icon( Icon::ELLIPSES, 'left', 16, Color::SECONDARY )
										->icon_only()
										->attr( 'x-ref', 'trigger' )
										->attr( '@click', 'toggle()' )
										->get()
								)
								->menu_item( array(
									'tag'     => 'a',
									'content' => __( 'Duplicate', 'tutor-lms-certificate-builder' ),
									'icon'    => tutor_utils()->get_svg_icon( Icon::COPY_2, 20, 20 ),
									'class'   => 'tutor-gap-5',
									'attr'    => array( 'href' => esc_url( $certificate->duplicate_url ) ),
								) )
								->menu_item( array(
									'tag'     => 'a',
									'content' => __( 'Edit', 'tutor-lms-certificate-builder' ),
									'icon'    => tutor_utils()->get_svg_icon( Icon::EDIT_2, 20, 20 ),
									'class'   => 'tutor-gap-5',
									'attr'    => array( 'href' => esc_url( $certificate->edit_url ) ),
								) )
								->menu_item( array(
									'tag'     => 'button',
									'content' => __( 'Delete', 'tutor-lms-certificate-builder' ),
									'icon'    => tutor_utils()->get_svg_icon( Icon::DELETE_2, 20, 20 ),
									'class'   => 'tutor-gap-5',
									'attr'    => array( '@click' => "hide(); TutorCore.modal.showModal('tutor-certificate-delete-modal', { certificateId: $certificate->ID });" ),
								) )
								->render();
							?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

			<?php
			Pagination::make()
			->current( $current_page )
			->total( $certificates_total )
			->limit( $item_per_page )
			->attr( 'class', 'tutor-border-t tutor-py-5 tutor-px-6' )
			->render();

			ConfirmationModal::make()
			->id( 'tutor-certificate-delete-modal' )
			->title( __( 'Do You Want to Delete This Certificate?', 'tutor-lms-certificate-builder' ) )
			->message( __( 'Are you sure you want to delete this certificate permanently from the site? Please confirm your choice.', 'tutor-lms-certificate-builder' ) )
			->confirm_text( __( 'Yes, Delete This', 'tutor-lms-certificate-builder' ) )
			->confirm_handler( 'deleteMutation?.mutate(payload?.certificateId)' )
			->mutation_state( 'deleteMutation' )
			->render();
			?>
		
		<?php else : ?>
			<?php EmptyState::make()->title( __( 'No certificates found!', 'tutor-lms-certificate-builder' ) )->render(); ?>
		<?php endif; ?>
	</div>
</div>
