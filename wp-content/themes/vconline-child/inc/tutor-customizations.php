<?php
/**
 * Tutor LMS Customizations and Overrides
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tutor_LMS_Customizations {

    public function __construct() {
        // Run initialization immediately since plugins are already loaded when theme is loaded
        $this->init();
    }

    public function init() {
        // 1. Load the extended class after Tutor LMS is fully loaded
        if ( class_exists( '\TUTOR\Utils' ) ) {
            require_once dirname( __FILE__ ) . '/Tutor_Custom_Utils_Extended.php';
            $GLOBALS['tutor_utils_object'] = new Tutor_Custom_Utils_Extended();
        }

        // 2. Prevent enrollment cancellation/downgrading if the student has a completed order
        add_action( 'tutor_enrollment/after/cancel', array( $this, 'prevent_enrollment_cancellation_if_paid' ), 10, 1 );
        add_action( 'tutor_enrollment/after/pending', array( $this, 'prevent_enrollment_cancellation_if_paid' ), 10, 1 );

        // 3. Sync newly registered WordPress users to TutorLMS students list and save mobile/phone number
        add_action( 'user_register', array( $this, 'save_mobile_and_sync_student' ) );

        // 4. Inject Mobile Number column on the TutorLMS Students admin page
        add_action( 'admin_footer', array( $this, 'add_mobile_to_tutor_students_page' ) );

        // 5. Add custom meta box for static total enrolled override
        add_action( 'add_meta_boxes', array( $this, 'register_static_enrolled_meta_box' ) );
        add_action( 'save_post_courses', array( $this, 'save_static_enrolled_meta' ), 10, 2 );

        // 6. AJAX handlers for frontend/course builder static enrolled
        add_action( 'wp_ajax_vca_get_static_enrolled', array( $this, 'ajax_get_static_enrolled' ) );
        add_action( 'wp_ajax_vca_save_static_enrolled', array( $this, 'ajax_save_static_enrolled' ) );

        // 7. Format rating display to exactly 1 decimal place (e.g. 4.7, 4.8)
        add_filter( 'tutor_course_rating_average', array( $this, 'format_rating_one_decimal' ), 99, 1 );

        // 8. Display course price on Course Cards (both Course List page and Home Page Elementor widget)
        add_filter( 'tutor_course_loop_price', array( $this, 'filter_course_loop_price' ), 20, 2 );

        // 9. Display regular price with <del> tag for Free courses on Single Course Details page
        add_filter( 'tutor/course/single/entry-box/free', array( $this, 'filter_single_course_free_entry_box' ), 20, 2 );

        // 10. Display 1 Year instead of 365 days for enrollment validity
        add_filter( 'tutor_course_expire_validity', array( $this, 'filter_course_expire_validity' ), 99, 2 );
        add_filter( 'tutor/course/single/sidebar/metadata', array( $this, 'filter_sidebar_metadata_validity' ), 99, 2 );

        // 11. Date-wise sorting: latest changes course display first
        add_action( 'pre_get_posts', array( $this, 'sort_course_archive_by_latest_changes' ), 999 );
        add_action( 'pre_get_posts', array( $this, 'sort_admin_wp_course_list_by_latest_changes' ), 999 );
        add_filter( 'tutor_admin_course_list', array( $this, 'sort_admin_course_list_by_latest_changes' ), 99, 4 );
        add_filter( 'tutor_course_filter_args', array( $this, 'sort_course_filter_args_by_latest_changes' ), 99, 1 );
        add_filter( 'gettext', array( $this, 'filter_course_filter_dropdown_label' ), 20, 3 );

        // 12. Support Rich Editor HTML in Course Benefits ("What Will I Learn?")
        add_action( 'tutor_after_prepare_update_post_meta', array( $this, 'save_course_benefits_rich_meta' ), 20, 2 );
        add_action( 'save_post_courses', array( $this, 'save_course_benefits_classic_meta' ), 20, 2 );
        add_filter( 'tutor_course/single/benefits_html', array( $this, 'filter_course_benefits_html_rich_content' ), 99, 1 );

        // 13. Load custom Course Builder Additional Chunk from child theme (No core plugin changes, no .htaccess)
        add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_custom_course_builder_chunk' ), 50 );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_custom_course_builder_chunk' ), 50 );

        // 14. Add distraction-free body class on Tutor checkout page
        add_filter( 'body_class', array( $this, 'add_checkout_body_class' ) );

        // 15. Save student mobile/phone number when order is placed
        add_action( 'tutor_order_placed', array( $this, 'save_phone_on_order_placed' ), 10, 1 );

        // 16. Fix Razorpay embedded redirect form: ensure method="post" is present to prevent AWS WAF 403 Forbidden on local IP
        add_action( 'tutor_action_tutor_pay_now', array( $this, 'buffer_razorpay_checkout_form' ), 1 );
        add_action( 'tutor_action_tutor_pay_incomplete_order', array( $this, 'buffer_razorpay_checkout_form' ), 1 );

        // 17. Preserve course_id on cancelled payment redirects so checkout price is never 0
        add_filter( 'tutor_ecommerce_payment_cancelled_url_args', array( $this, 'preserve_cancelled_course_id' ), 10, 1 );

        // 18. Render mobile sticky bar with price and Buy Now button on course details page
        add_action( 'wp_footer', array( $this, 'render_course_mobile_sticky_bar' ) );

        // 19. Certificate page template override in child theme
        add_filter( 'template_include', array( $this, 'override_single_certificate_template' ), 999 );

        // 20. Certificate Builder AJAX prefilter and canvas fix (no plugin modifications)
        add_action( 'tutor_certificate_builder_init', array( $this, 'attach_certificate_builder_prefilter' ) );
        add_action( 'tutor_certificate_builder_enqueue_script', array( $this, 'attach_certificate_builder_prefilter' ) );
        add_action( 'wp_head', array( $this, 'maybe_attach_prefilter_to_frontend' ), 1 );
        add_filter( 'get_post_metadata', array( $this, 'filter_certificate_template_postmeta' ), 10, 4 );

        // 21. Student Dashboard: View Certificate button on completed course cards
        add_action( 'tutor_course_action_btn', array( $this, 'render_dashboard_course_certificate_btn' ), 5, 1 );

        // 22. Student Dashboard: "My Certificates" navigation tab & template routing
        add_filter( 'tutor_dashboard/nav_items', array( $this, 'register_dashboard_certificates_nav' ), 20, 1 );
        add_filter( 'tutor_student_dashboard_nav', array( $this, 'register_dashboard_certificates_nav' ), 20, 1 );
        add_filter( 'load_dashboard_template_part_from_other_location', array( $this, 'load_dashboard_certificates_template' ), 20, 1 );
        add_filter( 'tutor_dashboard/permalinks', array( $this, 'register_dashboard_certificates_permalink' ), 20, 1 );
        add_action( 'parse_request', array( $this, 'ensure_dashboard_certificates_request' ) );
        add_filter( 'tutor_certificate_public_url', array( $this, 'ensure_trailing_slash_certificate_url' ), 99 );

        // 23. Course Completion Rating Modal: include Course Preview card with Access Course button
        add_action( 'wp', array( $this, 'setup_custom_review_popup' ), 20 );
        add_action( 'wp_footer', array( $this, 'custom_popup_review_form' ) );
    }

    /**
     * Filter enrollment validity text: change 365 days to 1 Year
     */
    public function filter_course_expire_validity( $validity, $course_id ) {
        if ( is_string( $validity ) ) {
            return str_ireplace( array( '365 days', '365 day' ), '1 Year', $validity );
        }
        return $validity;
    }

    /**
     * Filter single course sidebar metadata: change 365 days to 1 Year in enrollment validity
     */
    public function filter_sidebar_metadata_validity( $meta, $course_id ) {
        if ( is_array( $meta ) ) {
            foreach ( $meta as &$item ) {
                if ( isset( $item['value'] ) && is_string( $item['value'] ) ) {
                    $item['value'] = str_ireplace( array( '365 days', '365 day' ), '1 Year', $item['value'] );
                }
            }
        }
        return $meta;
    }

    /**
     * Helper to get regular price of a course
     */
    public function get_course_regular_price( $course_id ) {
        $regular_price = (float) get_post_meta( $course_id, 'tutor_course_price', true );
        if ( ! $regular_price ) {
            $regular_price = (float) get_post_meta( $course_id, '_tutor_course_price', true );
        }
        return $regular_price;
    }

    /**
     * Display course price above the button on course loop cards when course is free or user can continue
     */
    public function filter_course_loop_price( $loop_html, $course_id ) {
        // If price is already displayed (e.g., guest viewing a purchasable paid course with .list-item-price), do not duplicate
        if ( false !== strpos( $loop_html, 'list-item-price' ) ) {
            return $loop_html;
        }

        $price_type = get_post_meta( $course_id, '_tutor_course_price_type', true );
        $price_html = '';

        if ( 'paid' === $price_type ) {
            $formatted_price = tutor_utils()->get_course_price( $course_id );
            if ( $formatted_price ) {
                $price_html = $formatted_price;
            }
        } else {
            $regular_price = $this->get_course_regular_price( $course_id );
            $del_html      = '';
            if ( $regular_price > 0 ) {
                $del_html = '<del class="tutor-fs-7 tutor-color-muted tutor-ml-8">' . tutor_utils()->tutor_price( $regular_price ) . '</del>';
            }
            $price_html = '<div class="list-item-price tutor-item-price"><span class="price tutor-fs-6 tutor-fw-bold tutor-color-black">' . esc_html__( 'Free', 'tutor' ) . '</span>' . $del_html . '</div>';
        }

        if ( ! empty( $price_html ) ) {
            return '<div class="vco-card-price-wrapper tutor-mb-12">' . $price_html . '</div>' . $loop_html;
        }

        return $loop_html;
    }

    /**
     * Display regular price with <del> tag on Single Course Details page for Free courses
     */
    public function filter_single_course_free_entry_box( $html, $course_id ) {
        $regular_price = $this->get_course_regular_price( $course_id );
        if ( $regular_price > 0 ) {
            $formatted_regular = tutor_utils()->tutor_price( $regular_price );
            $custom_price_html = '<div class="tutor-course-single-pricing tutor-d-flex tutor-align-center"><span class="tutor-fs-4 tutor-fw-bold tutor-color-black">' . esc_html__( 'Free', 'tutor' ) . '</span><del class="tutor-fs-6 tutor-color-muted tutor-ml-8" style="color: #888888; text-decoration: line-through;">' . $formatted_regular . '</del></div>';
            $html = preg_replace( '/<div class="tutor-course-single-pricing">.*?<\/div>/s', $custom_price_html, $html );
        }
        return $html;
    }

    /**
     * Format any average rating to 1 decimal place.
     */
    public function format_rating_one_decimal( $rating ) {
        if ( is_numeric( $rating ) && (float) $rating > 0 ) {
            return number_format( (float) $rating, 1, '.', '' );
        }
        return $rating;
    }

    /**
     * Prevent enrollment cancellation/downgrading if the student has a completed order for the course
     */
    public function prevent_enrollment_cancellation_if_paid( $enrollment_id ) {
        global $wpdb;

        $enrollment = get_post( $enrollment_id );
        if ( ! $enrollment || 'tutor_enrolled' !== $enrollment->post_type ) {
            return;
        }

        $student_id = $enrollment->post_author;
        $course_id = $enrollment->post_parent;

        $has_completed_order = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}tutor_orders o 
                 JOIN {$wpdb->prefix}tutor_order_items i ON o.id = i.order_id 
                 WHERE o.user_id = %d 
                   AND i.item_id = %d 
                   AND o.order_status = 'completed'",
                $student_id,
                $course_id
            )
        );

        if ( $has_completed_order > 0 ) {
            $wpdb->update(
                $wpdb->posts,
                array( 'post_status' => 'completed' ),
                array( 'ID' => $enrollment_id )
            );
            clean_post_cache( $enrollment_id );
        }
    }

    /**
     * Sync newly registered WordPress users to TutorLMS students list and save mobile/phone number
     */
    public function save_mobile_and_sync_student( $user_id ) {
        $phone_value = '';

        if ( isset( $_POST['mobile_number'] ) ) {
            $phone_value = sanitize_text_field( wp_unslash( $_POST['mobile_number'] ) );
        } elseif ( isset( $_POST['phone_number'] ) ) {
            $phone_value = sanitize_text_field( wp_unslash( $_POST['phone_number'] ) );
        }

        if ( ! empty( $phone_value ) ) {
            $existing_phone = get_user_meta( $user_id, 'phone_number', true );
            $existing_mobile = get_user_meta( $user_id, 'mobile_number', true );

            if ( empty( $existing_phone ) && empty( $existing_mobile ) ) {
                update_user_meta( $user_id, 'phone_number', $phone_value );
            }
        }

        update_user_meta( $user_id, '_is_tutor_student', time() );
    }

    /**
     * Inject Mobile Number column on the TutorLMS Students admin page
     */
    public function add_mobile_to_tutor_students_page() {
        if ( ! isset( $_GET['page'] ) || 'tutor-students' !== $_GET['page'] ) {
            return;
        }

        global $wpdb;
        $phone_numbers = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'phone_number'"
        );

        $numbers_map = array();
        foreach ( $phone_numbers as $pn ) {
            $numbers_map[ $pn->user_id ] = $pn->meta_value;
        }

        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var mobileNumbers = <?php echo json_encode( $numbers_map ); ?>;

                // Add Header Column
                var $thead = $('.tutor-dashboard-list-table table thead tr');
                if ($thead.length) {
                    $thead.find('th').eq(2).after('<th class="tutor-table-rows-sorting">Mobile Number</th>');
                }

                // Add Body Columns
                $('.tutor-dashboard-list-table table tbody tr').each(function() {
                    var $row = $(this);
                    var userId = $row.find('input.tutor-bulk-checkbox').val();
                    var phone = mobileNumbers[userId] || '—';
                    $row.find('td').eq(2).after('<td><span class="tutor-fs-7">' + phone + '</span></td>');
                });
            });
        </script>
        <?php
    }

    /**
     * Register static enrolled override meta box for course post type
     */
    public function register_static_enrolled_meta_box() {
        add_meta_box(
            'vca_static_enrolled_meta_box',
            __( 'Total Enrolled Settings', 'tutor' ),
            array( $this, 'render_static_enrolled_meta_box' ),
            'courses',
            'side',
            'default'
        );
    }

    /**
     * Render the static enrolled meta box
     */
    public function render_static_enrolled_meta_box( $post ) {
        wp_nonce_field( 'vca_static_enrolled_save', 'vca_static_enrolled_nonce' );
        $value = get_post_meta( $post->ID, '_vca_static_enrolled_count', true );
        ?>
        <p>
            <label for="vca_static_enrolled_count"><?php _e( 'Manually enter total enrolled number:', 'tutor' ); ?></label>
            <input type="number" id="vca_static_enrolled_count" name="vca_static_enrolled_count" value="<?php echo esc_attr( $value ); ?>" class="components-text-control__input" style="width:100%; margin-top:5px;" min="0" placeholder="<?php _e( 'e.g. 500', 'tutor' ); ?>" />
        </p>
        <p class="description">
            <?php _e( 'If set, this number will be displayed as the "Total Enrolled" count on the frontend details page instead of the dynamically calculated enrolled student count.', 'tutor' ); ?>
        </p>
        <?php
    }

    /**
     * Save the static enrolled meta field
     */
    public function save_static_enrolled_meta( $post_id, $post ) {
        if ( ! isset( $_POST['vca_static_enrolled_nonce'] ) || ! wp_verify_nonce( $_POST['vca_static_enrolled_nonce'], 'vca_static_enrolled_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( isset( $_POST['vca_static_enrolled_count'] ) ) {
            $val = sanitize_text_field( $_POST['vca_static_enrolled_count'] );
            if ( $val === '' ) {
                delete_post_meta( $post_id, '_vca_static_enrolled_count' );
            } else {
                update_post_meta( $post_id, '_vca_static_enrolled_count', intval( $val ) );
            }
        }
    }

    /**
     * AJAX Handler to get static enrolled settings
     */
    public function ajax_get_static_enrolled() {
        check_ajax_referer( 'vco_badge_nonce', 'nonce' );

        $course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
        if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
            wp_send_json_error( 'Invalid course ID.' );
        }

        $value = get_post_meta( $course_id, '_vca_static_enrolled_count', true );
        wp_send_json_success( array(
            'static_enrolled' => $value,
        ) );
    }

    /**
     * AJAX Handler to save static enrolled settings
     */
    public function ajax_save_static_enrolled() {
        check_ajax_referer( 'vco_badge_nonce', 'nonce' );

        $course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
        if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
            wp_send_json_error( 'Invalid course ID.' );
        }

        $static_enrolled = isset( $_POST['static_enrolled'] ) ? sanitize_text_field( wp_unslash( $_POST['static_enrolled'] ) ) : '';
        
        if ( $static_enrolled === '' ) {
            delete_post_meta( $course_id, '_vca_static_enrolled_count' );
        } else {
            update_post_meta( $course_id, '_vca_static_enrolled_count', intval( $static_enrolled ) );
        }

        wp_send_json_success();
    }

    /**
     * 11. Sort Course Archive query by latest changes (post_modified DESC, post_date DESC)
     */
    public function sort_course_archive_by_latest_changes( $query ) {
        if ( is_admin() || ! $query->is_main_query() || $query->is_feed() ) {
            return;
        }

        $post_type        = $query->get( 'post_type' );
        $course_category  = $query->get( 'course-category' );
        $course_post_type = function_exists( 'tutor' ) ? tutor()->course_post_type : 'courses';

        $is_course = false;
        if ( is_array( $post_type ) && in_array( $course_post_type, $post_type, true ) ) {
            $is_course = true;
        } elseif ( $post_type === $course_post_type ) {
            $is_course = true;
        } elseif ( ! empty( $course_category ) ) {
            $is_course = true;
        } elseif ( $query->is_post_type_archive( $course_post_type ) || $query->is_tax( 'course-category' ) || $query->is_tax( 'course-tag' ) ) {
            $is_course = true;
        }

        // Also check if current page is the selected course archive page
        if ( ! $is_course && is_page() ) {
            $page_id               = get_queried_object_id();
            $selected_archive_page = (int) apply_filters( 'tutor_filter_course_archive_page', tutor_utils()->get_option( 'course_archive_page' ) );
            if ( $page_id && $page_id === $selected_archive_page ) {
                $is_course = true;
            }
        }

        if ( $is_course ) {
            $course_filter = '';
            if ( ! empty( $_GET['course_order'] ) ) {
                $course_filter = sanitize_text_field( wp_unslash( $_GET['course_order'] ) );
            } elseif ( ! empty( $_GET['tutor_course_filter'] ) ) {
                $course_filter = sanitize_text_field( wp_unslash( $_GET['tutor_course_filter'] ) );
            }

            switch ( $course_filter ) {
                case 'oldest_first':
                    $query->set( 'orderby', array(
                        'post_modified' => 'ASC',
                        'post_date'     => 'ASC',
                    ) );
                    $query->set( 'order', 'ASC' );
                    break;

                case 'course_title_az':
                    $query->set( 'orderby', 'post_title' );
                    $query->set( 'order', 'ASC' );
                    break;

                case 'course_title_za':
                    $query->set( 'orderby', 'post_title' );
                    $query->set( 'order', 'DESC' );
                    break;

                case 'newest_first':
                default:
                    $query->set( 'orderby', array(
                        'post_modified' => 'DESC',
                        'post_date'     => 'DESC',
                    ) );
                    $query->set( 'order', 'DESC' );
                    break;
            }
        }
    }

    /**
     * Sort Course Filter args (Elementor Course List widget, AJAX filters, etc.) by latest changes
     */
    public function sort_course_filter_args_by_latest_changes( $args ) {
        if ( ! is_array( $args ) ) {
            return $args;
        }

        $orderby = isset( $args['orderby'] ) ? $args['orderby'] : '';
        $order   = isset( $args['order'] ) ? strtoupper( $args['order'] ) : 'DESC';

        // Check if date-based sorting is requested (default, post_date, date)
        $is_date_sort = false;
        if ( empty( $orderby ) ) {
            $is_date_sort = true;
        } elseif ( in_array( $orderby, array( 'post_date', 'date' ), true ) && 'DESC' === $order ) {
            $is_date_sort = true;
        }

        // If request explicitly specified non-date order, respect it
        $course_order = '';
        if ( isset( $_POST['course_order'] ) ) {
            $course_order = sanitize_text_field( wp_unslash( $_POST['course_order'] ) );
        } elseif ( isset( $_GET['course_order'] ) ) {
            $course_order = sanitize_text_field( wp_unslash( $_GET['course_order'] ) );
        } elseif ( isset( $_POST['tutor_course_filter'] ) ) {
            $course_order = sanitize_text_field( wp_unslash( $_POST['tutor_course_filter'] ) );
        } elseif ( isset( $_GET['tutor_course_filter'] ) ) {
            $course_order = sanitize_text_field( wp_unslash( $_GET['tutor_course_filter'] ) );
        }

        if ( in_array( $course_order, array( 'oldest_first', 'course_title_az', 'course_title_za' ), true ) ) {
            $is_date_sort = false;
        }

        if ( $is_date_sort ) {
            $args['orderby'] = array(
                'post_modified' => 'DESC',
                'post_date'     => 'DESC',
            );
            $args['order']   = 'DESC';
        }

        return $args;
    }

    /**
     * Update filter dropdown label to clarify date-wise sorting by latest changes
     */
    public function filter_course_filter_dropdown_label( $translation, $text, $domain ) {
        if ( 'tutor' === $domain && 'Release Date (newest first)' === $text ) {
            return __( 'Date (latest changes first)', 'tutor' );
        }
        return $translation;
    }

    /**
     * Sort Tutor LMS Admin Courses list (admin.php?page=courses / tutor-courses) by latest changes
     */
    public function sort_admin_course_list_by_latest_changes( $args, $user_id = null, $status = null, $all_post_types = false ) {
        if ( ! is_array( $args ) ) {
            return $args;
        }

        // If user explicitly requested custom orderby via GET (other than default ID), respect it
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
        if ( ! empty( $orderby ) && 'ID' !== $orderby ) {
            return $args;
        }

        $args['orderby'] = array(
            'post_modified' => 'DESC',
            'post_date'     => 'DESC',
        );
        $args['order']   = 'DESC';

        return $args;
    }

    /**
     * Sort WordPress Admin edit.php?post_type=courses list by latest changes
     */
    public function sort_admin_wp_course_list_by_latest_changes( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        $course_post_type = function_exists( 'tutor' ) ? tutor()->course_post_type : 'courses';

        if ( $post_type === $course_post_type ) {
            if ( ! isset( $_GET['orderby'] ) ) {
                $query->set( 'orderby', array(
                    'post_modified' => 'DESC',
                    'post_date'     => 'DESC',
                ) );
                $query->set( 'order', 'DESC' );
            }
        }
    }

    /**
     * 12. Save rich editor HTML content for Course Benefits from Course Builder AJAX update
     */
    public function save_course_benefits_rich_meta( $post_id, $params = null ) {
        $benefits = null;
        if ( isset( $_POST['additional_content']['course_benefits'] ) ) {
            $benefits = wp_unslash( $_POST['additional_content']['course_benefits'] );
        } elseif ( isset( $_POST['course_benefits'] ) ) {
            $benefits = wp_unslash( $_POST['course_benefits'] );
        }

        if ( null !== $benefits ) {
            $benefits = wp_kses_post( $benefits );
            if ( '' !== trim( $benefits ) && '<p></p>' !== trim( $benefits ) ) {
                update_post_meta( $post_id, '_tutor_course_benefits', $benefits );
            } else {
                delete_post_meta( $post_id, '_tutor_course_benefits' );
            }
        }
    }

    /**
     * Save rich editor HTML content for Course Benefits from classic WordPress course editor
     */
    public function save_course_benefits_classic_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( isset( $_POST['course_benefits'] ) ) {
            $benefits = wp_kses_post( wp_unslash( $_POST['course_benefits'] ) );
            if ( '' !== trim( $benefits ) && '<p></p>' !== trim( $benefits ) ) {
                update_post_meta( $post_id, '_tutor_course_benefits', $benefits );
            } else {
                delete_post_meta( $post_id, '_tutor_course_benefits' );
            }
        }
    }

    /**
     * Filter Course Benefits HTML output to render rich editor content
     * Handles both Elementor CourseBenefits addon and any other template calls
     */
    public function filter_course_benefits_html_rich_content( $html ) {
        $course_id = get_the_ID();
        if ( ! $course_id ) {
            return $html;
        }

        $raw_benefits = get_post_meta( $course_id, '_tutor_course_benefits', true );
        if ( empty( $raw_benefits ) ) {
            return $html;
        }

        // Only override if benefits content contains HTML markup from the rich editor
        if ( $raw_benefits !== strip_tags( $raw_benefits ) ) {
            $rich_content = '<div class="tutor-course-benefits-content tutor-fs-6 tutor-color-secondary tutor-mt-16">' . apply_filters( 'the_content', $raw_benefits ) . '</div>';

            // If the HTML output has a list (like from Elementor addon or fallback template), replace the list
            if ( preg_match( '/<ul[^>]*class="[^"]*(?:etlms-course-widget-list-items|tutor-course-details-widget-list)[^"]*"[^>]*>.*?<\/ul>/is', $html ) ) {
                $html = preg_replace( '/<ul[^>]*class="[^"]*(?:etlms-course-widget-list-items|tutor-course-details-widget-list)[^"]*"[^>]*>.*?<\/ul>/is', $rich_content, $html );
            }
        }

        return $html;
    }

    /**
     * 13. Check if on course builder page and enqueue custom chunk
     */
    public function maybe_enqueue_custom_course_builder_chunk() {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( in_array( $page, array( 'tutor-course-builder', 'create-course' ), true ) || ( function_exists( 'tutor_utils' ) && tutor_utils()->is_tutor_frontend_dashboard( 'create-course' ) ) ) {
            $this->enqueue_custom_course_builder_chunk();
        }
    }

    /**
     * Enqueue child theme course builder chunk
     */
    public function enqueue_custom_course_builder_chunk() {
        $file_path = get_stylesheet_directory() . '/js/tutor-course-builder-additional.js';
        if ( file_exists( $file_path ) ) {
            // Ensure WordPress default editor scripts and settings are enqueued
            if ( function_exists( 'wp_enqueue_editor' ) ) {
                wp_enqueue_editor();
            }

            // Provide early safety polyfill for window.wp.editor.getDefaultSettings
            $inline_editor_safe = 'window.wp=window.wp||{};window.wp.editor=window.wp.editor||{};if(!window.wp.editor.getDefaultSettings){window.wp.editor.getDefaultSettings=function(){return {tinymce:{},quicktags:{buttons:"strong,em,link,ul,ol,li,code"}}};}';
            wp_add_inline_script( 'tutor-course-builder', $inline_editor_safe, 'after' );

            wp_enqueue_script(
                'vco-course-builder-additional-chunk',
                get_stylesheet_directory_uri() . '/js/tutor-course-builder-additional.js',
                array( 'tutor-course-builder' ),
                filemtime( $file_path ),
                true
            );
        }
    }

    /**
     * Add distraction-free body class on Tutor checkout page
     */
    public function add_checkout_body_class( $classes ) {
        $checkout_page_id = (int) ( function_exists( 'tutor_utils' ) ? tutor_utils()->get_option( 'tutor_checkout_page_id' ) : 0 );
        if ( ( $checkout_page_id && is_page( $checkout_page_id ) ) || is_page( 'checkout' ) ) {
            $classes[] = 'vco-checkout-distraction-free';
        }
        return $classes;
    }

    /**
     * Save student mobile/phone number to user meta when order is placed
     */
    public function save_phone_on_order_placed( $order_data ) {
        $user_id = isset( $order_data['user_id'] ) ? (int) $order_data['user_id'] : 0;
        $phone   = isset( $order_data['billing_phone'] ) ? sanitize_text_field( $order_data['billing_phone'] ) : '';

        if ( empty( $phone ) && isset( $_POST['billing_phone'] ) ) {
            $phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
        }

        if ( $user_id && ! empty( $phone ) ) {
            update_user_meta( $user_id, 'phone_number', $phone );
            update_user_meta( $user_id, 'mobile_number', $phone );
        }
    }

    /**
     * 16. Buffer Razorpay checkout redirect: render official Razorpay Standard Checkout SDK (checkout.js).
     * Replaces the legacy hosted embedded fallback with Razorpay Standard Checkout for native UPI Intent support and priority display.
     */
    public function buffer_razorpay_checkout_form() {
        ob_start( function( $buffer ) {
            if ( false !== strpos( $buffer, 'id="razorpay-form"' ) ) {
                // Parse parameters generated by tutor-razorpay
                preg_match_all( '/name=[\"\']([^\"\']+)[\"\']\s+value=[\"\']([^\"\']*)[\"\']/i', $buffer, $matches );
                $data = array();
                if ( ! empty( $matches[1] ) && ! empty( $matches[2] ) ) {
                    $data = array_combine( $matches[1], $matches[2] );
                }

                $phone = '';
                if ( ! empty( $_POST['billing_phone'] ) ) {
                    $phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
                    $phone = preg_replace( '/[^0-9]/', '', $phone );
                }

                $key_id       = $data['key_id'] ?? '';
                $amount       = (int) ( $data['amount'] ?? 0 );
                $order_id       = $data['order_id'] ?? '';
                $tutor_order_id = (int) ( $data['notes[order_id]'] ?? $_REQUEST['order_id'] ?? 0 );
                $name           = $data['name'] ?? get_bloginfo( 'name' );
                $callback_url   = html_entity_decode( $data['callback_url'] ?? home_url() );
                $cancel_url     = html_entity_decode( $data['cancel_url'] ?? home_url() );
                $user_name      = $data['profile[name]'] ?? '';
                $user_email     = $data['profile[email]'] ?? '';

                // Link Razorpay Order ID to Tutor LMS Order ID in database
                if ( $tutor_order_id && $order_id ) {
                    global $wpdb;
                    $wpdb->replace(
                        $wpdb->prefix . 'tutor_ordermeta',
                        array(
                            'order_id'       => $tutor_order_id,
                            'meta_key'       => 'razorpay_order_id',
                            'meta_value'     => $order_id,
                            'created_at_gmt' => current_time( 'mysql', true ),
                            'created_by'     => get_current_user_id(),
                            'updated_at_gmt' => current_time( 'mysql', true ),
                            'updated_by'     => get_current_user_id(),
                        ),
                        array( '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
                    );
                    setcookie( 'vco_last_tutor_order_id', (string) $tutor_order_id, time() + 86400, COOKIEPATH, COOKIE_DOMAIN );
                }

                // Ensure callback_url always has tutor_order_placement=success and order_id
                if ( $tutor_order_id ) {
                    $callback_url = add_query_arg( array(
                        'tutor_order_placement' => 'success',
                        'order_id'              => $tutor_order_id,
                    ), $callback_url );
                }

                $intent = 'gpay';
                if ( ! empty( $_POST['vco_payment_intent'] ) ) {
                    $intent = sanitize_text_field( wp_unslash( $_POST['vco_payment_intent'] ) );
                } elseif ( ! empty( $_GET['vco_payment_intent'] ) ) {
                    $intent = sanitize_text_field( wp_unslash( $_GET['vco_payment_intent'] ) );
                } elseif ( ! empty( $_REQUEST['vco_payment_intent'] ) ) {
                    $intent = sanitize_text_field( wp_unslash( $_REQUEST['vco_payment_intent'] ) );
                }

                // Ensure cancel_url carries course_id so returning to checkout restores price
                $course_id = 0;
                if ( ! empty( $_POST['object_ids'] ) ) {
                    $course_id = (int) sanitize_text_field( wp_unslash( $_POST['object_ids'] ) );
                }
                if ( ! $course_id && ! empty( $tutor_order_id ) ) {
                    if ( class_exists( '\Tutor\Models\OrderModel' ) ) {
                        $order_model = new \Tutor\Models\OrderModel();
                        if ( method_exists( $order_model, 'get_order_items_by_id' ) ) {
                            $order_items = $order_model->get_order_items_by_id( (int) $tutor_order_id );
                            if ( ! empty( $order_items ) && isset( $order_items[0]->item_id ) ) {
                                $course_id = (int) $order_items[0]->item_id;
                            }
                        }
                    }
                }
                if ( ! $course_id && ! empty( $_COOKIE['vco_last_checkout_course_id'] ) ) {
                    $course_id = (int) $_COOKIE['vco_last_checkout_course_id'];
                }
                if ( $course_id ) {
                    $cancel_url = add_query_arg( 'course_id', $course_id, $cancel_url );
                }

                if ( $key_id && $order_id ) {
                    $prefill = array(
                        'name'    => $user_name,
                        'email'   => $user_email,
                        'contact' => $phone,
                    );

                    $rzp_options = array(
                        'key'          => $key_id,
                        'amount'       => $amount,
                        'currency'     => 'INR',
                        'name'         => $name,
                        'description'  => 'Online Course Enrollment',
                        'order_id'     => $order_id,
                        'callback_url' => $callback_url,
                        'handler'      => 'RZP_SUCCESS_HANDLER',
                        'theme'        => array(
                            'color' => '#010101',
                        ),
                        'modal'        => array(
                            'ondismiss' => 'RZP_DISMISS_HANDLER',
                        ),
                    );

                    if ( 'cards' === $intent ) {
                        // "Pay with Cards" button: Open main Razorpay Checkout page with all available payment options, without UPI pre-selected
                        $rzp_options['prefill'] = $prefill;
                        $rzp_options['config']  = array(
                            'display' => array(
                                'blocks'      => array(
                                    'card'  => array(
                                        'name'        => 'Cards (Credit / Debit)',
                                        'instruments' => array(
                                            array( 'method' => 'card' ),
                                        ),
                                    ),
                                    'other' => array(
                                        'name'        => 'Other Payment Methods',
                                        'instruments' => array(
                                            array( 'method' => 'netbanking' ),
                                            array( 'method' => 'wallet' ),
                                            array( 'method' => 'upi' ),
                                        ),
                                    ),
                                ),
                                'sequence'    => array( 'block.card', 'block.other' ),
                                'preferences' => array(
                                    'show_default_blocks' => true,
                                ),
                            ),
                        );
                        $subtitle_text          = 'Opening Payment Options. Please wait...';
                    } else {
                        // "Pay with GPay" button: Directly selects Google Pay and shows the Pay option
                        $rzp_options['prefill'] = $prefill;
                        $subtitle_text          = 'Opening Google Pay. Please wait...';
                    }

                    $json_options = wp_json_encode( $rzp_options );
                    $json_options = str_replace( '"RZP_DISMISS_HANDLER"', 'function() { window.location.href = ' . wp_json_encode( $cancel_url ) . '; }', $json_options );
                    $json_options = str_replace( '"RZP_SUCCESS_HANDLER"', 'function(response) { var form = document.createElement("form"); form.method = "POST"; form.action = ' . wp_json_encode( $callback_url ) . '; var p = document.createElement("input"); p.type="hidden"; p.name="razorpay_payment_id"; p.value=response.razorpay_payment_id; form.appendChild(p); var o = document.createElement("input"); o.type="hidden"; o.name="razorpay_order_id"; o.value=response.razorpay_order_id; form.appendChild(o); var s = document.createElement("input"); s.type="hidden"; s.name="razorpay_signature"; s.value=response.razorpay_signature; form.appendChild(s); document.body.appendChild(form); form.submit(); }', $json_options );

                    return '<!DOCTYPE html>'
                        . '<html lang="en"><head>'
                        . '<meta charset="UTF-8">'
                        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                        . '<title>' . esc_html__( 'Connecting to Payment...', 'tutor' ) . '</title>'
                        . '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>'
                        . '<style>'
                        . 'body{margin:0;padding:0;background:#09090b;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#ffffff;text-align:center;box-sizing:border-box;padding:24px;}'
                        . '.vco-loader{width:48px;height:48px;border:3px solid rgba(255,255,255,0.15);border-top-color:#ffffff;border-radius:50%;animation:vco-spin 0.75s linear infinite;margin:0 auto 20px;}'
                        . '@keyframes vco-spin{to{transform:rotate(360deg)}}'
                        . '</style>'
                        . '</head><body>'
                        . '<div class="vco-wrap">'
                        . '<div class="vco-loader"></div>'
                        . '<div style="font-size:20px;font-weight:700;letter-spacing:-0.02em;margin-bottom:8px;">Connecting to Secure Payment...</div>'
                        . '<div style="font-size:14px;color:#a1a1aa;max-width:320px;line-height:1.5;">' . esc_html( $subtitle_text ) . '</div>'
                        . '</div>'
                        . '<script>'
                        . 'document.addEventListener("DOMContentLoaded", function() {'
                        . '    var options = ' . $json_options . ';'
                        . '    var rzp = new Razorpay(options);'
                        . '    rzp.on("payment.failed", function(response) {'
                        . '        window.location.href = ' . wp_json_encode( $cancel_url ) . ';'
                        . '    });'
                        . '    rzp.open();'
                        . '});'
                        . '</script>'
                        . '</body></html>';
                }
            }
            return $buffer;
        } );
    }

    /**
     * 17. Preserve course_id on cancelled payment redirects so checkout price is never 0
     */
    public function preserve_cancelled_course_id( $args ) {
        $course_id = 0;
        if ( ! empty( $_REQUEST['course_id'] ) ) {
            $course_id = (int) $_REQUEST['course_id'];
        } elseif ( ! empty( $_POST['object_ids'] ) ) {
            $course_id = (int) $_POST['object_ids'];
        } elseif ( ! empty( $args['order_id'] ) ) {
            if ( class_exists( '\Tutor\Models\OrderModel' ) ) {
                $order_model = new \Tutor\Models\OrderModel();
                if ( method_exists( $order_model, 'get_order_items_by_id' ) ) {
                    $order_items = $order_model->get_order_items_by_id( (int) $args['order_id'] );
                    if ( ! empty( $order_items ) && isset( $order_items[0]->item_id ) ) {
                        $course_id = (int) $order_items[0]->item_id;
                    }
                }
            }
        }
        if ( ! $course_id && ! empty( $_COOKIE['vco_last_checkout_course_id'] ) ) {
            $course_id = (int) $_COOKIE['vco_last_checkout_course_id'];
        }

        if ( $course_id ) {
            $args['course_id'] = $course_id;
        }
        return $args;
    }

    /**
     * 18. Render mobile sticky bar with price and Buy Now button on course details page
     */
    public function render_course_mobile_sticky_bar() {
        $course_post_type = function_exists( 'tutor' ) ? tutor()->course_post_type : 'courses';
        if ( ! is_singular( $course_post_type ) ) {
            return;
        }

        $course_id = get_queried_object_id();
        if ( ! $course_id ) {
            $course_id = get_the_ID();
        }
        if ( ! $course_id ) {
            return;
        }

        $user_id     = get_current_user_id();
        $is_enrolled = false;

        // Check enrollment status
        if ( is_user_logged_in() ) {
            if ( function_exists( 'tutor_utils' ) ) {
                $is_enrolled = (bool) tutor_utils()->is_enrolled( $course_id, $user_id );
            }
            if ( ! $is_enrolled && class_exists( '\Tutor\Models\EnrollmentModel' ) ) {
                $is_enrolled = (bool) \Tutor\Models\EnrollmentModel::is_enrolled( $course_id, $user_id );
            }
            if ( ! $is_enrolled ) {
                global $is_enrolled;
                if ( ! empty( $is_enrolled ) ) {
                    $is_enrolled = true;
                }
            }
            if ( ! $is_enrolled && function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'has_user_course_content_access' ) ) {
                $is_enrolled = (bool) tutor_utils()->has_user_course_content_access( $user_id, $course_id );
            }
        }

        $is_public  = ( get_post_meta( $course_id, '_tutor_is_public_course', true ) === 'yes' );
        if ( ! $is_public && class_exists( '\TUTOR\Course_List' ) && method_exists( '\TUTOR\Course_List', 'is_public' ) ) {
            $is_public = (bool) \TUTOR\Course_List::is_public( $course_id );
        }

        $price_type = function_exists( 'tutor_utils' ) ? tutor_utils()->price_type( $course_id ) : get_post_meta( $course_id, '_tutor_course_price_type', true );
        $is_free    = ( 'free' === $price_type || ( function_exists( 'tutor_utils' ) && ! tutor_utils()->is_course_purchasable( $course_id ) ) );

        $is_public_free     = ( $is_public && $is_free );
        $can_start_learning = ( $is_enrolled || $is_public_free );

        $lesson_url = '';
        if ( $can_start_learning && function_exists( 'tutor_utils' ) ) {
            if ( isset( tutor()->lesson_post_type ) ) {
                $lesson_url = tutor_utils()->get_course_first_lesson( $course_id, tutor()->lesson_post_type );
            }
            if ( ! $lesson_url ) {
                $lesson_url = tutor_utils()->get_course_first_lesson( $course_id );
            }
        }
        if ( $can_start_learning && ! $lesson_url ) {
            $lesson_url = get_permalink( $course_id );
        }

        // Determine button label
        $enroll_btn_text = __( 'Start Learning', 'tutor' );
        if ( $is_enrolled && function_exists( 'tutor_utils' ) ) {
            $course_progress   = tutor_utils()->get_course_completed_percent( $course_id, $user_id, true );
            $completed_percent = is_array( $course_progress ) ? (int) ( $course_progress['completed_percent'] ?? 0 ) : (int) $course_progress;
            if ( $completed_percent > 0 && $completed_percent < 100 ) {
                $enroll_btn_text = __( 'Continue Learning', 'tutor' );
            }
        }

        // Check pricing only when user cannot directly start learning
        $has_sale                = false;
        $discount_pct            = 0;
        $formatted_display_price = '';
        $formatted_regular_price = '';
        $is_purchasable          = true;
        $display_price           = 0;
        $regular_price           = 0;
        $buy_now_link            = '';

        if ( ! $can_start_learning ) {
            $is_purchasable = function_exists( 'tutor_utils' ) ? tutor_utils()->is_course_purchasable( $course_id ) : true;
            $price_info     = function_exists( 'tutor_utils' ) ? tutor_utils()->get_raw_course_price( $course_id ) : null;

            $regular_price = $price_info->regular_price ?? 0;
            $sale_price    = $price_info->sale_price ?? 0;
            $display_price = $price_info->display_price ?? 0;

            $has_sale     = ( $regular_price && $sale_price && (float) $sale_price < (float) $regular_price );
            $discount_pct = ( $has_sale && (float) $regular_price > 0 ) ? round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 ) : 0;

            $formatted_display_price = ( function_exists( 'tutor_get_formatted_price' ) && $display_price ) ? tutor_get_formatted_price( $display_price ) : ( ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'tutor_price' ) ) ? tutor_utils()->tutor_price( $display_price ) : '₹' . $display_price );
            $formatted_regular_price = ( function_exists( 'tutor_get_formatted_price' ) && $regular_price ) ? tutor_get_formatted_price( $regular_price ) : ( ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'tutor_price' ) ) ? tutor_utils()->tutor_price( $regular_price ) : '₹' . $regular_price );

            if ( ! $is_free ) {
                // Direct checkout link for "Buy Now" (paid courses only)
                $checkout_url = '';
                if ( class_exists( '\Tutor\Ecommerce\CheckoutController' ) ) {
                    $checkout_url = \Tutor\Ecommerce\CheckoutController::get_page_url();
                } elseif ( function_exists( 'tutor_utils' ) ) {
                    $checkout_url = tutor_utils()->get_checkout_page_url();
                }
                $buy_now_link = add_query_arg( array( 'course_id' => $course_id ), $checkout_url );
            }
        }

        $login_url = '';
        if ( ! is_user_logged_in() && function_exists( 'tutor_utils' ) ) {
            $is_tutor_login_disabled = ! tutor_utils()->get_option( 'enable_tutor_native_login', null, true, true );
            $login_url = $is_tutor_login_disabled ? ( isset( $_SERVER['REQUEST_SCHEME'] ) ? wp_login_url( tutor_utils()->get_current_url() ) : '' ) : '';
        }
        ?>
        <div class="vco-course-mobile-sticky-bar<?php echo $can_start_learning ? ' vco-is-enrolled vco-is-start-learning' : ''; ?>" id="vco-course-mobile-sticky-bar">
            <div class="vco-course-sticky-inner">
                <?php if ( ! $can_start_learning ) : ?>
                    <div class="vco-course-sticky-price-col">
                        <span class="vco-course-sticky-price-label"><?php esc_html_e( 'Price', 'tutor' ); ?></span>
                        <div class="vco-course-sticky-price-row">
                            <?php if ( $is_free || ! $is_purchasable || ( ! $display_price && ! $regular_price ) ) : ?>
                                <span class="vco-course-sticky-current-price"><?php esc_html_e( 'Free', 'tutor' ); ?></span>
                                <?php if ( $regular_price && (float) $regular_price > 0 ) : ?>
                                    <del class="vco-course-sticky-regular-price"><?php echo wp_kses_post( $formatted_regular_price ); ?></del>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="vco-course-sticky-current-price"><?php echo wp_kses_post( $formatted_display_price ); ?></span>
                                <?php if ( $has_sale ) : ?>
                                    <del class="vco-course-sticky-regular-price"><?php echo wp_kses_post( $formatted_regular_price ); ?></del>
                                    <?php if ( $discount_pct > 0 ) : ?>
                                        <span class="vco-course-sticky-discount-badge"><?php echo esc_html( $discount_pct ); ?>% OFF</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="vco-course-sticky-action-col">
                    <?php if ( $can_start_learning ) : ?>
                        <a href="<?php echo esc_url( $lesson_url ? $lesson_url : '#' ); ?>" class="vco-course-sticky-btn vco-enrolled-btn vco-start-learning-btn tutor-btn tutor-btn-primary">
                            <span><?php echo esc_html( $enroll_btn_text ); ?></span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    <?php elseif ( $is_free ) : ?>
                        <div class="tutor-course-single-btn-group <?php echo is_user_logged_in() ? '' : 'tutor-course-entry-box-login'; ?>" data-login_url="<?php echo esc_url( $login_url ); ?>">
                            <form class="tutor-enrol-course-form" method="post">
                                <?php wp_nonce_field( tutor()->nonce_action, tutor()->nonce, false ); ?>
                                <input type="hidden" name="tutor_course_id" value="<?php echo esc_attr( $course_id ); ?>">
                                <input type="hidden" name="tutor_course_action" value="_tutor_course_enroll_now">
                                <button type="submit" class="vco-course-sticky-btn vco-enroll-btn tutor-btn tutor-btn-primary tutor-enroll-course-button <?php echo is_user_logged_in() ? 'tutor-static-loader' : ''; ?>">
                                    <span><?php esc_html_e( 'Enroll Now', 'tutor' ); ?></span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </button>
                            </form>
                        </div>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $buy_now_link ); ?>" class="vco-course-sticky-btn vco-buy-now-btn">
                            <span><?php esc_html_e( 'Buy Now', 'tutor' ); ?></span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Override single certificate page template to load from child theme
     */
    public function override_single_certificate_template( $template ) {
        $cert_hash = isset( $_GET['cert_hash'] ) ? sanitize_text_field( $_GET['cert_hash'] ) : '';
        if ( $cert_hash ) {
            $certificate_page_id = function_exists( 'tutor_utils' ) ? (int) tutor_utils()->get_option( 'tutor_certificate_page' ) : 0;
            global $post;
            if ( ( isset( $post ) && (int) $post->ID === $certificate_page_id ) || ( false !== strpos( $template, 'single-certificate.php' ) ) ) {
                $child_cert_template = get_stylesheet_directory() . '/tutor/single-certificate.php';
                if ( file_exists( $child_cert_template ) ) {
                    return $child_cert_template;
                }
            }
        }
        return $template;
    }

    /**
     * Attach AJAX prefilter inside Certificate Builder iframe / editor
     */
    public function attach_certificate_builder_prefilter() {
        static $attached = false;
        if ( $attached ) {
            return;
        }
        $attached = true;
        ?>
        <script>
        (function() {
            function initBuilderFix() {
                if (window.jQuery && window.jQuery.ajaxPrefilter) {
                    window.jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                        var isCertStore = false;
                        if (options.url && (options.url.indexOf('[object') !== -1 || options.url.indexOf('object%20Object') !== -1)) {
                            isCertStore = true;
                        } else if (typeof options.data === 'string' && (options.data.indexOf('tutor_store_certificate_image') !== -1 || options.data.indexOf('tutor_certificate_store_preview') !== -1)) {
                            isCertStore = true;
                        } else if (options.data instanceof FormData && (options.data.get('action') === 'tutor_store_certificate_image' || options.data.get('action') === 'tutor_certificate_store_preview')) {
                            isCertStore = true;
                        } else if (originalOptions.data && (originalOptions.data.action === 'tutor_store_certificate_image' || originalOptions.data.action === 'tutor_certificate_store_preview')) {
                            isCertStore = true;
                        }

                        if (isCertStore) {
                            var targetUrl = (window.tutor_object_cb && window.tutor_object_cb.ajaxUrl) ? window.tutor_object_cb.ajaxUrl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
                            options.url = targetUrl;
                        }
                    });
                } else {
                    setTimeout(initBuilderFix, 25);
                }
            }
            initBuilderFix();
        })();
        </script>
        <?php
    }

    /**
     * Maybe attach prefilter on frontend pages with cert_hash
     */
    public function maybe_attach_prefilter_to_frontend() {
        if ( isset( $_GET['cert_hash'] ) ) {
            $this->attach_certificate_builder_prefilter();
        }
    }

    /**
     * Dynamically replace dead domain URLs in certificate postmeta to avoid canvas tainting
     */
    public function filter_certificate_template_postmeta( $value, $object_id, $meta_key, $single ) {
        if ( 'tutor_certificate_data' === $meta_key || 'tutor_certificate_draft_data' === $meta_key ) {
            remove_filter( 'get_post_metadata', array( $this, 'filter_certificate_template_postmeta' ), 10 );
            $raw_value = get_post_meta( $object_id, $meta_key, $single );
            add_filter( 'get_post_metadata', array( $this, 'filter_certificate_template_postmeta' ), 10, 4 );

            if ( is_string( $raw_value ) && ! empty( $raw_value ) ) {
                $upload_baseurl = wp_upload_dir()['baseurl'];
                $replacements = array(
                    'https://vcacademy.in/wp-content/uploads' => $upload_baseurl,
                    'http://vcacademy.in/wp-content/uploads'  => $upload_baseurl,
                );
                return str_replace( array_keys( $replacements ), array_values( $replacements ), $raw_value );
            }
            return $raw_value;
        }
        return $value;
    }

    /**
     * Render View Certificate action button on completed course cards in student dashboard
     */
    public function render_dashboard_course_certificate_btn( $course_id ) {
        $user_id = get_current_user_id();
        $is_completed = function_exists( 'tutor_utils' ) ? tutor_utils()->is_completed_course( $course_id, $user_id ) : false;
        if ( $is_completed && ! empty( $is_completed->completed_hash ) ) {
            $cert_url = apply_filters( 'tutor_certificate_public_url', $is_completed->completed_hash );
            if ( $cert_url ) {
                ?>
                <a href="<?php echo esc_url( $cert_url ); ?>" 
                   class="tutor-btn tutor-btn-primary tutor-btn-x-small vco-view-certificate-btn" 
                   target="_blank" 
                   @click.stop 
                   style="z-index: 2; position: relative;">
                    <span class="tutor-icon-certificate-landscape"></span>
                    <span><?php esc_html_e( 'View Certificate', 'vconline' ); ?></span>
                </a>
                <?php
            }
        }
    }

    /**
     * Register "My Certificates" in Tutor dashboard navigation
     */
    public function register_dashboard_certificates_nav( $items ) {
        $icon = 'certificate';
        $items['certificates'] = array(
            'title'       => __( 'My Certificates', 'vconline' ),
            'icon'        => $icon,
            'active_icon' => $icon,
            'url'         => trailingslashit( tutor_utils()->get_tutor_dashboard_page_permalink( 'certificates' ) ),
        );
        return $items;
    }

    /**
     * Ensure /dashboard/certificates/ correctly populates query vars
     */
    public function ensure_dashboard_certificates_request( $wp ) {
        if ( isset( $wp->request ) && preg_match( '#^dashboard/certificates/?$#i', $wp->request ) ) {
            $wp->query_vars['pagename'] = 'dashboard';
            $wp->query_vars['tutor_dashboard_page'] = 'certificates';
        }
    }

    /**
     * Ensure certificate URL has trailing slash before query args
     */
    public function ensure_trailing_slash_certificate_url( $url ) {
        if ( is_string( $url ) && false !== strpos( $url, 'tutor-certificate?' ) ) {
            $url = str_replace( 'tutor-certificate?', 'tutor-certificate/?', $url );
        }
        return $url;
    }

    /**
     * Load child theme dashboard certificates template
     */
    public function load_dashboard_certificates_template( $other_location ) {
        global $wp_query;
        $sub_page  = $wp_query->query_vars['tutor_dashboard_sub_page'] ?? '';
        $page_slug = $wp_query->query_vars['tutor_dashboard_page'] ?? '';
        if ( 'certificates' === $sub_page || 'certificates' === $page_slug ) {
            $cert_template = get_stylesheet_directory() . '/tutor/dashboard/certificates.php';
            if ( file_exists( $cert_template ) ) {
                return $cert_template;
            }
        }
        return $other_location;
    }

    /**
     * Register certificates dashboard subpage permalink/rewrite
     */
    public function register_dashboard_certificates_permalink( $permalinks ) {
        $permalinks['certificates'] = array(
            'title' => __( 'My Certificates', 'vconline' ),
        );
        return $permalinks;
    }

    /**
     * Remove Tutor default review popup in favor of custom popup with course card
     */
    public function setup_custom_review_popup() {
        if ( function_exists( 'tutor_course' ) ) {
            remove_action( 'wp_footer', array( tutor_course(), 'popup_review_form' ) );
        }
    }

    /**
     * Custom review popup with completed course card & Access Course button
     */
    public function custom_popup_review_form() {
        if ( ! is_user_logged_in() || ! function_exists( 'tutor_utils' ) ) {
            return;
        }

        $is_learning_area   = tutor_utils()->is_learning_area();
        $is_legacy_learning = tutor_utils()->is_legacy_learning_mode();
        if ( $is_legacy_learning && $is_learning_area ) {
            return;
        }

        // Only run on single course page or learning area
        if ( ! is_single() && ! $is_learning_area ) {
            return;
        }

        $course_id = get_the_ID();
        if ( $is_learning_area && ! \TUTOR\Input::has( 'subpage' ) ) {
            $course_id = wp_get_post_parent_id( wp_get_post_parent_id( get_the_ID() ) );
        }

        if ( empty( $course_id ) ) {
            return;
        }

        $user_id  = get_current_user_id();
        $meta_key = class_exists( 'TUTOR\User' ) && method_exists( 'TUTOR\User', 'get_review_popup_meta' )
            ? \TUTOR\User::get_review_popup_meta( $course_id )
            : 'tutor_review_course_popup_' . $course_id;

        if ( ! $meta_key ) {
            return;
        }

        $review_course_id = (int) get_user_meta( $user_id, $meta_key, true );
        if ( is_single() && $course_id === $review_course_id ) {
            $course_thumb = get_tutor_course_thumbnail_src( 'thumbnail', $course_id );
            $course_title = get_the_title( $course_id );
            $course_url   = get_permalink( $course_id );
            $modal_id     = 'tutor-review-modal-' . $course_id;
            ?>
            <form class="tutor-modal tutor-is-active tutor-course-review-popup-form">
                <div class="tutor-modal-overlay"></div>
                <div class="tutor-modal-window">
                    <div class="tutor-modal-content tutor-modal-content-white">
                        <button type="button" class="tutor-iconic-btn tutor-modal-close-o" data-tutor-modal-close aria-label="<?php esc_attr_e( 'Close', 'tutor' ); ?>">
                            <span class="tutor-icon-times" aria-hidden="true"></span>
                        </button>

                        <div class="tutor-modal-body tutor-text-center">
                            <div class="vco-review-course-banner tutor-mb-24" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; text-align:left; gap:12px; margin-top:16px;">
                                <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                                    <?php if ( $course_thumb ) : ?>
                                        <img src="<?php echo esc_url( $course_thumb ); ?>" alt="<?php echo esc_attr( $course_title ); ?>" style="width:48px; height:48px; object-fit:cover; border-radius:8px; flex-shrink:0;" />
                                    <?php endif; ?>
                                    <div style="min-width:0;">
                                        <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:#ff5500; letter-spacing:0.5px;"><?php esc_html_e( 'Course Completed', 'vconline' ); ?></div>
                                        <div style="font-size:14px; font-weight:600; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html( $course_title ); ?></div>
                                    </div>
                                </div>
                                <a href="<?php echo esc_url( $course_url ); ?>" class="tutor-btn tutor-btn-outline-primary tutor-btn-xs" style="border-radius:6px; font-weight:600; padding:6px 12px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; text-decoration:none; flex-shrink:0;">
                                    <span class="tutor-icon-play-circle"></span>
                                    <span><?php esc_html_e( 'Access Course', 'vconline' ); ?></span>
                                </a>
                            </div>

                            <div id="<?php echo esc_attr( $modal_id ); ?>-title" class="tutor-fs-4 tutor-fw-bold tutor-color-black tutor-mb-8"><?php esc_html_e( 'How would you rate this course?', 'tutor' ); ?></div>
                            <div class="tutor-fs-7 tutor-color-muted"><?php esc_html_e( 'Select Rating', 'tutor' ); ?></div>

                            <input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>"> 
                            <input type="hidden" name="review_id" value="<?php echo esc_attr( isset( $review_id ) ? $review_id : '' ); ?>"/>
                            <input type="hidden" name="action" value="tutor_place_rating" />

                            <div class="tutor-ratings tutor-ratings-xl tutor-ratings-selectable tutor-justify-center tutor-mt-16" tutor-ratings-selectable>
                                <?php
                                    tutor_utils()->star_rating_generator( tutor_utils()->get_rating_value() );
                                ?>
                            </div>

                            <textarea name="review" class="tutor-form-control tutor-mt-24" aria-label="<?php esc_attr_e( 'Tell us about your own personal experience taking this course', 'tutor' ); ?>" placeholder="<?php esc_attr_e( 'Tell us about your own personal experience taking this course. Was it a good match for you?', 'tutor' ); ?>"></textarea>

                            <div class="tutor-d-flex tutor-justify-center tutor-my-32">
                                <button type="button" class="tutor-review-popup-cancel tutor-btn tutor-btn-outline-primary" data-tutor-modal-close>
                                    <?php esc_html_e( 'Cancel', 'tutor' ); ?>
                                </button>
                                <button type="submit" class="tutor_submit_review_btn tutor-btn tutor-btn-primary tutor-ml-20">
                                    <?php esc_html_e( 'Update Review', 'tutor' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php
        }
    }
}

// Instantiate customizations
new Tutor_LMS_Customizations();
