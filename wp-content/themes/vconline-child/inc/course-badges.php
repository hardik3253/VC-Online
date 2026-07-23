<?php
/**
 * Tutor LMS Custom Course Badges
 * Handles admin meta boxes, Quick Edit, Course Builder integration, and renders badges on course cards/single pages with custom colors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add custom meta box for Tutor LMS Course Badge (for standard editors/fallback)
 */
add_action( 'add_meta_boxes', 'vco_add_course_badge_meta_box' );
function vco_add_course_badge_meta_box() {
	add_meta_box(
		'vco_course_badge_meta',
		__( 'Course Badge Settings', 'vconline-child' ),
		'vco_render_course_badge_meta_box',
		'courses',
		'side',
		'high'
	);
}

/**
 * Render the meta box content
 */
function vco_render_course_badge_meta_box( $post ) {
	wp_nonce_field( 'vco_save_course_badge', 'vco_course_badge_nonce' );

	$enable_badge = get_post_meta( $post->ID, '_tutor_course_enable_badge', true );
	$badge_text   = get_post_meta( $post->ID, '_tutor_course_badge_text', true );
	$text_color   = get_post_meta( $post->ID, '_tutor_course_badge_text_color', true );
	$bg_color     = get_post_meta( $post->ID, '_tutor_course_badge_bg_color', true );

	$text_color = $text_color ? $text_color : '#ffffff';
	$bg_color   = $bg_color ? $bg_color : '#E03E3E';
	?>
	<div class="vco-meta-box-wrapper">
		<p style="margin-bottom: 15px;">
			<label for="vco_enable_badge">
				<input type="checkbox" name="vco_enable_badge" id="vco_enable_badge" value="yes" <?php checked( $enable_badge, 'yes' ); ?> />
				<strong><?php esc_html_e( 'Enable Badge', 'vconline-child' ); ?></strong>
			</label>
		</p>
		<p style="margin-bottom: 15px;">
			<label for="vco_badge_text" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Badge Text', 'vconline-child' ); ?></label>
			<input type="text" name="vco_badge_text" id="vco_badge_text" value="<?php echo esc_attr( $badge_text ); ?>" class="widefat" placeholder="e.g. New, Free, Hot" />
		</p>
		<p style="margin-bottom: 15px;">
			<label for="vco_badge_text_color" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Text Color', 'vconline-child' ); ?></label>
			<input type="color" name="vco_badge_text_color" id="vco_badge_text_color" value="<?php echo esc_attr( $text_color ); ?>" style="width: 50px; height: 30px; padding: 0; border: none; cursor: pointer;" />
		</p>
		<p>
			<label for="vco_badge_bg_color" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e( 'Background Color', 'vconline-child' ); ?></label>
			<input type="color" name="vco_badge_bg_color" id="vco_badge_bg_color" value="<?php echo esc_attr($bg_color); ?>" style="width: 50px; height: 30px; padding: 0; border: none; cursor: pointer;" />
		</p>
	</div>
	<?php
}

/**
 * Save meta box values securely
 */
add_action( 'save_post_courses', 'vco_save_course_badge_meta' );
function vco_save_course_badge_meta( $post_id ) {
	if ( ! isset( $_POST['vco_course_badge_nonce'] ) || ! wp_verify_nonce( $_POST['vco_course_badge_nonce'], 'vco_save_course_badge' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$enable_badge = isset( $_POST['vco_enable_badge'] ) && $_POST['vco_enable_badge'] === 'yes' ? 'yes' : 'no';
	update_post_meta( $post_id, '_tutor_course_enable_badge', $enable_badge );

	if ( isset( $_POST['vco_badge_text'] ) ) {
		$badge_text = sanitize_text_field( wp_unslash( $_POST['vco_badge_text'] ) );
		update_post_meta( $post_id, '_tutor_course_badge_text', $badge_text );
	}

	if ( isset( $_POST['vco_badge_text_color'] ) ) {
		update_post_meta( $post_id, '_tutor_course_badge_text_color', sanitize_hex_color( $_POST['vco_badge_text_color'] ) );
	}

	if ( isset( $_POST['vco_badge_bg_color'] ) ) {
		update_post_meta( $post_id, '_tutor_course_badge_bg_color', sanitize_hex_color( $_POST['vco_badge_bg_color'] ) );
	}
}


/**
 * Add Badge columns to Courses list table
 */
add_filter( 'manage_courses_posts_columns', 'vco_add_course_badge_columns' );
function vco_add_course_badge_columns( $columns ) {
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		if ( 'author' === $key ) {
			$new_columns['course_badge'] = __( 'Badge', 'vconline-child' );
		}
		$new_columns[$key] = $value;
	}
	if ( ! isset( $new_columns['course_badge'] ) ) {
		$new_columns['course_badge'] = __( 'Badge', 'vconline-child' );
	}
	return $new_columns;
}

/**
 * Display Badge column content
 */
add_action( 'manage_courses_posts_custom_column', 'vco_display_course_badge_columns', 10, 2 );
function vco_display_course_badge_columns( $column, $post_id ) {
	if ( 'course_badge' === $column ) {
		$enable_badge = get_post_meta( $post_id, '_tutor_course_enable_badge', true );
		$badge_text   = get_post_meta( $post_id, '_tutor_course_badge_text', true );
		$text_color   = get_post_meta( $post_id, '_tutor_course_badge_text_color', true );
		$bg_color     = get_post_meta( $post_id, '_tutor_course_badge_bg_color', true );

		$text_color = $text_color ? $text_color : '#ffffff';
		$bg_color   = $bg_color ? $bg_color : '#E03E3E';

		if ( 'yes' === $enable_badge && ! empty( $badge_text ) ) {
			echo '<span class="badge-preview" data-enable="yes" data-text="' . esc_attr( $badge_text ) . '" data-textcolor="' . esc_attr( $text_color ) . '" data-bgcolor="' . esc_attr( $bg_color ) . '" style="background: ' . esc_attr( $bg_color ) . '; color: ' . esc_attr( $text_color ) . '; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;">' . esc_html( $badge_text ) . '</span>';
		} else {
			echo '<span class="badge-preview" data-enable="no" data-text="" data-textcolor="' . esc_attr( $text_color ) . '" data-bgcolor="' . esc_attr( $bg_color ) . '" style="color: #bbb;">—</span>';
		}
	}
}

/**
 * Add custom fields to Quick Edit panel
 */
add_action( 'quick_edit_custom_box', 'vco_add_course_badge_quick_edit', 10, 2 );
function vco_add_course_badge_quick_edit( $column_name, $post_type ) {
	if ( 'courses' !== $post_type || 'course_badge' !== $column_name ) {
		return;
	}
	
	wp_nonce_field( 'vco_quick_edit_nonce', 'vco_quick_edit_nonce_field' );
	?>
	<fieldset class="inline-edit-col-right" style="margin-top: 10px;">
		<div class="inline-edit-col">
			<span class="title" style="font-weight: bold;"><?php esc_html_e( 'Course Badge', 'vconline-child' ); ?></span>
			
			<div class="inline-edit-group" style="margin-top: 5px;">
				<label align="left" style="display: inline-block; margin-right: 15px; cursor: pointer;">
					<input type="checkbox" name="vco_enable_badge" class="quick-edit-enable-badge" value="yes" />
					<span class="checkbox-title" style="font-weight: normal;"><?php esc_html_e( 'Enable Badge', 'vconline-child' ); ?></span>
				</label>
				
				<label style="display: inline-block; cursor: pointer; margin-right: 15px;">
					<span class="title" style="font-weight: normal; margin-right: 5px;"><?php esc_html_e( 'Text:', 'vconline-child' ); ?></span>
					<input type="text" name="vco_badge_text" class="quick-edit-badge-text" value="" style="width: 100px;" placeholder="e.g. New" />
				</label>

				<label style="display: inline-block; cursor: pointer; margin-right: 15px;">
					<span class="title" style="font-weight: normal; margin-right: 5px;"><?php esc_html_e( 'Text Color:', 'vconline-child' ); ?></span>
					<input type="color" name="vco_badge_text_color" class="quick-edit-badge-text-color" value="#ffffff" style="vertical-align: middle; width: 40px; height: 25px; padding: 0; border: none; cursor: pointer;" />
				</label>

				<label style="display: inline-block; cursor: pointer;">
					<span class="title" style="font-weight: normal; margin-right: 5px;"><?php esc_html_e( 'Bg Color:', 'vconline-child' ); ?></span>
					<input type="color" name="vco_badge_bg_color" class="quick-edit-badge-bg-color" value="#E03E3E" style="vertical-align: middle; width: 40px; height: 25px; padding: 0; border: none; cursor: pointer;" />
				</label>
			</div>
		</div>
	</fieldset>
	<?php
}

/**
 * Save Quick Edit fields
 */
add_action( 'save_post_courses', 'vco_save_course_badge_quick_edit' );
function vco_save_course_badge_quick_edit( $post_id ) {
	if ( ! isset( $_POST['vco_quick_edit_nonce_field'] ) || ! wp_verify_nonce( $_POST['vco_quick_edit_nonce_field'], 'vco_quick_edit_nonce' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$enable_badge = isset( $_POST['vco_enable_badge'] ) && $_POST['vco_enable_badge'] === 'yes' ? 'yes' : 'no';
	update_post_meta( $post_id, '_tutor_course_enable_badge', $enable_badge );

	if ( isset( $_POST['vco_badge_text'] ) ) {
		$badge_text = sanitize_text_field( wp_unslash( $_POST['vco_badge_text'] ) );
		update_post_meta( $post_id, '_tutor_course_badge_text', $badge_text );
	}

	if ( isset( $_POST['vco_badge_text_color'] ) ) {
		update_post_meta( $post_id, '_tutor_course_badge_text_color', sanitize_hex_color( $_POST['vco_badge_text_color'] ) );
	}

	if ( isset( $_POST['vco_badge_bg_color'] ) ) {
		update_post_meta( $post_id, '_tutor_course_badge_bg_color', sanitize_hex_color( $_POST['vco_badge_bg_color'] ) );
	}
}

/**
 * Load script to populate Quick Edit values on click
 */
add_action( 'admin_enqueue_scripts', 'vco_enqueue_quick_edit_script' );
function vco_enqueue_quick_edit_script( $hook ) {
	global $post_type;
	if ( 'edit.php' === $hook && 'courses' === $post_type ) {
		wp_enqueue_script( 'vco-quick-edit-js', get_stylesheet_directory_uri() . '/js/admin-quick-edit.js', array( 'jquery', 'inline-edit-post' ), '1.0', true );
	}
}


/**
 * Enqueue settings insertion script for Tutor LMS Course Builder
 */
add_action( 'admin_enqueue_scripts', 'vco_enqueue_builder_script' );
add_action( 'wp_enqueue_scripts', 'vco_enqueue_builder_script' );
function vco_enqueue_builder_script() {
	$is_builder = false;
	$course_id = 0;

	if ( is_admin() ) {
		global $pagenow;
		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
			if ( 'courses' === get_post_type( $post_id ) ) {
				$is_builder = true;
				$course_id = $post_id;
			}
		} elseif ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'create-course' === $_GET['page'] ) {
			$is_builder = true;
			$course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;
		}
	} else {
		if ( tutor_utils()->is_tutor_frontend_dashboard( 'create-course' ) ) {
			$is_builder = true;
			$course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;
		}
	}

	if ( $is_builder && $course_id ) {
		wp_enqueue_script( 'vco-builder-integration', get_stylesheet_directory_uri() . '/js/admin-course-builder.js', array( 'jquery' ), '1.2', true );
		wp_localize_script( 'vco-builder-integration', 'vcoBuilderObj', array(
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'course_id' => $course_id,
			'nonce'     => wp_create_nonce( 'vco_badge_nonce' ),
		) );
	}
}

/**
 * AJAX Handler to get badge settings for a course
 */
add_action( 'wp_ajax_vco_get_course_badge', 'vco_ajax_get_course_badge' );
function vco_ajax_get_course_badge() {
	check_ajax_referer( 'vco_badge_nonce', 'nonce' );
	
	$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
	if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
		wp_send_json_error( 'Invalid course ID.' );
	}

	$enable_badge = get_post_meta( $course_id, '_tutor_course_enable_badge', true );
	$badge_text   = get_post_meta( $course_id, '_tutor_course_badge_text', true );
	$text_color   = get_post_meta( $course_id, '_tutor_course_badge_text_color', true );
	$bg_color     = get_post_meta( $course_id, '_tutor_course_badge_bg_color', true );

	wp_send_json_success( array(
		'enable'    => $enable_badge === 'yes',
		'text'      => $badge_text ? $badge_text : '',
		'textcolor' => $text_color ? $text_color : '#ffffff',
		'bgcolor'   => $bg_color ? $bg_color : '#E03E3E',
	) );
}

/**
 * AJAX Handler to save badge settings for a course
 */
add_action( 'wp_ajax_vco_save_course_badge', 'vco_ajax_save_course_badge' );
function vco_ajax_save_course_badge() {
	check_ajax_referer( 'vco_badge_nonce', 'nonce' );

	$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
	if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
		wp_send_json_error( 'Invalid course ID.' );
	}

	$enable_badge = isset( $_POST['enable'] ) && $_POST['enable'] === 'true' ? 'yes' : 'no';
	$badge_text   = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';
	$textcolor    = isset( $_POST['textcolor'] ) ? sanitize_hex_color( $_POST['textcolor'] ) : '#ffffff';
	$bgcolor      = isset( $_POST['bgcolor'] ) ? sanitize_hex_color( $_POST['bgcolor'] ) : '#E03E3E';

	update_post_meta( $course_id, '_tutor_course_enable_badge', $enable_badge );
	update_post_meta( $course_id, '_tutor_course_badge_text', $badge_text );
	update_post_meta( $course_id, '_tutor_course_badge_text_color', $textcolor );
	update_post_meta( $course_id, '_tutor_course_badge_bg_color', $bgcolor );

	wp_send_json_success();
}


/**
 * Display the badge on Tutor LMS course card loop (archives & sliders)
 */
add_action( 'tutor_course/loop/before_title', 'vco_display_course_loop_badge' );
function vco_display_course_loop_badge() {
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
}

/**
 * Display the badge on Single Course details page (title area)
 */
add_action( 'tutor_course/single/title/before', 'vco_display_single_course_badge' );
function vco_display_single_course_badge() {
	$course_id = get_the_ID();
	$enable_badge = get_post_meta( $course_id, '_tutor_course_enable_badge', true );
	$badge_text   = get_post_meta( $course_id, '_tutor_course_badge_text', true );
	$text_color   = get_post_meta( $course_id, '_tutor_course_badge_text_color', true );
	$bg_color     = get_post_meta( $course_id, '_tutor_course_badge_bg_color', true );

	if ( 'yes' === $enable_badge && ! empty( $badge_text ) ) {
		$text_color = $text_color ? $text_color : '#ffffff';
		$bg_color   = $bg_color ? $bg_color : '#E03E3E';
		echo '<span class="tutor-course-custom-badge single-course-badge" style="color: ' . esc_attr( $text_color ) . '; background: ' . esc_attr( $bg_color ) . ';">' . esc_html( $badge_text ) . '</span>';
	}
}

/**
 * Filter Elementor template path to override ratings.php in child theme
 */
add_filter( 'etlms_get_template_path', 'vco_etlms_template_override', 10, 2 );
function vco_etlms_template_override( $template_location, $template ) {
	if ( 'course/list/parts/ratings' === $template ) {
		$child_path = get_stylesheet_directory() . '/tutor/elementor/course/list/parts/ratings.php';
		if ( file_exists( $child_path ) ) {
			return $child_path;
		}
	}
	return $template_location;
}

