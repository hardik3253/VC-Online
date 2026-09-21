<?php
/**
 * Course Visibility Customization: Hide from Frontend Listings
 *
 * Allows administrators to hide specific Tutor LMS courses from frontend listings,
 * archives, loops, and search results while keeping the single course page accessible
 * via direct URL for testing checkout, payment, and enrollment flows.
 *
 * @package VCOnlineChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * 1. Helper: Get array of course IDs hidden from frontend
 *
 * Uses WordPress object cache for fast execution and minimal database load.
 *
 * @return array
 */
function vc_online_get_hidden_course_ids() {
	$hidden_ids = wp_cache_get( 'vc_online_hidden_course_ids', 'vc_online' );

	if ( false === $hidden_ids ) {
		global $wpdb;
		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'",
			'_tutor_hide_from_frontend'
		) );

		$hidden_ids = ! empty( $results ) ? array_map( 'intval', $results ) : array();
		wp_cache_set( 'vc_online_hidden_course_ids', $hidden_ids, 'vc_online', 3600 );
	}

	return $hidden_ids;
}

/**
 * 2. Helper: Invalidate the hidden course IDs cache on post meta changes
 */
function vc_online_clear_hidden_course_ids_cache() {
	wp_cache_delete( 'vc_online_hidden_course_ids', 'vc_online' );
}
add_action( 'save_post_courses', 'vc_online_clear_hidden_course_ids_cache' );
add_action( 'deleted_post_meta', 'vc_online_clear_hidden_course_ids_cache' );
add_action( 'updated_post_meta', 'vc_online_clear_hidden_course_ids_cache' );
add_action( 'added_post_meta', 'vc_online_clear_hidden_course_ids_cache' );

/**
 * 3. Register custom Meta Box on standard Course edit screen
 */
add_action( 'add_meta_boxes', 'vc_online_register_visibility_metabox' );
function vc_online_register_visibility_metabox() {
	add_meta_box(
		'vc_online_course_visibility_meta',
		__( 'Course Visibility', 'vconline-child' ),
		'vc_online_render_visibility_metabox',
		'courses',
		'side',
		'high'
	);
}

/**
 * 4. Render Meta Box content
 *
 * @param WP_Post $post Current post object.
 */
function vc_online_render_visibility_metabox( $post ) {
	wp_nonce_field( 'vc_online_save_visibility_meta', 'vc_online_visibility_nonce' );

	$is_hidden = get_post_meta( $post->ID, '_tutor_hide_from_frontend', true );
	?>
	<div class="vc-online-visibility-wrapper">
		<p style="margin-bottom: 8px;">
			<label for="vc_online_hide_from_frontend" style="font-weight: 600; cursor: pointer;">
				<input type="checkbox" name="vc_online_hide_from_frontend" id="vc_online_hide_from_frontend" value="1" <?php checked( $is_hidden, '1' ); ?> style="margin-right: 6px;" />
				<?php esc_html_e( 'Hide from Frontend Listings', 'vconline-child' ); ?>
			</label>
		</p>
		<p class="description" style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.4;">
			<?php esc_html_e( 'When enabled, this course is hidden from course archives, taxonomy listings, search results, and course loops. The single course page remains fully accessible via direct link for checkout testing.', 'vconline-child' ); ?>
		</p>
	</div>
	<?php
}

/**
 * 5. Save Meta Box setting on course update
 *
 * @param int $post_id Post ID.
 */
add_action( 'save_post_courses', 'vc_online_save_visibility_metabox' );
function vc_online_save_visibility_metabox( $post_id ) {
	if ( ! isset( $_POST['vc_online_visibility_nonce'] ) || ! wp_verify_nonce( $_POST['vc_online_visibility_nonce'], 'vc_online_save_visibility_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$hide = ( isset( $_POST['vc_online_hide_from_frontend'] ) && '1' === (string) $_POST['vc_online_hide_from_frontend'] ) ? '1' : '0';

	if ( '1' === $hide ) {
		update_post_meta( $post_id, '_tutor_hide_from_frontend', '1' );
	} else {
		delete_post_meta( $post_id, '_tutor_hide_from_frontend' );
	}

	vc_online_clear_hidden_course_ids_cache();
}

/**
 * 6. Admin AJAX handlers for Tutor LMS Course Builder integration
 */
add_action( 'wp_ajax_vc_online_get_course_visibility', 'vc_online_ajax_get_course_visibility' );
function vc_online_ajax_get_course_visibility() {
	check_ajax_referer( 'vco_badge_nonce', 'nonce' );

	$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
	if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
		wp_send_json_error( 'Invalid course ID or permission denied.' );
	}

	$is_hidden = get_post_meta( $course_id, '_tutor_hide_from_frontend', true );

	wp_send_json_success( array(
		'hide_from_frontend' => '1' === (string) $is_hidden,
	) );
}

add_action( 'wp_ajax_vc_online_save_course_visibility', 'vc_online_ajax_save_course_visibility' );
function vc_online_ajax_save_course_visibility() {
	check_ajax_referer( 'vco_badge_nonce', 'nonce' );

	$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
	if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
		wp_send_json_error( 'Invalid course ID or permission denied.' );
	}

	$hide = ( isset( $_POST['hide'] ) && 'true' === (string) $_POST['hide'] ) ? '1' : '0';

	if ( '1' === $hide ) {
		update_post_meta( $course_id, '_tutor_hide_from_frontend', '1' );
	} else {
		delete_post_meta( $course_id, '_tutor_hide_from_frontend' );
	}

	vc_online_clear_hidden_course_ids_cache();

	wp_send_json_success();
}

/**
 * 7. Add Visibility Column to Admin Courses List Table
 */
add_filter( 'manage_courses_posts_columns', 'vc_online_add_visibility_column' );
function vc_online_add_visibility_column( $columns ) {
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		if ( 'course_badge' === $key || 'title' === $key ) {
			$new_columns['course_visibility'] = __( 'Visibility', 'vconline-child' );
		}
	}
	if ( ! isset( $new_columns['course_visibility'] ) ) {
		$new_columns['course_visibility'] = __( 'Visibility', 'vconline-child' );
	}
	return $new_columns;
}

add_action( 'manage_courses_posts_custom_column', 'vc_online_display_visibility_column', 10, 2 );
function vc_online_display_visibility_column( $column, $post_id ) {
	if ( 'course_visibility' === $column ) {
		$is_hidden = get_post_meta( $post_id, '_tutor_hide_from_frontend', true );
		if ( '1' === (string) $is_hidden ) {
			echo '<span class="vc-online-hidden-badge" data-hidden="1" style="display: inline-block; background: #fee2e2; color: #b91c1c; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 11px; letter-spacing: 0.3px;">' . esc_html__( 'Hidden', 'vconline-child' ) . '</span>';
		} else {
			echo '<span class="vc-online-hidden-badge" data-hidden="0" style="color: #64748b; font-size: 12px;">' . esc_html__( 'Visible', 'vconline-child' ) . '</span>';
		}
	}
}

/**
 * 8. Quick Edit Integration for Course Visibility
 */
add_action( 'quick_edit_custom_box', 'vc_online_quick_edit_visibility', 10, 2 );
function vc_online_quick_edit_visibility( $column_name, $post_type ) {
	if ( 'courses' !== $post_type || 'course_visibility' !== $column_name ) {
		return;
	}
	wp_nonce_field( 'vc_online_quick_edit_visibility_action', 'vc_online_quick_edit_visibility_nonce' );
	?>
	<fieldset class="inline-edit-col-right" style="margin-top: 6px;">
		<div class="inline-edit-col">
			<label class="alignleft" style="cursor: pointer; display: inline-flex; align-items: center;">
				<input type="checkbox" name="vc_online_hide_from_frontend" class="quick-edit-hide-frontend" value="1" style="margin-right: 6px;" />
				<span class="checkbox-title" style="font-weight: 600;"><?php esc_html_e( 'Hide from Frontend Listings', 'vconline-child' ); ?></span>
			</label>
		</div>
	</fieldset>
	<?php
}

add_action( 'save_post_courses', 'vc_online_save_quick_edit_visibility' );
function vc_online_save_quick_edit_visibility( $post_id ) {
	if ( ! isset( $_POST['vc_online_quick_edit_visibility_nonce'] ) || ! wp_verify_nonce( $_POST['vc_online_quick_edit_visibility_nonce'], 'vc_online_quick_edit_visibility_action' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$hide = ( isset( $_POST['vc_online_hide_from_frontend'] ) && '1' === (string) $_POST['vc_online_hide_from_frontend'] ) ? '1' : '0';

	if ( '1' === $hide ) {
		update_post_meta( $post_id, '_tutor_hide_from_frontend', '1' );
	} else {
		delete_post_meta( $post_id, '_tutor_hide_from_frontend' );
	}

	vc_online_clear_hidden_course_ids_cache();
}

/**
 * 9. Core Filter: Exclude hidden courses from frontend queries via pre_get_posts
 *
 * Excludes hidden courses from:
 * - Tutor LMS course archive pages
 * - WordPress search results
 * - Tutor LMS taxonomy/category archives
 * - Elementor course grids/loops using WP_Query
 * - Generic frontend course loops and carousels
 *
 * Safely preserves:
 * - Single course page (/courses/test-course/)
 * - Direct URL access & checkout flows
 * - Admin course management & edit screens
 * - Elementor editor mode
 * - Student dashboard enrolled courses
 * - WooCommerce products, shop, cart, and orders
 *
 * @param WP_Query $query WordPress query object.
 */
add_action( 'pre_get_posts', 'vc_online_exclude_hidden_courses_pre_get_posts', 99 );
function vc_online_exclude_hidden_courses_pre_get_posts( $query ) {
	// A. Never filter in the WordPress admin area (unless it is a frontend AJAX query)
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}

	// B. Never filter inside Elementor editor preview mode
	if ( did_action( 'elementor/loaded' ) ) {
		if ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}
	}

	// C. Never filter single post / page queries (direct course URL must ALWAYS load)
	if ( $query->is_single() || $query->is_singular() ) {
		return;
	}

	// If query is specifically requesting a single post by ID or slug, do not filter
	if ( $query->get( 'p' ) || $query->get( 'name' ) ) {
		return;
	}

	// D. Never filter student dashboard enrolled courses query
	if ( function_exists( 'tutor_utils' ) && tutor_utils()->is_tutor_frontend_dashboard() ) {
		return;
	}
	if ( $query->get( 'tutor_is_enrolled_query' ) ) {
		return;
	}

	// E. Get hidden course IDs
	$hidden_ids = vc_online_get_hidden_course_ids();
	if ( empty( $hidden_ids ) ) {
		return;
	}

	// F. Verify whether this query targets courses or is a frontend search
	$post_type       = $query->get( 'post_type' );
	$is_course_query = false;

	if ( $query->is_search() ) {
		// Global frontend search results
		$is_course_query = true;
	} elseif ( $query->is_post_type_archive( 'courses' ) || $query->is_tax( array( 'course-category', 'course-tag' ) ) ) {
		$is_course_query = true;
	} elseif ( ! empty( $post_type ) ) {
		if ( is_array( $post_type ) && in_array( 'courses', $post_type, true ) ) {
			$is_course_query = true;
		} elseif ( 'courses' === $post_type || 'any' === $post_type ) {
			$is_course_query = true;
		}
	} elseif ( $query->is_main_query() && ( is_post_type_archive( 'courses' ) || is_tax( array( 'course-category', 'course-tag' ) ) ) ) {
		$is_course_query = true;
	}

	if ( ! $is_course_query ) {
		return;
	}

	// G. Apply exclusion via post__not_in
	$post__not_in = (array) $query->get( 'post__not_in' );
	$query->set( 'post__not_in', array_unique( array_merge( $post__not_in, $hidden_ids ) ) );

	// H. If query explicitly specifies post__in, strip out the hidden courses
	$post__in = $query->get( 'post__in' );
	if ( ! empty( $post__in ) && is_array( $post__in ) ) {
		$query->set( 'post__in', array_values( array_diff( $post__in, $hidden_ids ) ) );
	}
}

/**
 * 10. Filter Tutor LMS course filter arguments (AJAX & archive filter)
 *
 * Hooks into tutor_course_filter_args to exclude hidden courses from Tutor LMS
 * AJAX filtering and Elementor Tutor addons (course-list, course-carousel).
 *
 * @param array $args Query arguments array.
 * @return array
 */
add_filter( 'tutor_course_filter_args', 'vc_online_filter_tutor_course_args', 99 );
function vc_online_filter_tutor_course_args( $args ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $args;
	}

	if ( did_action( 'elementor/loaded' ) ) {
		if ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $args;
		}
	}

	$hidden_ids = vc_online_get_hidden_course_ids();
	if ( empty( $hidden_ids ) ) {
		return $args;
	}

	$not_in = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
	$args['post__not_in'] = array_unique( array_merge( $not_in, $hidden_ids ) );

	if ( ! empty( $args['post__in'] ) && is_array( $args['post__in'] ) ) {
		$args['post__in'] = array_values( array_diff( $args['post__in'], $hidden_ids ) );
	}

	return $args;
}

/**
 * 11. Flag enrolled courses queries so enrolled students can always see their courses
 *
 * When an enrolled student views their purchased courses in their dashboard,
 * we flag the query so it is never excluded even if the course is hidden from frontend listings.
 *
 * @param array  $args Course query arguments.
 * @param int    $user_id User ID.
 * @param string $post_type Post type.
 * @return array
 */
add_filter( 'tutor_get_enrolled_courses_by_user', 'vc_online_flag_enrolled_courses_query', 10, 3 );
function vc_online_flag_enrolled_courses_query( $args, $user_id, $post_type ) {
	$args['tutor_is_enrolled_query'] = true;
	return $args;
}
