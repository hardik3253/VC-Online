<?php
/**
 * Helper ulits functions
 *
 * @package  Tutor\Certificate\Builder
 * @author   Themeum <support@themeum.com>
 * @license  GPLv2 or later
 * @link     https://www.themeum.com/product/tutor-lms/
 * @since    1.0.0
 */

namespace Tutor\Certificate\Builder;

/**
 * Class Helper
 *
 * @package Tutor\Certificate\Builder
 *
 * @since   1.0.0
 */
class Helper {

	/**
	 * Is open editor
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return bool True if editor is active, false otherwise.
	 */
	public static function is_open_editor() {
		return ( isset( $_GET['action'] ) && 'tutor_certificate_builder' === $_GET['action'] );
	}

	/**
	 * On delete certificate
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return bool True if action is active, false otherwise.
	 */
	public static function on_delete_certificate() {
		return ( isset( $_GET['action'] ) && 'tutor_certificate_builder_delete' === $_GET['action'] );
	}

	/**
	 * On delete certificate
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param string $nonce WP Nonce value.
	 *
	 * @return bool True if action is active, false otherwise.
	 */
	public static function is_nonce_valid( $nonce = '', $action = -1 ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Check if the request is a valid certificate preview for a student/public viewer.
	 *
	 * @since 1.3.3
	 * @param int $template_id
	 * @return bool
	 */
	public static function is_valid_certificate_preview( $template_id = 0 ) {
		$cert_hash = sanitize_text_field( $_GET['cert_hash'] ?? '' );
		$course_id = absint( $_GET['course_id'] ?? 0 );

		if ( empty( $cert_hash ) || empty( $course_id ) || empty( $template_id ) ) {
			return false;
		}

		$completed = apply_filters( 'tutor_certificate_completion_data', $cert_hash );
		if ( ! is_object( $completed ) || (int) $completed->course_id !== $course_id ) {
			return false;
		}

		$template_post = get_post( $template_id );
		if ( ! $template_post || 'publish' !== $template_post->post_status ) {
			return false;
		}

		$course_template = get_post_meta( $course_id, 'tutor_course_certificate_template', true );
		if ( empty( $course_template ) ) {
			$course_template = tutor_utils()->get_option( 'certificate_template', 'default' );
		}

		return ( 'tutor_cb_' . $template_id ) === $course_template;
	}

	/**
	 * Has editor access
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return bool True if user has editor access, false otherwise.
	 */
	public static function has_editor_access() {
		$disable_certificate_creation_for_instructors = (bool) tutor_utils()->get_option( 'disable_certificate_creation_for_instructors', false );

		if ( current_user_can( 'administrator' ) ) {
			return true;
		}

		if ( current_user_can( tutor()->instructor_role ) && ! $disable_certificate_creation_for_instructors ) {
			return true;
		}

		if ( isset( $_GET['post'], $_GET['cert_hash'], $_GET['course_id'] ) && is_user_logged_in() ) {
			return self::is_valid_certificate_preview( absint( $_GET['post'] ) );
		}

		return false;
	}

	/**
	 * Has User access to do action
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param $post_id Current post id to check permission
	 * @param $permission_type permission type delete_certificate
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public static function has_user_permission( $post_id = 0, $permission_type = '' ) {
		$user_id = get_current_user_id();
		if ( ! $user_id || ! $post_id ) {
			return false;
		}
		$author_id = get_post_field( 'post_author', $post_id );
		if ( ! $author_id ) {
			return false;
		}
		if ( $permission_type === 'delete_certificate' ) {
			return (int) $user_id === (int) $author_id;
		}
		return false;
	}

	/**
	 * Check whether a user can manage a certificate.
	 *
	 * Verifies that the post exists, is of the certificate post type,
	 * and that the user is an administrator or the certificate author.
	 *
	 * @since 1.3.3
	 * @access public
	 * @static
	 *
	 * @param int|\WP_Post $certificate Certificate post ID or WP_Post object.
	 * @param int|null     $user_id     Optional. User ID. Defaults to current user.
	 *
	 * @return bool True if authorized, false otherwise.
	 */
	public static function can_manage_certificate( $certificate, $user_id = null ) {
		$post = get_post( $certificate );

		if ( ! $post || Plugin::CERTIFICATE_POST_TYPE !== $post->post_type ) {
			return false;
		}

		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		if ( user_can( $user_id, 'administrator' ) ) {
			return true;
		}

		return (int) $post->post_author === $user_id;
	}

	/**
	 * Setup editor post data
	 *
	 * @param int $post_id
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function setup_post_data( $post_id ) {
		if ( get_the_ID() === $post_id ) {
			return;
		}
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * Get editor URL
	 *
	 * @param int $post_id
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return string
	 */
	public static function certificate_builder_url( $id = null, $additional = array() ) {
		$editor_data             = array( 'action' => Plugin::EDITOR_ACTION );
		if ( $id ) {
			$editor_data['post'] = absint( $id );
		}
		$editor_data['_wpnonce'] = wp_create_nonce( 'tutor-lms-certificate-builder' );
		$editor_data             = array_merge( $editor_data, $additional );

		return add_query_arg( $editor_data, get_home_url() );
	}

	/**
	 * Get duplicate certificate URL
	 *
	 * @param int $id certificate id.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return string
	 */
	public static function certificate_duplicate_url( $id = null, $additional = array() ) {
		$editor_data                    = array( 'action' => Plugin::EDITOR_ACTION );
		if ( $id ) {
			$editor_data['duplicate'] = absint( $id );
		}
		$editor_data['_wpnonce'] = wp_create_nonce( 'tutor-lms-certificate-builder' );
		$editor_data             = array_merge( $editor_data, $additional );

		return add_query_arg( $editor_data, get_home_url() );
	}

	/**
	 * Duplicate a certificate
	 *
	 * @param int $post_id certificate id.
	 */
	public static function duplicate_certificate( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || Plugin::CERTIFICATE_POST_TYPE !== $post->post_type ) {
			return false;
		}

		$args = array(
			'post_author'  => get_current_user_id(),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_name'    => $post->post_name,
			'post_status'  => 'draft',
			'post_title'   => $post->post_title . ' (Copy)',
			'post_type'    => Plugin::CERTIFICATE_POST_TYPE,
		);

		$new_post_id = wp_insert_post( wp_slash( $args ) );

		if ( is_wp_error( $new_post_id ) ) {
			return false;
		}

		$post_meta          = get_post_meta( $post_id );
		$excluded_meta_keys = array( 'tutor_cb_template_preview_name' );
		foreach ( $post_meta as $meta_key => $meta_values ) {
			if ( in_array( $meta_key, $excluded_meta_keys, true ) ) {
				continue;
			}
			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $new_post_id, $meta_key, wp_slash( maybe_unserialize( $meta_value ) ) );
			}
		}

		return $new_post_id;

	}
}
