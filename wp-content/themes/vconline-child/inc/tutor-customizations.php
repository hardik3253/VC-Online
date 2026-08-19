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
}

// Instantiate customizations
new Tutor_LMS_Customizations();
