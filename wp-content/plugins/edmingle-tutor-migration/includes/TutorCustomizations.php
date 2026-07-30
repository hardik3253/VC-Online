<?php
/**
 * TutorLMS Customizations and Overrides
 *
 * @package Edmingle_Tutor_Migration\Includes
 */

namespace ETM\Includes;

class TutorCustomizations {

    /**
     * Initialize hooks
     */
    public function register() {
        // 1. Prevent enrollment cancellation/downgrading if the student has a completed order
        add_action( 'tutor_enrollment/after/cancel', array( $this, 'prevent_enrollment_cancellation_if_paid' ), 10, 1 );
        add_action( 'tutor_enrollment/after/pending', array( $this, 'prevent_enrollment_cancellation_if_paid' ), 10, 1 );

        // 2. Sync newly registered WordPress users to TutorLMS students list and save mobile/phone number
        add_action( 'user_register', array( $this, 'save_mobile_and_sync_student' ) );

        // 3. Inject Mobile Number column on the TutorLMS Students admin page
        add_action( 'admin_footer', array( $this, 'add_mobile_to_tutor_students_page' ) );

        // 4. Override the global tutor utils object with our extended version
        add_action( 'init', array( $this, 'override_tutor_utils_object' ), 999 );
    }

    /**
     * Prevent enrollment cancellation/downgrading if the student has a completed order for the course
     */
    public function prevent_enrollment_cancellation_if_paid( $enrollment_id ) {
        global $wpdb;

        // Get enrollment details
        $enrollment = get_post( $enrollment_id );
        if ( ! $enrollment || 'tutor_enrolled' !== $enrollment->post_type ) {
            return;
        }

        $student_id = $enrollment->post_author;
        $course_id = $enrollment->post_parent;

        // Check if the user has any completed order in tutor_orders table for this course
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
            // Restore enrollment status to completed
            $wpdb->update(
                $wpdb->posts,
                array( 'post_status' => 'completed' ),
                array( 'ID' => $enrollment_id )
            );

            // Clear WordPress post cache
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

        // Automatically add the user as a TutorLMS student
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
     * Override the global tutor utils object with our extended version
     */
    public function override_tutor_utils_object() {
        if ( class_exists( '\TUTOR\Utils' ) && ! ( $GLOBALS['tutor_utils_object'] instanceof ETM_Tutor_Utils_Extended ) ) {
            $GLOBALS['tutor_utils_object'] = new ETM_Tutor_Utils_Extended();
        }
    }
}

/**
 * Version compatibility: extend Tutor Utils class to override students and enrollment list retrieval
 */
if ( class_exists( '\TUTOR\Utils' ) ) {
    class ETM_Tutor_Utils_Extended extends \TUTOR\Utils {

        public function get_total_enrolments( $status, $search_term = '', $course_id = '', $date = '' ) {
            global $wpdb;
            $status      = sanitize_text_field( $status );
            $course_id   = sanitize_text_field( $course_id );
            $date        = sanitize_text_field( $date );
            $search_term = sanitize_text_field( $search_term );

            $search_term_raw = $search_term;
            $search_term     = '%' . $wpdb->esc_like( $search_term ) . '%';

            // Add course id in where clause.
            $course_query = '';
            if ( '' !== $course_id ) {
                $course_query = "AND course.ID = $course_id";
            }

            // Add date in where clause.
            $date_query = '';
            if ( '' !== $date ) {
                $date_query = "AND DATE(enrol.post_date) = CAST('$date' AS DATE) ";
            }

            // Add status in where clause.
            if ( 'approved' === $status ) {
                $status = 'completed';
            } elseif ( 'cancelled' === $status ) {
                $status = array( 'cancel', 'canceled', 'cancelled' );
            } elseif ( 'all' === $status ) {
                $status = '';
            }

            $status_query = '';
            if ( is_array( $status ) && count( $status ) ) {
                $in_clause    = \Tutor\Helpers\QueryHelper::prepare_in_clause( $status );
                $status_query = "AND enrol.post_status IN ({$in_clause})";
            } elseif ( ! empty( $status ) ) {
                $status_query = "AND enrol.post_status = '$status' ";
            }

            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(enrol.ID)
                FROM 	{$wpdb->posts} enrol
                        INNER JOIN {$wpdb->posts} course
                                ON enrol.post_parent = course.ID
                                AND course.post_type != 'course-bundle'
                        INNER JOIN {$wpdb->users} student
                                ON enrol.post_author = student.ID
                WHERE 	enrol.post_type = %s
                        {$status_query}
                        {$course_query}
                        {$date_query}
                        AND ( enrol.ID LIKE %s OR student.display_name LIKE %s OR student.user_email = %s OR course.post_title LIKE %s );
                ",
                    'tutor_enrolled',
                    $search_term,
                    $search_term,
                    $search_term_raw,
                    $search_term
                )
            );

            return (int) $count;
        }

        public function get_enrolments( $status, $start = 0, $limit = 10, $search_term = '', $course_id = '', $date = '', $order = 'DESC' ) {
            global $wpdb;
            $status      = sanitize_text_field( $status );
            $course_id   = sanitize_text_field( $course_id );
            $date        = sanitize_text_field( $date );
            $search_term = sanitize_text_field( $search_term );

            $search_term_raw = $search_term;
            $search_term     = '%' . $wpdb->esc_like( $search_term ) . '%';

            // add course id in where clause.
            $course_query = '';
            if ( '' !== $course_id ) {
                $course_query = "AND course.ID = $course_id";
            }

            // add date in where clause.
            $date_query = '';
            if ( '' !== $date ) {
                $date_query = "AND DATE(enrol.post_date) = CAST('$date' AS DATE) ";
            }

            // add status in where clause.
            if ( 'approved' === $status ) {
                $status = 'completed';
            } elseif ( 'cancelled' === $status ) {
                $status = array( 'cancel', 'canceled', 'cancelled' );
            } elseif ( 'all' === $status ) {
                $status = '';
            }

            $status_query = '';
            if ( is_array( $status ) && count( $status ) ) {
                $in_clause    = \Tutor\Helpers\QueryHelper::prepare_in_clause( $status );
                $status_query = "AND enrol.post_status IN ({$in_clause})";
            } elseif ( ! empty( $status ) ) {
                $status_query = "AND enrol.post_status = '$status' ";
            }

            $enrolments = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT enrol.ID AS enrol_id,
                        enrol.post_author AS student_id,
                        enrol.post_date AS enrol_date,
                        enrol.post_title AS enrol_title,
                        enrol.post_status AS status,
                        enrol.post_parent AS course_id,
                        course.post_title AS course_title,
                        course.guid,
                        student.user_nicename,
                        student.user_email,
                        student.display_name
                FROM 	{$wpdb->posts} enrol
                        INNER JOIN {$wpdb->posts} course
                                ON enrol.post_parent = course.ID
                                AND course.post_type != 'course-bundle'
                        INNER JOIN {$wpdb->users} student
                                ON enrol.post_author = student.ID
                WHERE 	enrol.post_type = %s
                        {$status_query}
                        {$course_query}
                        {$date_query}
                        AND ( enrol.ID LIKE %s OR student.display_name LIKE %s OR student.user_email = %s OR course.post_title LIKE %s )
                ORDER BY enrol_id {$order}
                LIMIT 	%d, %d;
                ",
                    'tutor_enrolled',
                    $search_term,
                    $search_term,
                    $search_term_raw,
                    $search_term,
                    $start,
                    $limit
                )
            );

            return $enrolments;
        }

        public function get_students( $start = 0, $limit = 10, $search_term = '', $course_id = '', $date = '', $order = 'DESC' ) {
            global $wpdb;

            $start       = sanitize_text_field( $start );
            $limit       = sanitize_text_field( $limit );
            $search_term = sanitize_text_field( $search_term );
            $course_id   = sanitize_text_field( $course_id );
            $date        = sanitize_text_field( $date );

            $course_query = '';
            $course_join = '';
            if ( '' !== $course_id ) {
                $course_id    = (int) $course_id;
                $course_join  = "INNER JOIN {$wpdb->posts} posts ON user.ID = posts.post_author AND posts.post_type = 'tutor_enrolled' AND posts.post_status = 'completed'";
                $course_query = "AND posts.post_parent = {$course_id}";
            }

            $date_query = '';
            if ( '' !== $date ) {
                $date_query = "AND DATE(user.user_registered) = CAST('$date' AS DATE)";
            }

            $order_query = 'ORDER BY user.ID DESC';
            if ( '' !== $order ) {
                $is_valid_sql = sanitize_sql_orderby( $order );
                if ( $is_valid_sql ) {
                    $order_query = "ORDER BY user.display_name {$order}";
                }
            }
            $search_term_raw = $search_term;
            $search_term     = '%' . $wpdb->esc_like( $search_term ) . '%';

            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user.* FROM {$wpdb->users} AS user
                    INNER JOIN {$wpdb->usermeta} AS meta 
                        ON user.ID = meta.user_id AND meta.meta_key = '_is_tutor_student'
                    {$course_join}
                    WHERE 1=1
                        {$course_query}
                        {$date_query}
                        AND (user.display_name LIKE %s OR user.user_email = %s OR user.user_login LIKE %s)
                    GROUP BY user.ID
                    {$order_query}
                    LIMIT %d, %d
                ",
                    $search_term,
                    $search_term_raw,
                    $search_term,
                    $start,
                    $limit
                )
            );

            return $students;
        }

        public function get_total_students( $search_term = '', $course_id = '', $date = '' ): int {
            global $wpdb;

            $search_term = sanitize_text_field( $search_term );
            $course_id   = sanitize_text_field( $course_id );
            $date        = sanitize_text_field( $date );

            $course_query = '';
            $course_join = '';
            if ( '' !== $course_id ) {
                $course_id    = (int) $course_id;
                $course_join  = "INNER JOIN {$wpdb->posts} posts ON user.ID = posts.post_author AND posts.post_type = 'tutor_enrolled' AND posts.post_status = 'completed'";
                $course_query = "AND posts.post_parent = {$course_id}";
            }

            $date_query = '';
            if ( '' !== $date ) {
                $date_query = "AND DATE(user.user_registered) = CAST('$date' AS DATE)";
            }

            $search_term_raw = $search_term;
            $search_term     = '%' . $wpdb->esc_like( $search_term ) . '%';

            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT user.ID) FROM {$wpdb->users} AS user
                    INNER JOIN {$wpdb->usermeta} AS meta 
                        ON user.ID = meta.user_id AND meta.meta_key = '_is_tutor_student'
                    {$course_join}
                    WHERE 1=1
                        {$course_query}
                        {$date_query}
                        AND (user.display_name LIKE %s OR user.user_email = %s OR user.user_login LIKE %s)
                ",
                    $search_term,
                    $search_term_raw,
                    $search_term
                )
            );

            return (int) $count;
        }
    }
}
