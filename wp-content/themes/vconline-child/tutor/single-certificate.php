<?php
/**
 * Custom Template for displaying certificate in child theme
 *
 * @package VCOnlineChild
 * @subpackage Tutor
 */

use TUTOR\Input;
use TUTOR_CERT\Certificate;

tutor_utils()->tutor_custom_header();

$cert_obj = new Certificate( true );

$cert_hash  = Input::get( 'cert_hash' );
$completed  = $cert_obj->completed_course( $cert_hash );
$course     = get_post( $completed->course_id );
$upload_dir = wp_upload_dir();

$template = $cert_obj->get_course_certificate_template( $course->ID );

$certificate_dir_url  = $upload_dir['baseurl'] . '/' . $cert_obj->certificates_dir_name;
$certificate_dir_path = $upload_dir['basedir'] . '/' . $cert_obj->certificates_dir_name;
$rand_string          = get_comment_meta( $completed->certificate_id, $cert_obj->certificate_stored_key, true );

$cert_path = '/' . $rand_string . '-' . $cert_hash . '.jpg';
$cert_file = $certificate_dir_path . $cert_path;

if ( ! file_exists( $cert_file ) ) {
	$cert_file = null;
}

$course_template_key = get_post_meta( $course->ID, 'tutor_course_certificate_template', true );
if ( empty( $course_template_key ) ) {
	$course_template_key = 'default';
}

$verified_template_key = get_comment_meta( $completed->certificate_id, '_vco_certificate_template_verified', true );
$user_regen            = ( 1 == Input::get( 'regenerate' ) );

$file_on_disk_valid   = ( $cert_file && file_exists( $cert_file ) );
$course_modified_time = strtotime( $course->post_modified );
$file_mtime           = $file_on_disk_valid ? filemtime( $cert_file ) : 0;

// Determine if certificate needs regeneration:
// 1. Image does not exist on disk
// 2. OR verified template key does not match current course template (fixes live site showing old default certificate!)
// 3. OR file on disk is older than the course's latest update in the backend
// 4. OR explicit user regeneration requested (?regenerate=1)
$needs_regen = ( ! $file_on_disk_valid )
               || ( $verified_template_key !== $course_template_key )
               || ( $file_on_disk_valid && $course_modified_time && $file_mtime < ( $course_modified_time - 60 ) )
               || $user_regen;

$generate_cert = $needs_regen;
if ( $generate_cert ) {
	if ( $file_on_disk_valid ) {
		@unlink( $cert_file );
	}
	$cert_file = null;
}

$cert_img = $generate_cert ? get_admin_url() . 'images/loading.gif' : $certificate_dir_url . $cert_path;
$cert_url = $cert_obj->tutor_certificate_public_url( $cert_hash );

if ( $generate_cert ) {
	update_user_meta( get_current_user_id(), 'tutor_certificate_generated', $completed->course_id );
}

$issued_by = tutor_utils()->get_option( 'tutor_cert_authorised_name' );

$share_config = array(
	'title' => __( 'Course Completion Certificate', 'tutor-pro' ),
	'text'  => __( 'My course completion certificate for', 'tutor-pro' ) . ' ' . $course->post_title,
	'image' => $cert_img,
);

$course_permalink = get_permalink( $course->ID );
?>
<link rel="stylesheet" href="<?php echo esc_url( TUTOR_CERT()->url . 'assets/css/certificate-page.css' ); ?>">

<script>
(function() {
	function setupPrefilter() {
		if (window.jQuery && window.jQuery.ajaxPrefilter) {
			window.jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
				if (options.url && (options.url.indexOf('[object') !== -1 || options.url.indexOf('object%20Object') !== -1)) {
					options.url = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				}
			});
		} else {
			setTimeout(setupPrefilter, 20);
		}
	}
	setupPrefilter();

	<?php if ( $generate_cert ) : ?>
	var pollCount = 0;
	var maxPolls = 20;
	var checkInterval = setInterval(function() {
		pollCount++;
		if (pollCount > maxPolls) {
			clearInterval(checkInterval);
			return;
		}
		if (window.jQuery) {
			window.jQuery.ajax({
				url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				type: 'GET',
				data: {
					action: 'vco_check_certificate_status',
					cert_hash: '<?php echo esc_js( $cert_hash ); ?>'
				},
				success: function(res) {
					if (res && res.success && res.data && res.data.is_generated && res.data.image_url) {
						clearInterval(checkInterval);
						var preview = document.getElementById('tutor-pro-certificate-preview');
						if (preview) {
							preview.src = res.data.image_url;
							preview.dataset.is_generated = 'yes';
							preview.style.width = '';
							preview.style.height = '';
						}
						var printImg = document.querySelector('#div-to-print img');
						if (printImg) {
							printImg.src = res.data.image_url;
							printImg.dataset.is_generated = 'yes';
						}
					}
				}
			});
		}
	}, 2000);
	<?php endif; ?>
})();
</script>

<div class="tutor-download-certificate tutor-pb-48 tutor-p-12">
	<?php do_action( 'tutor_certificate/before_content' ); ?>
	<div class="tutor-dc-title tutor-pb-36" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
		<div class="tutor-certificate-course-title">
			<a href="<?php echo esc_url( $course_permalink ); ?>" class="tutor-dc-course-title tutor-fs-4 tutor-fw-bold tutor-color-black" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:color 0.2s;" onmouseover="this.style.color='#ff5500'" onmouseout="this.style.color='#000000'">
				<span><?php echo esc_html( $course->post_title ); ?></span>
				<span class="tutor-icon-angle-right tutor-fs-6"></span>
			</a>
		</div>
		<div class="vco-cert-access-course-btn-wrap">
			<a href="<?php echo esc_url( $course_permalink ); ?>" class="tutor-btn tutor-btn-outline-primary tutor-btn-sm" style="border-radius:8px; font-weight:600; padding:8px 18px; display:inline-flex; align-items:center; gap:8px;">
				<span class="tutor-icon-play-circle"></span>
				<span><?php esc_html_e( 'Access Course', 'vconline' ); ?></span>
			</a>
		</div>
	</div>
	<div class="tutor-certificate-demo tutor-pb-44">
		<span class="tutor-dc-demo-img">
			<img
				id="tutor-pro-certificate-preview"
				src="<?php echo esc_url( $cert_img ); ?>"
				alt="<?php echo esc_attr( $course->post_title ); ?>"
				style="<?php echo ! $cert_file ? 'width:auto;height:auto;' : ''; ?>"
				data-is_generated="<?php echo esc_attr( $cert_file ? 'yes' : 'no' ); ?>"
				data-certificate_url="<?php echo remove_query_arg( 'regenerate', tutor()->current_url ); ?>"
				data-course_id="<?php echo esc_attr( $course->ID ); ?>"
				data-cert_hash="<?php echo esc_attr( $cert_hash ); ?>" 
				data-orientation="<?php echo esc_attr( isset( $template['orientation'] ) ? $template['orientation'] : '' ); ?>"
				data-size="<?php echo esc_attr( isset( $template['size'] ) ? $template['size'] : 'letter' ); ?>"
			/>
		</span>
	</div>
	<!--Printable area-->
	<div class="tutor-certificate-demo tutor-pb-44" id="div-to-print" style="display:none;max-width:730px;height:auto;overflow:hidden;">
		<span class="tutor-dc-demo-img">
			<img
				style="width: 100%;"
				src="<?php echo esc_url( $cert_img ); ?>"
				alt="<?php echo esc_attr( $course->post_title ); ?>"
				data-is_generated="<?php echo esc_attr( $cert_file ? 'yes' : 'no' ); ?>"
			/>
		</span>
	</div>
	<!--End printable area-->
	<div class="tutor-dc-certificate-details">
		<div class="tutor-certificate-info">
			<div class="tutor-info-id">
				<div class="tutor-info-id-name tutor-fs-7 tutor-color-secondary tutor-pb-4">
					<?php esc_html_e( 'Credential ID', 'tutor-pro' ); ?>
				</div>
				<div class="tutor-info-id-details tutor-fs-6 tutor-fw-medium tutor-color-black">
					#<?php echo esc_html( $cert_hash ); ?>
				</div>
			</div>
			<div class="tutor-info-issued">
				<?php if ( '' !== $issued_by ) : ?>
				<div class="tutor-info-issued-name tutor-fs-7 tutor-color-secondary tutor-pb-4">
					<?php esc_html_e( 'Issued By', 'tutor-pro' ); ?>
				</div>
				<div class="tutor-info-issued-value tutor-fs-6 tutor-fw-medium tutor-color-black">
					<?php echo esc_html( $issued_by ); ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="tutor-info-issued-date">
				<div class="tutor-info-date-name tutor-fs-7 tutor-color-secondary tutor-pb-4">
					<?php esc_html_e( 'Issued Date', 'tutor-pro' ); ?>
				</div>
				<div class="tutor-info-date-details tutor-fs-6 tutor-fw-medium tutor-color-black">
					<?php echo esc_html( tutor_i18n_get_formated_date( $completed->completion_date, get_option( 'date_format' ) ) ); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="tutor-dc-button-group tutor-mt-72">
		<div class="tutor-dc-download-button tutor-py-16">
			<button class="tutor-iconic-btn tutor-iconic-btn-outline tutor-iconic-btn-lg tooltip-wrap">
				<span class="tutor-icon-import-o"></span>
				<span style="top:10px" class="tooltip-txt tooltip-left tutor-d-flex">
					<a class="tutor-certificate-pdf tutor-cert-view-page tutor-mr-8 tutor-d-flex tutor-align-center" style="text-decoration:none;color:#ffffff;">
						<span class="tutor-icon-pdf-file tutor-fs-6"></span> <span><?php esc_html_e( 'PDF', 'tutor-pro' ); ?></span>
					</a>
					<a href="#" class="tutor-d-flex tutor-align-center" id="tutor-pro-certificate-download-image" style="text-decoration:none;color:#ffffff;">
						<span class="tutor-icon-jpg-file tutor-fs-6"></span> <span><?php esc_html_e( 'JPG', 'tutor-pro' ); ?></span>
					</a>
				</span>
			</button>
		</div>
		<div class="tutor-dc-copy-button tutor-copy-text tutor-py-16" data-text="<?php echo esc_url( $cert_url ); ?>">
			<button class="tutor-iconic-btn tutor-iconic-btn-outline tutor-iconic-btn-lg tooltip-wrap">
				<span class="tutor-icon-copy"></span>
				<span class="tooltip-txt tooltip-left"><?php esc_html_e( 'Copy Credential URL', 'tutor-pro' ); ?></span>
			</button>
		</div>
		<div class="tutor-dc-print-button tutor-py-16" onClick="PrintDiv()">
			<button class="tutor-iconic-btn tutor-iconic-btn-outline tutor-iconic-btn-lg tooltip-wrap">
				<span class="tutor-icon-print"></span>
				<span class="tooltip-txt tooltip-left"><?php esc_html_e( 'Print Now', 'tutor-pro' ); ?></span>
			</button>
		</div>
		<div class="tutor-dc-share-button tutor-py-16">
			<button class="tutor-iconic-btn tutor-iconic-btn-outline tutor-iconic-btn-lg tooltip-wrap">
				<span class="tutor-icon-share"></span>
				<span style="top:10px" class="tooltip-txt tooltip-left tutor-d-flex tutor-social-share-wrap" data-social-share-config="<?php echo esc_attr( json_encode( $share_config ) ); ?>">
					<a class="tutor-d-flex tutor-align-center tutor-mr-8 tutor_share s_facebook" style="text-decoration:none;color:#ffffff;">
						<span><?php esc_html_e( 'Facebook', 'tutor-pro' ); ?></span>
					</a>
					<a class="tutor-d-flex tutor-align-center tutor-mr-8 tutor_share s_twitter" style="text-decoration:none;color:#ffffff;">
						<span><?php esc_html_e( 'Twitter', 'tutor-pro' ); ?></span>
					</a>
					<a class="tutor-d-flex tutor-align-center tutor_share s_linkedin" style="text-decoration:none;color:#ffffff;">
						<span><?php esc_html_e( 'LinkedIn', 'tutor-pro' ); ?></span>
					</a>
				</span>
			</button>
		</div>
	</div>
</div>
<?php
tutor_utils()->tutor_custom_footer();
