<?php
/**
 * Custom Shortcode: Latest Courses with Modern Design
 *
 * Provides shortcodes [vc_latest_courses] and [vc_latest_course] to display
 * the latest Tutor LMS courses with course image, title, price, rating,
 * category, custom badge, and Tutor LMS action button.
 *
 * @package VCOnlineChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register & Enqueue Assets for Latest Courses Shortcode
 */
add_action( 'wp_enqueue_scripts', 'vco_enqueue_latest_courses_assets' );
function vco_enqueue_latest_courses_assets() {
	wp_enqueue_style(
		'vco-latest-courses-style',
		get_stylesheet_directory_uri() . '/css/latest-courses-shortcode.css',
		array(),
		'1.0.2'
	);
}

/**
 * Main Shortcode Handler
 *
 * Usage:
 * [vc_latest_courses] (displays latest single featured course banner)
 * [vc_latest_courses count="3" layout="grid"] (displays 3 courses in modern grid)
 * [vc_latest_courses id="832"] (displays specific course)
 * [vc_latest_courses category="makeup"] (filters by category slug)
 */
function vco_latest_courses_shortcode_handler( $atts ) {
	// Enqueue CSS when shortcode is executed
	wp_enqueue_style( 'vco-latest-courses-style' );

	$atts = shortcode_atts(
		array(
			'count'              => 1,
			'id'                 => 0,
			'category'           => '',
			'layout'             => '', // 'horizontal', 'grid', or empty (auto: 1 = horizontal, >1 = grid)
			'columns'            => 3,
			'orderby'            => 'date',
			'order'              => 'DESC',
			'show_image'         => 'yes',
			'show_category'      => 'yes',
			'show_badge'         => 'yes',
			'show_rating'        => 'yes',
			'show_price'         => 'yes',
			'show_button'        => 'yes',
			'show_meta'          => 'yes',
			'show_secondary_btn' => 'yes',
			'bg_color'           => '',
			'class'              => '',
		),
		$atts,
		'vc_latest_courses'
	);

	$count   = max( 1, intval( $atts['count'] ) );
	$post_id = intval( $atts['id'] );

	// Determine layout
	$layout = ! empty( $atts['layout'] ) ? sanitize_key( $atts['layout'] ) : ( $count === 1 ? 'horizontal' : 'grid' );

	// Query arguments
	$query_args = array(
		'post_type'      => 'courses',
		'post_status'    => 'publish',
		'posts_per_page' => $count,
		'orderby'        => sanitize_key( $atts['orderby'] ),
		'order'          => strtoupper( $atts['order'] ) === 'ASC' ? 'ASC' : 'DESC',
	);

	// Query by specific ID if supplied
	if ( $post_id > 0 ) {
		$query_args['p']              = $post_id;
		$query_args['posts_per_page'] = 1;
	} else {
		// Filter out courses hidden from frontend listings if function exists
		if ( function_exists( 'vc_online_get_hidden_course_ids' ) ) {
			$hidden_ids = vc_online_get_hidden_course_ids();
			if ( ! empty( $hidden_ids ) ) {
				$query_args['post__not_in'] = $hidden_ids;
			}
		}

		// Filter by category if supplied
		if ( ! empty( $atts['category'] ) ) {
			$tax_query = array(
				array(
					'taxonomy' => 'course-category',
					'field'    => is_numeric( $atts['category'] ) ? 'term_id' : 'slug',
					'terms'    => is_numeric( $atts['category'] ) ? intval( $atts['category'] ) : sanitize_text_field( $atts['category'] ),
				),
			);
			$query_args['tax_query'] = $tax_query;
		}
	}

	$courses_query = new WP_Query( $query_args );

	if ( ! $courses_query->have_posts() ) {
		return '';
	}

	$custom_bg_style = ! empty( $atts['bg_color'] ) ? ' style="--vco-lc-bg: ' . esc_attr( $atts['bg_color'] ) . ';"' : '';
	$wrapper_classes = 'vco-latest-courses-wrap vco-layout-' . esc_attr( $layout );
	if ( ! empty( $atts['class'] ) ) {
		$wrapper_classes .= ' ' . esc_attr( $atts['class'] );
	}

	ob_start();
	?>
	<div class="<?php echo esc_attr( $wrapper_classes ); ?>"<?php echo $custom_bg_style; ?>>
		<?php if ( 'grid' === $layout && $courses_query->post_count > 1 ) : ?>
			<div class="vco-courses-grid" style="grid-template-columns: repeat(auto-fill, minmax(<?php echo intval( $atts['columns'] ) >= 4 ? '260px' : '310px'; ?>, 1fr));">
		<?php endif; ?>

		<?php
		while ( $courses_query->have_posts() ) :
			$courses_query->the_post();
			$course_id = get_the_ID();
			vco_render_single_latest_course_card( $course_id, $layout, $atts );
		endwhile;
		wp_reset_postdata();
		?>

		<?php if ( 'grid' === $layout && $courses_query->post_count > 1 ) : ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

// Register all shortcode aliases
add_shortcode( 'vc_latest_courses', 'vco_latest_courses_shortcode_handler' );
add_shortcode( 'vc_latest_course', 'vco_latest_courses_shortcode_handler' );
add_shortcode( 'vco_latest_courses', 'vco_latest_courses_shortcode_handler' );
add_shortcode( 'vco_latest_course', 'vco_latest_courses_shortcode_handler' );

/**
 * Render a Single Course Card
 *
 * @param int    $course_id Course post ID.
 * @param string $layout    'horizontal' or 'grid'.
 * @param array  $atts      Shortcode attributes.
 */
function vco_render_single_latest_course_card( $course_id, $layout, $atts ) {
	$permalink    = get_the_permalink( $course_id );
	$course_title = get_the_title( $course_id );

	// 1. Course Image
	$thumbnail_url = get_the_post_thumbnail_url( $course_id, 'large' );
	if ( ! $thumbnail_url && function_exists( 'tutor_utils' ) ) {
		$thumbnail_url = tutor_utils()->get_tutor_course_thumbnail_src();
	}
	if ( ! $thumbnail_url ) {
		$thumbnail_url = 'https://via.placeholder.com/640x360?text=Course+Image';
	}

	// 2. Category
	$categories   = wp_get_post_terms( $course_id, 'course-category' );
	$primary_cat  = ! empty( $categories ) && ! is_wp_error( $categories ) ? $categories[0] : null;

	// 3. Custom Badge (Popular, Free, Hot, etc.)
	$enable_badge = get_post_meta( $course_id, '_tutor_course_enable_badge', true );
	$badge_text   = get_post_meta( $course_id, '_tutor_course_badge_text', true );
	$badge_text_c = get_post_meta( $course_id, '_tutor_course_badge_text_color', true ) ?: '#ffffff';
	$badge_bg_c   = get_post_meta( $course_id, '_tutor_course_badge_bg_color', true ) ?: '#f59e0b';
	$has_badge    = ( 'yes' === $enable_badge && ! empty( $badge_text ) );

	// 4. Rating & Reviews
	$course_rating = function_exists( 'tutor_utils' ) ? tutor_utils()->get_course_rating( $course_id ) : null;
	$rating_avg    = $course_rating && isset( $course_rating->rating_avg ) ? (float) $course_rating->rating_avg : 0.0;
	$rating_count  = $course_rating && isset( $course_rating->rating_count ) ? (int) $course_rating->rating_count : 0;
	$formatted_avg = $rating_avg > 0 ? number_format( $rating_avg, 1, '.', '' ) : '5.0';

	// 5. Pricing Details
	$price_data = vco_get_course_pricing_data( $course_id );

	// 6. Meta info (Duration, Level)
	$duration_meta = get_post_meta( $course_id, '_course_duration', true );
	$duration_str  = '';
	if ( is_array( $duration_meta ) ) {
		$h = ! empty( $duration_meta['hours'] ) ? intval( $duration_meta['hours'] ) : 0;
		$m = ! empty( $duration_meta['minutes'] ) ? intval( $duration_meta['minutes'] ) : 0;
		if ( $h > 0 && $m > 0 ) {
			$duration_str = sprintf( '%dh %dm', $h, $m );
		} elseif ( $h > 0 ) {
			$duration_str = sprintf( '%d hours', $h );
		} elseif ( $m > 0 ) {
			$duration_str = sprintf( '%d mins', $m );
		}
	}

	$level_meta = get_post_meta( $course_id, '_tutor_course_level', true );
	$level_labels = array(
		'all_levels'   => __( 'All Levels', 'vconline-child' ),
		'beginner'     => __( 'Beginner', 'vconline-child' ),
		'intermediate' => __( 'Intermediate', 'vconline-child' ),
		'expert'       => __( 'Expert', 'vconline-child' ),
	);
	$level_str = ! empty( $level_meta ) && isset( $level_labels[ $level_meta ] ) ? $level_labels[ $level_meta ] : '';

	// 7. Tutor LMS Action Button Details
	$btn_data = vco_get_course_button_data( $course_id );
	?>
	<div class="vco-course-card vco-layout-<?php echo esc_attr( $layout ); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>">
		
		<?php if ( 'no' !== $atts['show_image'] ) : ?>
		<div class="vco-card-media">
			<a href="<?php echo esc_url( $permalink ); ?>" class="vco-media-inner" aria-label="<?php echo esc_attr( $course_title ); ?>">
				<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $course_title ); ?>" class="vco-media-img" loading="lazy" />
			</a>
		</div>
		<?php endif; ?>

		<div class="vco-card-body">
			<div class="vco-card-header">
				<div class="vco-badges-group">
					<?php if ( 'no' !== $atts['show_category'] && $primary_cat ) : ?>
						<a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>" class="vco-category-pill">
							<?php echo esc_html( $primary_cat->name ); ?>
						</a>
					<?php endif; ?>

					<?php if ( 'no' !== $atts['show_badge'] && $has_badge ) : ?>
						<span class="vco-highlight-badge" style="background-color: <?php echo esc_attr( $badge_bg_c ); ?>; color: <?php echo esc_attr( $badge_text_c ); ?>;">
							<?php echo esc_html( $badge_text ); ?>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( 'no' !== $atts['show_rating'] ) : ?>
				<div class="vco-rating-pill">
					<div class="vco-stars-list">
						<?php echo vco_render_star_rating_svg( $rating_avg > 0 ? $rating_avg : 5.0 ); ?>
					</div>
					<span class="vco-rating-val"><?php echo esc_html( $formatted_avg ); ?></span>
					<span class="vco-rating-count">(<?php echo esc_html( $rating_count ); ?>)</span>
				</div>
				<?php endif; ?>
			</div>

			<div class="vco-card-title-wrap">
				<h3 class="vco-course-title">
					<a href="<?php echo esc_url( $permalink ); ?>">
						<?php echo esc_html( $course_title ); ?>
					</a>
				</h3>
			</div>

			<?php if ( 'no' !== $atts['show_meta'] && ( $duration_str || $level_str ) ) : ?>
			<div class="vco-course-meta-row">
				<?php if ( $duration_str ) : ?>
					<span class="vco-meta-pill">
						<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						<?php echo esc_html( $duration_str ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $level_str ) : ?>
					<span class="vco-meta-pill">
						<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
						<?php echo esc_html( $level_str ); ?>
					</span>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="vco-card-footer">
				<?php if ( 'no' !== $atts['show_price'] ) : ?>
				<div class="vco-price-block">
					<span class="vco-price-current"><?php echo esc_html( $price_data['current_price'] ); ?></span>
					<?php if ( ! empty( $price_data['regular_price'] ) ) : ?>
						<del class="vco-price-regular"><?php echo esc_html( $price_data['regular_price'] ); ?></del>
					<?php endif; ?>
					<?php if ( ! empty( $price_data['discount_tag'] ) ) : ?>
						<span class="vco-discount-tag"><?php echo esc_html( $price_data['discount_tag'] ); ?></span>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( 'no' !== $atts['show_button'] ) : ?>
				<div class="vco-actions-block">
					<a href="<?php echo esc_url( $btn_data['url'] ); ?>" 
					   class="vco-btn-primary <?php echo esc_attr( $btn_data['class'] ); ?>"
					   <?php echo ! empty( $btn_data['attrs'] ) ? $btn_data['attrs'] : ''; ?>>
						<?php echo esc_html( $btn_data['label'] ); ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</a>

					<?php if ( 'no' !== $atts['show_secondary_btn'] && 'Continue Learning' !== $btn_data['label'] ) : ?>
						<a href="<?php echo esc_url( $permalink ); ?>" class="vco-btn-secondary">
							<?php esc_html_e( 'Details', 'vconline-child' ); ?>
						</a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Helper: Retrieve formatted price, regular price strikethrough, and discount tag
 *
 * @param int $course_id
 * @return array
 */
function vco_get_course_pricing_data( $course_id ) {
	$price_type     = get_post_meta( $course_id, '_tutor_course_price_type', true );
	$is_purchasable = function_exists( 'tutor_utils' ) ? tutor_utils()->is_course_purchasable( $course_id ) : false;

	$current_price = __( 'Free', 'vconline-child' );
	$regular_price = '';
	$discount_tag  = '';

	if ( 'paid' === $price_type && $is_purchasable ) {
		$product_id = function_exists( 'tutor_utils' ) ? tutor_utils()->get_course_product_id( $course_id ) : 0;
		$product    = ( $product_id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : null;

		if ( $product ) {
			$reg_val  = (float) $product->get_regular_price();
			$sale_val = (float) $product->get_sale_price();
			$curr_val = (float) $product->get_price();

			if ( function_exists( 'wc_price' ) ) {
				$current_price = wp_strip_all_tags( wc_price( $curr_val ) );
				if ( $product->is_on_sale() && $reg_val > $sale_val && $sale_val > 0 ) {
					$regular_price = wp_strip_all_tags( wc_price( $reg_val ) );
					$pct = round( ( ( $reg_val - $sale_val ) / $reg_val ) * 100 );
					$discount_tag = $pct . '% OFF';
				}
			}
		} else {
			// Fallback: Parse Tutor formatted price
			$raw_price_html = tutor_utils()->get_course_price( $course_id );
			if ( $raw_price_html ) {
				// Match span content and del content
				if ( preg_match( '/<del[^>]*>(.*?)<\/del>/is', $raw_price_html, $del_m ) ) {
					$regular_price = wp_strip_all_tags( $del_m[1] );
				}
				$clean_current = preg_replace( '/<del[^>]*>.*?<\/del>/is', '', $raw_price_html );
				$current_price = trim( wp_strip_all_tags( $clean_current ) );
			}
		}

		// Calculate discount percentage if both regular and current prices exist
		if ( ! empty( $regular_price ) && ! empty( $current_price ) && empty( $discount_tag ) ) {
			$clean_reg  = (float) preg_replace( '/[^0-9.]/', '', $regular_price );
			$clean_curr = (float) preg_replace( '/[^0-9.]/', '', $current_price );
			if ( $clean_reg > $clean_curr && $clean_curr > 0 ) {
				$pct = round( ( ( $clean_reg - $clean_curr ) / $clean_reg ) * 100 );
				if ( $pct > 0 ) {
					$discount_tag = $pct . '% OFF';
				}
			}
		}
	} else {
		// Free course: check if regular price exists for crossed out display
		$reg_meta = (float) get_post_meta( $course_id, 'tutor_course_price', true );
		if ( ! $reg_meta ) {
			$reg_meta = (float) get_post_meta( $course_id, '_tutor_course_price', true );
		}
		if ( $reg_meta > 0 && function_exists( 'tutor_utils' ) ) {
			$regular_price = wp_strip_all_tags( tutor_utils()->tutor_price( $reg_meta ) );
			$discount_tag  = '100% OFF';
		}
	}

	return array(
		'current_price' => $current_price,
		'regular_price' => $regular_price,
		'discount_tag'  => $discount_tag,
	);
}

/**
 * Helper: Retrieve proper Tutor LMS Button action (Buy Now / Enroll / Continue Learning)
 *
 * @param int $course_id
 * @return array
 */
function vco_get_course_button_data( $course_id ) {
	$current_user_id = get_current_user_id();
	$permalink       = get_the_permalink( $course_id );

	// 1. Is user already enrolled or does course have full public access?
	$is_enrolled = function_exists( 'tutor_utils' ) && (
		tutor_utils()->is_enrolled( $course_id, $current_user_id ) ||
		get_post_meta( $course_id, '_tutor_is_public_course', true ) === 'yes' ||
		tutor_utils()->has_user_course_content_access( $current_user_id, $course_id )
	);

	if ( $is_enrolled ) {
		$lesson_url = function_exists( 'tutor_utils' ) ? tutor_utils()->get_course_first_lesson( $course_id ) : '';
		return array(
			'label' => __( 'Continue Learning', 'vconline-child' ),
			'url'   => ! empty( $lesson_url ) ? $lesson_url : $permalink,
			'class' => 'vco-btn-enrolled',
			'attrs' => '',
		);
	}

	// 2. Is course purchasable?
	$is_purchasable = function_exists( 'tutor_utils' ) && tutor_utils()->is_course_purchasable( $course_id );

	if ( $is_purchasable ) {
		// Tutor checkout page (page ID 10 on this install)
		$checkout_page_id = 10;
		if ( function_exists( 'tutor_utils' ) ) {
			$opt_checkout_id = tutor_utils()->get_option( 'checkout_page_id' );
			if ( $opt_checkout_id ) {
				$checkout_page_id = $opt_checkout_id;
			}
		}

		$checkout_url = add_query_arg( 'course_id', $course_id, get_permalink( $checkout_page_id ) );

		// Check login modal requirement
		$is_logged_in      = is_user_logged_in();
		$enable_guest_cart = function_exists( 'tutor_utils' ) ? tutor_utils()->get_option( 'enable_guest_course_cart' ) : false;
		$login_class       = ( ! $is_logged_in && ! $enable_guest_cart ) ? ' tutor-open-login-modal' : '';

		return array(
			'label' => __( 'Buy Now', 'vconline-child' ),
			'url'   => $checkout_url,
			'class' => 'vco-btn-buy-now' . $login_class,
			'attrs' => '',
		);
	}

	// 3. Free course fallback
	return array(
		'label' => __( 'Enroll Course', 'vconline-child' ),
		'url'   => $permalink,
		'class' => 'vco-btn-enroll',
		'attrs' => '',
	);
}

/**
 * Helper: Render Crisp 5-star SVG icons with full, half, and empty stars
 *
 * @param float $rating Rating score out of 5.
 * @return string
 */
function vco_render_star_rating_svg( $rating ) {
	$rating = max( 0, min( 5, floatval( $rating ) ) );
	$full_stars  = floor( $rating );
	$has_half    = ( ( $rating - $full_stars ) >= 0.3 && ( $rating - $full_stars ) <= 0.7 );
	if ( ( $rating - $full_stars ) > 0.7 ) {
		$full_stars++;
	}

	$output = '';

	// Full star SVG
	$star_full = '<svg class="vco-star-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
	
	// Half star SVG
	$star_half = '<svg class="vco-star-icon" viewBox="0 0 24 24"><defs><linearGradient id="vco-half-grad"><stop offset="50%" stop-color="#f59e0b"/><stop offset="50%" stop-color="#d1d5db"/></linearGradient></defs><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="url(#vco-half-grad)"/></svg>';

	// Empty star SVG
	$star_empty = '<svg class="vco-star-icon vco-star-empty" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';

	for ( $i = 1; $i <= 5; $i++ ) {
		if ( $i <= $full_stars ) {
			$output .= $star_full;
		} elseif ( $has_half && $i === ( $full_stars + 1 ) ) {
			$output .= $star_half;
		} else {
			$output .= $star_empty;
		}
	}

	return $output;
}
