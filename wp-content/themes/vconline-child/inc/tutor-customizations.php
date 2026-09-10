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
}

// Instantiate customizations
new Tutor_LMS_Customizations();
