<?php
/**
 * Tutor LMS Customizations - Extended Utils Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tutor_Custom_Utils_Extended extends \TUTOR\Utils {

    public function get_total_enrolments( $status, $search_term = '', $course_id = '', $date = '' ) {
        global $wpdb;
        $status      = sanitize_text_field( $status );
        $course_id   = sanitize_text_field( $course_id );
        $date        = sanitize_text_field( $date );
        $search_term = sanitize_text_field( $search_term );

        $search_term_raw = $search_term;
        $search_term     = '%' . $wpdb->esc_like( $search_term ) . '%';

        $course_query = '';
        if ( '' !== $course_id ) {
            $course_query = "AND course.ID = $course_id";
        }

        $date_query = '';
        if ( '' !== $date ) {
            $date_query = "AND DATE(enrol.post_date) = CAST('$date' AS DATE) ";
        }

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

        $course_query = '';
        if ( '' !== $course_id ) {
            $course_query = "AND course.ID = $course_id";
        }

        $date_query = '';
        if ( '' !== $date ) {
            $date_query = "AND DATE(enrol.post_date) = CAST('$date' AS DATE) ";
        }

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

        $order_query = 'ORDER BY user.user_registered DESC';
        if ( '' !== $order ) {
            $is_valid_sql = sanitize_sql_orderby( $order );
            if ( $is_valid_sql ) {
                $order_query = "ORDER BY user.user_registered {$order}";
            }
        }
        $search_term_raw = $search_term;
        $search_term     = '%' . $wpdb->esc_like( $search_term ) . '%';

        // Select all users, but exclude administrators and instructors
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user.* FROM {$wpdb->users} AS user
                LEFT JOIN {$wpdb->usermeta} AS meta 
                    ON user.ID = meta.user_id AND meta.meta_key = '{$wpdb->prefix}capabilities'
                {$course_join}
                WHERE (meta.meta_value IS NULL OR (meta.meta_value NOT LIKE '%administrator%' AND meta.meta_value NOT LIKE '%tutor_instructor%'))
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
                LEFT JOIN {$wpdb->usermeta} AS meta 
                    ON user.ID = meta.user_id AND meta.meta_key = '{$wpdb->prefix}capabilities'
                {$course_join}
                WHERE (meta.meta_value IS NULL OR (meta.meta_value NOT LIKE '%administrator%' AND meta.meta_value NOT LIKE '%tutor_instructor%'))
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
