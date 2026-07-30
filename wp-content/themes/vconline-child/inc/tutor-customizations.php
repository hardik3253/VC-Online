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
}

// Instantiate customizations
new Tutor_LMS_Customizations();
