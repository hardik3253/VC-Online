<?php

/**
 * Class Ajax
 *
 * @package  Tutor\Certificate\Builder
 * @author   Themeum <support@themeum.com>
 * @license  GPLv2 or later
 * @link     https://www.themeum.com/product/tutor-lms/
 * @since    1.0.0
 */

namespace Tutor\Certificate\Builder;

use TutorPro\CourseBundle\Models\BundleModel;
use WP_Query;

/**
 * Class Ajax
 *
 * @package Tutor\Certificate\Builder
 *
 * @since   1.0.0
 */
class Ajax {

	/**
	 * Ajax constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_tutor_save_certificate', array( $this, 'tutor_save_certificate' ) );
		add_action( 'wp_ajax_tutor_save_draft_certificate', array( $this, 'tutor_save_certificate' ) );
		add_action( 'wp_ajax_tutor_view_certificate', array( $this, 'tutor_view_certificate' ) );
		add_action( 'wp_ajax_tutor_certificate_store_preview', array( $this, 'tutor_certificate_store_preview' ) );
		add_action( 'wp_ajax_tutor_get_cert_info', array( $this, 'tutor_get_cert_info' ) );
		add_action( 'wp_ajax_tutor_delete_certificate_template', array( $this, 'tutor_delete_certificate' ) );
		add_action( 'wp_ajax_tutor_cb_template_status_update', array( $this, 'tutor_cb_template_status_update' ) );
		add_action( 'wp_ajax_tutor_certificate_get_media_images', array( $this, 'ajax_get_media_images' ) );
	}

	public function tutor_cb_template_status_update() {
		tutor_utils()->checking_nonce();

		$status      = sanitize_text_field( tutor_utils()->array_get( 'status', $_POST ) );
		$template_id = absint( tutor_utils()->array_get( 'template_id', $_POST ) );

		$allowed_statuses = array( 'publish', 'draft' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'tutor-lms-certificate-builder' ) ) );
		}

		if ( ! $template_id || ! Helper::can_manage_certificate( $template_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		wp_update_post(
			array(
				'post_status' => $status,
				'ID'          => $template_id,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Certificate Status Updated', 'tutor-lms-certificate-builder' ) ) );
	}

	public function tutor_delete_certificate() {
		tutor_utils()->checking_nonce();
		$id = absint( tutor_utils()->array_get( 'certificate_id', $_POST ) );

		if ( ! $id || ! Helper::can_manage_certificate( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		$basedir      = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . Plugin::CERTIFICATE_TEMPLATE_DIR;
		$real_basedir = realpath( $basedir );
		$name         = sanitize_file_name( (string) get_post_meta( $id, Plugin::CERTIFICATE_PREVIEW_META_KEY, true ) );

		if ( ! wp_delete_post( $id, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete certificate.', 'tutor-lms-certificate-builder' ) ) );
		}

		if ( $real_basedir && '' !== $name ) {
			$real_path = realpath( $basedir . DIRECTORY_SEPARATOR . $name );
			if ( $real_path && strpos( $real_path, $real_basedir . DIRECTORY_SEPARATOR ) === 0 && is_file( $real_path ) ) {
				unlink( $real_path );
			}
		}

		wp_send_json_success();
	}

	private function get_user_name( $user ) {
		$user_name                = '';
		! $user_name ? $user_name = trim( $user->display_name ) : 0;
		! $user_name ? $user_name = trim( $user->user_firstname . ' ' . $user->user_lastname ) : 0;
		! $user_name ? $user_name = $user->user_login : 0;
		return $user_name;
	}

	public function tutor_get_cert_info() {
		$cert_hash = sanitize_text_field( tutils()->array_get( 'cert_hash', $_GET ) );

		// Check gradebook if enabled.
		$has_gradebook     = function_exists( 'TUTOR_GB' ) && tutor_utils()->is_addon_enabled( TUTOR_GB()->basename );
		$has_course_bundle = tutor_utils()->is_addon_enabled( 'course-bundle' );

		if ( empty( $cert_hash ) || ! is_string( $cert_hash ) ) {
			if ( ! Helper::has_editor_access() ) {
				wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
			}

			$signature_url = apply_filters( 'tutor_certificate_instructor_signature', get_current_user_id(), false );
			$signature_url = $signature_url ? str_replace( get_home_url(), '', $signature_url ) : '';

			wp_send_json_success(
				array(
					'SIGNATURE_URL' => $signature_url,
					'ADDONS'        => array(
						'gradebook'     => $has_gradebook,
						'course_bundle' => $has_course_bundle,
					),
				)
			);
		}

		// Check if course completed.
		$completed = apply_filters( 'tutor_certificate_completion_data', $cert_hash );
		if (
			! is_object( $completed ) ||
			! property_exists( $completed, 'completed_user_id' ) ||
			! property_exists( $completed, 'course_id' ) ||
			! property_exists( $completed, 'completion_date' )
		) {
			wp_send_json_error( array( 'message' => __( 'Invalid Course', 'tutor-lms-certificate-builder' ) ) );
		}

		$current_user_id = get_current_user_id();
		$is_owner        = (int) $completed->completed_user_id === $current_user_id;
		$is_admin        = current_user_can( 'administrator' );
		$is_instructor   = (int) get_post_field( 'post_author', $completed->course_id ) === $current_user_id;

		if ( ! $is_owner && ! $is_admin && ! $is_instructor ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		// Get course info.
		$course     = get_post( $completed->course_id );
		$student    = get_userdata( $completed->completed_user_id );
		$instructor = get_userdata( get_post_field( 'post_author', $completed->course_id ) );

		// PDF download name.
		$user_name = $this->get_user_name( $student );
		$pdf_name  = 'Certificate_for_' . str_replace( ' ', '_', $user_name );

		// Get signature URL.
		$signature_url = apply_filters( 'tutor_certificate_instructor_signature', $instructor->ID, false );
		$signature_url = $signature_url ? str_replace( get_home_url(), '', $signature_url ) : '';

		// Get grade info.
		$def_grade      = array(
			'gradename'  => '',
			'gradepoint' => '',
		);
		$grade          = apply_filters( 'tutor_gradebook_get_final_stats', $def_grade, $completed->course_id );
		$duration_texts = array(
			'h' => _x( 'hrs', 'course-duration', 'tutor-lms-certificate-builder' ),
			'm' => _x( 'min', 'course-duration', 'tutor-lms-certificate-builder' ),
			's' => _x( 'sec', 'course-duration', 'tutor-lms-certificate-builder' ),
		);

		// Get bundle courses
		$bundle_course_titles = array();
		if ( $has_course_bundle && 'course-bundle' === get_post_type( $completed->course_id ) ) {
			$bundle_courses = BundleModel::get_bundle_courses( $completed->course_id );
			foreach ( $bundle_courses as $bundle_course ) {
				$bundle_course_titles[] = $bundle_course->post_title;
			}
		}

		wp_send_json_success(
			array(
				'COURSE_TITLE'    => $course->post_title,
				'STUDENT_NAME'    => $this->get_user_name( $student ),
				'INSTRUCTOR_NAME' => $this->get_user_name( $instructor ),
				'VERIFICATION_ID' => $cert_hash,
				'POINT'           => $grade['gradepoint'],
				'GRADE'           => $grade['gradename'],
				'QR_URL'          => apply_filters( 'tutor_certificate_public_url', $cert_hash ),
				'TIME'            => date_i18n( get_option( 'date_format' ), strtotime( $completed->completion_date ) ),
				'DURATION'        => tutor_utils()->get_course_duration( $completed->course_id, false, $duration_texts ),
				'SIGNATURE_URL'   => $signature_url,
				'PDF_NAME'        => $pdf_name,
				'ADDONS'          => array(
					'gradebook'     => $has_gradebook,
					'course_bundle' => $has_course_bundle,
				),
				'BUNDLE_COURSES'  => $bundle_course_titles,
			)
		);
	}

	/**
	 * Save Certificate Draft data
	 *
	 * @return json
	 */
	public function tutor_save_certificate() {
		if ( false === Helper::has_editor_access() ) {
			wp_send_json_error( array( 'message' => __( 'You can not save certificate!', 'tutor-lms-certificate-builder' ) ) );
		}

		$certificate_id   = absint( $_POST['certificate_id'] ?? 0 );
		$certificate_data = $_POST['certificate_data'] ?? '';
		$nonce            = $_POST['_wpnonce'] ?? '';
		$action           = sanitize_key( wp_unslash( $_POST['action'] ?? '' ) );

		// Check for nonce security.
		if ( ! wp_verify_nonce( $nonce, 'tutor-lms-certificate-builder' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bad Request sent. Please try again.', 'tutor-lms-certificate-builder' ) ) );
		}

		if ( empty( $certificate_id ) || empty( $certificate_data ) || ! is_string( $certificate_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid certificate data.', 'tutor-lms-certificate-builder' ) ) );
		}

		if ( ! Helper::can_manage_certificate( $certificate_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		$certificate_data_arr = json_decode( wp_unslash( $certificate_data ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $certificate_data_arr ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid certificate data.', 'tutor-lms-certificate-builder' ) ) );
		}

		$post_data = isset( $certificate_data_arr['post'] ) && is_array( $certificate_data_arr['post'] ) ? $certificate_data_arr['post'] : array();

		// Validate everything before writing anything.
		if ( ! $post_data ) {
			wp_send_json_error( array( 'message' => __( 'Something was wrong. Please try again.', 'tutor-lms-certificate-builder' ) ) );
		}

		$post_title  = sanitize_text_field( $post_data['post_title'] ?? '' );
		$orientation = sanitize_text_field( $certificate_data_arr['canvas']['settings']['orientation'] ?? '' );
		$size        = sanitize_text_field( $certificate_data_arr['canvas']['settings']['size'] ?? '' );

		// Unset Post data.
		unset( $certificate_data_arr['post'] );

		$certificate_data_json = wp_json_encode( $certificate_data_arr );
		if ( false === $certificate_data_json ) {
			wp_send_json_error( array( 'message' => __( 'Invalid certificate data.', 'tutor-lms-certificate-builder' ) ) );
		}

		// Save Data.
		$is_draft = 'tutor_save_draft_certificate' === $action;
		$meta_key = $is_draft ? 'tutor_certificate_draft_data' : 'tutor_certificate_data';
		update_post_meta( $certificate_id, $meta_key, wp_slash( $certificate_data_json ) );

		// Save dimension and size in separate meta for easy access.
		if ( '' !== $orientation ) {
			update_post_meta( $certificate_id, 'tutor_certificate_dimension', $orientation );
		}
		if ( '' !== $size ) {
			update_post_meta( $certificate_id, 'tutor_certificate_size', $size );
		}

		if ( ! $is_draft ) {
			// Delete draft data if it is publish action.
			delete_post_meta( $certificate_id, 'tutor_certificate_draft_data' );
		}

		// Update Post data.
		$post_args = array(
			'ID'         => $certificate_id,
			'post_title' => $post_title,
		);

		// Add publish status only if publish action.
		if ( ! $is_draft ) {
			$post_args['post_status'] = 'publish';
		}

		$post_id = wp_update_post( wp_slash( $post_args ), true );
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Certificate Data Updated Successfully', 'tutor-lms-certificate-builder' ) ) );
	}


	/**
	 * View Certificate data by ajax call
	 *
	 * @return json
	 */
	public function tutor_view_certificate() {
		$certificate_id = absint( $_POST['certificate_id'] ?? 0 );
		$nonce          = $_POST['_wpnonce'] ?? '';

		// Check for nonce security.
		if ( ! wp_verify_nonce( $nonce, 'tutor-lms-certificate-builder' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bad Request sent. Please try again.', 'tutor-lms-certificate-builder' ) ) );
		}

		$cert_post = get_post( $certificate_id );
		if ( ! $cert_post || Plugin::CERTIFICATE_POST_TYPE !== $cert_post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Certificate not found.', 'tutor-lms-certificate-builder' ) ) );
		}

		$is_admin  = current_user_can( 'administrator' );
		$is_author = (int) $cert_post->post_author === get_current_user_id();

		// Non-authors can only access published templates.
		if ( ! $is_admin && ! $is_author && 'publish' !== $cert_post->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		$decode = static function ( $value ) {
			if ( is_serialized( $value ) ) {
				return unserialize( $value, array( 'allowed_classes' => false ) );
			}
			return is_string( $value ) ? json_decode( $value, true ) : $value;
		};

		$draft     = get_post_meta( $certificate_id, 'tutor_certificate_draft_data', true );
		$published = get_post_meta( $certificate_id, 'tutor_certificate_data', true );

		// Only author or admin can see draft data; non-authors only get published data.
		$can_see_draft = $is_admin || $is_author;
		$use_draft     = $can_see_draft && $draft;

		$certificate_data = $decode( $use_draft ? $draft : $published );
		$published_data   = $use_draft ? $decode( $published ) : null;

		wp_send_json(
			array(
				'type'                     => $certificate_data ? 'success' : 'info',
				'message'                  => $certificate_data ? __( 'Certificate data found.', 'tutor-lms-certificate-builder' ) : __( 'No certificate data found.', 'tutor-lms-certificate-builder' ),
				'certificateData'          => $certificate_data,
				'certificateDataPublished' => $published_data,
			)
		);
	}

	public function tutor_certificate_store_preview() {
		tutor_utils()->checking_nonce();

		if ( ! is_user_logged_in() || ! Helper::has_editor_access() ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		$certificate_id = absint( tutor_utils()->array_get( 'certificate_id', $_POST, 0 ) );

		if ( ! $certificate_id || ! Helper::can_manage_certificate( $certificate_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		// Check if valid image.
		if (
			! isset( $_FILES['certificate_image'] ) ||
			UPLOAD_ERR_OK !== $_FILES['certificate_image']['error'] ||
			empty( $_FILES['certificate_image']['tmp_name'] ) ||
			'image/jpeg' !== wp_get_image_mime( $_FILES['certificate_image']['tmp_name'] )
		) {

			wp_send_json_error( array( 'message' => __( 'Could not save preview due to invalid image.', 'tutor-lms-certificate-builder' ) ) );
		}

		// Get the dir from outside of the filter hook to avoid a recursive filter call.
		$certificates_dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . Plugin::CERTIFICATE_TEMPLATE_DIR;

		// Make sure the directory exists.
		if ( ! wp_mkdir_p( $certificates_dir ) && ! is_dir( $certificates_dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save preview.', 'tutor-lms-certificate-builder' ) ) );
		}

		// Store new file.
		$cache_bust = str_replace( '.', '', (string) microtime( true ) );
		$file_name  = 'template-' . $certificate_id . '-' . $cache_bust . '.jpg';
		$file_dest  = $certificates_dir . DIRECTORY_SEPARATOR . $file_name;
		if ( ! move_uploaded_file( $_FILES['certificate_image']['tmp_name'], $file_dest ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save preview.', 'tutor-lms-certificate-builder' ) ) );
		}

		// Delete old preview file.
		$old_name = get_post_meta( $certificate_id, Plugin::CERTIFICATE_PREVIEW_META_KEY, true );
		$old_path = $old_name ? $certificates_dir . DIRECTORY_SEPARATOR . basename( $old_name ) : null;
		( $old_path && file_exists( $old_path ) ) ? unlink( $old_path ) : 0;

		// Save the preview file name in meta.
		update_post_meta( $certificate_id, Plugin::CERTIFICATE_PREVIEW_META_KEY, $file_name );

		wp_send_json_success();
	}

	/**
	 * Ajax handler to get media images
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function ajax_get_media_images() {
		if ( ! Helper::has_editor_access() || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission Denied', 'tutor-lms-certificate-builder' ) ) );
		}

		$nonce  = $_POST['_wpnonce'] ?? '';
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

		// Check for nonce security.
		if ( ! wp_verify_nonce( $nonce, 'tutor-lms-certificate-builder' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bad Request sent. Please try again.', 'tutor-lms-certificate-builder' ) ) );
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 10,
			'author'         => get_current_user_id(),
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
			's'              => $search,
		);

		$query = new WP_Query( $args );

		$media = array();

		foreach ( $query->posts as $post ) {
			$meta    = wp_get_attachment_metadata( $post->ID );
			$media[] = array(
				'id'      => $post->ID,
				'guid'    => wp_get_attachment_url( $post->ID ),
				'title'   => get_the_title( $post->ID ),
				'thumb'   => wp_get_attachment_thumb_url( $post->ID ),
				'author'  => $post->post_author,
				'sizes'   => isset( $meta['sizes'] ) ? $meta['sizes'] : array(),
				'details' => $meta,
			);
		}

		wp_send_json_success( $media );
	}
}
