<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate random review date
 */
function tdrg_random_review_date() {

	$days = rand( 5, 180 );

	return date(
		'Y-m-d H:i:s',
		strtotime( "-{$days} days +" . rand( 0, 86400 ) . " seconds" )
	);
}


/**
 * Random Rating
 * 80% = 5 Star
 * 20% = 4 Star
 */
function tdrg_random_rating() {

	return rand(1,100) <= 80 ? 5 : 4;
}


/**
 * Review Text
 */
function tdrg_get_review_text( $course_title ) {

	$course_title = strtolower( $course_title );

	$makeup = array(

		'Amazing makeup course.',
		'Very informative and practical.',
		'Perfect for beginners.',
		'Excellent bridal makeup training.',
		'Loved every module.',
		'Trainer explained everything clearly.',
		'Highly recommended.',
		'Very useful techniques.',
		'Professional quality training.',
		'Worth every minute.'

	);

	$hair = array(

		'Excellent haircut techniques.',
		'Loved the styling methods.',
		'Very professional course.',
		'Amazing explanation.',
		'Easy to follow.',
		'Very practical learning.',
		'Modern salon techniques.',
		'Useful for professionals.',
		'Excellent trainer.',
		'Highly recommended.'

	);

	$korean = array(

		'Loved the Korean styling.',
		'Beautiful layering techniques.',
		'Amazing finishing.',
		'Very unique methods.',
		'Professional training.',
		'Easy to understand.',
		'Excellent demonstrations.',
		'Very useful course.',
		'Highly recommended.',
		'Fantastic content.'

	);

	$generic = array(

		'Excellent course.',
		'Very informative.',
		'Highly recommended.',
		'Loved this course.',
		'Amazing trainer.',
		'Very practical.',
		'Worth the money.',
		'Excellent explanations.',
		'Five star experience.',
		'Very happy with this course.'

	);

	if ( strpos( $course_title, 'makeup' ) !== false ) {
		return $makeup[ array_rand( $makeup ) ];
	}

	if (
		strpos( $course_title, 'hair' ) !== false ||
		strpos( $course_title, 'barcode' ) !== false
	) {
		return $hair[ array_rand( $hair ) ];
	}

	if (
		strpos( $course_title, 'korean' ) !== false ||
		strpos( $course_title, 'meo' ) !== false
	) {
		return $korean[ array_rand( $korean ) ];
	}

	return $generic[ array_rand( $generic ) ];

}


/**
 * Generate Reviews
 */
function tdrg_generate_reviews() {

	global $wpdb;

	if ( ! class_exists( '\TUTOR\Ajax' ) ) {

		?>
		<div class="notice notice-error">
			<p>Tutor LMS Ajax class not found.</p>
		</div>
		<?php

		return;
	}

	$courses = get_posts(array(
		'post_type'      => 'courses',
		'post_status'    => 'publish',
		'posts_per_page' => -1
	));

	$users = get_users(array(
		'meta_key'   => 'dummy_user',
		'meta_value' => 1
	));

	$total_reviews = 0;
	$skipped = 0;

    	foreach ( $courses as $course ) {

		$course_id = $course->ID;
		$title     = $course->post_title;

		foreach ( $users as $user ) {

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT comment_ID
					FROM {$wpdb->comments}
					WHERE comment_post_ID=%d
					AND user_id=%d
					AND comment_type='tutor_course_rating'
					LIMIT 1",
					$course_id,
					$user->ID
				)
			);

			if ( $exists ) {
				$skipped++;
				continue;
			}

			$rating = tdrg_random_rating();
			$review = tdrg_get_review_text( $title );

			// Generate review using Tutor LMS
			tdrg_add_review(
                $user->ID,
                $course_id,
                $rating,
                $review
            );

			$new_comment = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT comment_ID
					FROM {$wpdb->comments}
					WHERE comment_post_ID=%d
					AND user_id=%d
					AND comment_type='tutor_course_rating'
					ORDER BY comment_ID DESC
					LIMIT 1",
					$course_id,
					$user->ID
				)
			);

			if ( $new_comment ) {

				$date = tdrg_random_review_date();

				$wpdb->update(
					$wpdb->comments,
					array(

						'comment_author'   => $user->user_login,
						'comment_agent'    => 'TutorLMSPlugin',
						'comment_approved' => 'approved',
						'comment_date'     => $date,
						'comment_date_gmt' => get_gmt_from_date( $date ),

					),
					array(
						'comment_ID' => $new_comment
					)
				);

				update_comment_meta(
					$new_comment,
					'dummy_review',
					1
				);

				update_comment_meta(
					$new_comment,
					'tutor_rating',
					$rating
				);

				$total_reviews++;

			}

		}

	}

	?>

	<div class="notice notice-success is-dismissible">

		<h2>Dummy Reviews Generated Successfully</h2>

		<p>

			<strong>Total Reviews Created:</strong>

			<?php echo esc_html( $total_reviews ); ?>

		</p>

		<p>

			<strong>Skipped Existing Reviews:</strong>

			<?php echo esc_html( $skipped ); ?>

		</p>

	</div>

	<?php

}

/**
 * Add Tutor LMS Review
 */
function tdrg_add_review($user_id, $course_id, $rating, $review) {

    global $wpdb;

    $user = get_userdata($user_id);

    if (!$user) {
        return false;
    }

    // Already reviewed?
    $comment_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT comment_ID
            FROM {$wpdb->comments}
            WHERE comment_post_ID=%d
            AND user_id=%d
            AND comment_type='tutor_course_rating'
            LIMIT 1",
            $course_id,
            $user_id
        )
    );

    $date = tdrg_random_review_date();

    if ($comment_id) {

        $wpdb->update(
            $wpdb->comments,
            array(
                'comment_content'  => $review,
                'comment_author'   => $user->user_login,
                'comment_agent'    => 'TutorLMSPlugin',
                'comment_approved' => 'approved',
                'comment_date'     => $date,
                'comment_date_gmt' => get_gmt_from_date($date),
            ),
            array(
                'comment_ID' => $comment_id,
            )
        );

        update_comment_meta(
            $comment_id,
            'tutor_rating',
            $rating
        );

    } else {

        $wpdb->insert(
            $wpdb->comments,
            array(

                'comment_post_ID'      => $course_id,
                'comment_author'       => $user->user_login,
                'comment_author_email' => $user->user_email,
                'user_id'              => $user_id,

                'comment_content'      => $review,

                'comment_type'         => 'tutor_course_rating',
                'comment_agent'        => 'TutorLMSPlugin',

                'comment_approved'     => 'approved',

                'comment_date'         => $date,
                'comment_date_gmt'     => get_gmt_from_date($date),

            )
        );

        $comment_id = $wpdb->insert_id;

        add_comment_meta(
            $comment_id,
            'tutor_rating',
            $rating
        );

    }

    update_comment_meta(
        $comment_id,
        'dummy_review',
        1
    );

    return $comment_id;

}