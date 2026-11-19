<?php

/**
 * Class Editor
 *
 * @package  Tutor\Certificate\Builder
 * @author   Themeum <support@themeum.com>
 * @license  GPLv2 or later
 * @link     https://www.themeum.com/product/tutor-lms/
 * @since    1.0.0
 */

namespace Tutor\Certificate\Builder;

use Tutor\Certificate\Builder\Plugin;

/**
 * Class Editor
 *
 * @since 1.0.0
 */
class Editor {


	/**
	 * Editor post id.
	 *
	 * @since 1.0.0
	 */
	private $_post_id;

	/**
	 * Editor constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_builder' ) );
	}

	/**
	 * Enqueue script for admin panel.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		$post = array(
			'ID'          => 0,
			'post_title'  => '',
			'post_status' => 'draft',
		);

		$post_id   = absint( $_REQUEST['post'] );
		$post_data = get_post( $post_id, ARRAY_A );
		if ( $post_data ) {
			$post['ID']          = isset( $post_data['ID'] ) ? $post_data['ID'] : 0;
			$post['post_title']  = isset( $post_data['post_title'] ) ? $post_data['post_title'] : '';
			$post['post_status'] = isset( $post_data['post_status'] ) ? $post_data['post_status'] : 'draft';
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'tutor-lms-certificate-builder-editor-script',
			TUTOR_CB_PLUGIN_URL . 'assets/editor/dist/main.min.js',
			array(
				'wp-i18n',
				'wp-auth-check',
			),
			TUTOR_CB_VERSION,
			true
		);

		wp_enqueue_style(
			'wp-admin-common-css',
			admin_url() . 'css/common.css',
			false,
			TUTOR_CB_VERSION
		);

		// Localize the script with data.
		wp_localize_script(
			'tutor-lms-certificate-builder-editor-script',
			'tutor_object_cb',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'siteUrl'           => site_url(),
				'userId'            => get_current_user_id(),
				'nonce'             => wp_create_nonce( 'tutor-lms-certificate-builder' ),
				'assetUrl'          => TUTOR_CB_PLUGIN_URL . 'assets',
				'homeUrl'           => get_home_url(),
				'tutor_certificate' => $post,
				'tutor_nonce'       => wp_create_nonce( tutor()->nonce_action ),
				'tutor_nonce_key'   => tutor()->nonce,
			)
		);

		do_action( 'tutor_certificate_builder_enqueue_script' );
	}

	/**
	 * Register styles for front.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		if ( false === Helper::is_open_editor() ) {
			return;
		}

		wp_enqueue_style(
			'tutor-lms-certificate-builder-editor-styles',
			TUTOR_CB_PLUGIN_URL . 'assets/editor/dist/main.min.css',
			array(
				'wp-auth-check',
			),
			TUTOR_CB_VERSION
		);

		do_action( 'tutor_certificate_builder_enqueue_style' );
	}

	/**
	 * Initialize builder editor.
	 *
	 * @since 1.0.0
	 */
	public function init_builder() {
		if ( false === Helper::is_open_editor() ) {
			return;
		}

		if ( ! Helper::has_editor_access() ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'tutor-lms-certificate-builder' ), 403 );
		}

		$duplicate = isset( $_REQUEST['duplicate'] ) ? absint( $_REQUEST['duplicate'] ) : 0;
		if ( $duplicate ) {
			$this->verify_link_nonce();

			if ( ! Helper::can_manage_certificate( $duplicate ) ) {
				wp_die( __( 'Sorry, you are not allowed to duplicate this certificate.', 'tutor-lms-certificate-builder' ), 403 );
			}

			$id = Helper::duplicate_certificate( $duplicate );
			if ( $id ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'action' => Plugin::EDITOR_ACTION,
							'post'   => $id,
						),
						get_home_url()
					)
				);
				exit;
			}

			wp_die( __( 'Sorry, something went wrong.', 'tutor-lms-certificate-builder' ), 500 );
		}

		$post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;

		if ( 0 === $post_id ) {
			$this->verify_link_nonce();

			$post_id = wp_insert_post(
				array(
					'post_title'  => __( 'Untitled Template', 'tutor-lms-certificate-builder' ),
					'post_type'   => Plugin::CERTIFICATE_POST_TYPE,
					'post_status' => 'draft',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				wp_die( esc_html( $post_id->get_error_message() ) );
			}

			wp_safe_redirect( add_query_arg( 'post', $post_id, remove_query_arg( '_wpnonce' ) ) );
			exit;
		} else {
			$get_post = get_post( $post_id );

			if ( ! $get_post || Plugin::CERTIFICATE_POST_TYPE !== $get_post->post_type ) {
				wp_die( __( 'Certificate not found.', 'tutor-lms-certificate-builder' ), 404 );
			}

			$is_preview = isset( $_GET['cert_hash'], $_GET['course_id'] ) && Helper::is_valid_certificate_preview( $post_id );

			// Block users who can't manage this certificate.
			if ( ! $is_preview && ! Helper::can_manage_certificate( $post_id ) ) {
				wp_die( __( 'Sorry, you are not allowed to access this certificate.', 'tutor-lms-certificate-builder' ), 403 );
			}

			if ( 'auto-draft' === $get_post->post_status ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
			}
		}

		$this->_post_id = $post_id;

		// Send MIME Type header like WP admin-header.
		@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );

		query_posts(
			array(
				'p'         => $this->_post_id,
				'post_type' => get_post_type( $this->_post_id ),
			)
		);

		Helper::setup_post_data( $this->_post_id );

		// Remove all WordPress actions.
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_print_styles' );
		remove_all_actions( 'wp_print_head_scripts' );
		remove_all_actions( 'wp_footer' );

		// Handle `wp_head`.
		add_action( 'wp_head', 'wp_enqueue_scripts', 1 );
		add_action( 'wp_head', 'wp_print_styles', 8 );
		add_action( 'wp_head', 'wp_print_head_scripts', 9 );
		add_action( 'wp_head', 'wp_site_icon' );

		// Handle `wp_footer`.
		add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
		add_action( 'wp_footer', 'wp_auth_check_html', 30 );

		// Handle `wp_enqueue_scripts`.
		remove_all_actions( 'wp_enqueue_scripts' );

		// Also remove all scripts hooked into after_wp_tiny_mce.
		remove_all_actions( 'after_wp_tiny_mce' );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_translation' ), 100 );

		do_action( 'tutor_certificate_builder_init' );

		// Print the panel.
		include_once TUTOR_CB_PLUGIN_PATH . 'templates/editor.php';
		die;
	}

	public function enqueue_translation() {
		wp_set_script_translations( 'tutor-lms-certificate-builder-editor-script', 'tutor-lms-certificate-builder', TUTOR_CB_PLUGIN_PATH . 'languages/' );
	}

	/**
	 * Die with a friendly message if the link nonce is missing or expired.
	 *
	 * @since 1.3.3
	 */
	private function verify_link_nonce() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'tutor-lms-certificate-builder' ) ) {
			wp_die(
				esc_html__( 'This link has expired. Please go back, refresh the page, and try again.', 'tutor-lms-certificate-builder' ),
				esc_html__( 'Link expired', 'tutor-lms-certificate-builder' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}
}
