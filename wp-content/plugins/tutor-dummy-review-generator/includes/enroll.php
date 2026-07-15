<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tdrg_enroll_users() {

	if ( ! function_exists( 'tutor_utils' ) ) {
		?>
		<div class="notice notice-error">
			<p>Tutor LMS is not active.</p>
		</div>
		<?php
		return;
	}

	$courses = get_posts( array(
		'post_type'      => 'courses',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$users = get_users( array(
		'meta_key'   => 'dummy_user',
		'meta_value' => 1,
		'fields'     => array( 'ID', 'user_login' ),
	) );

	$created = 0;
	$skipped = 0;

	foreach ( $courses as $course_id ) {

		foreach ( $users as $user ) {

			if ( tutor_utils()->is_enrolled( $course_id, $user->ID ) ) {
				$skipped++;
				continue;
			}

			// Enroll user
			tutor_utils()->do_enroll( $course_id, $user->ID );

			$created++;
		}
	}
	?>

	<div class="notice notice-success is-dismissible">
		<p>
			<strong>Enrollment Completed</strong><br>
			New Enrollments: <?php echo esc_html( $created ); ?><br>
			Skipped (Already Enrolled): <?php echo esc_html( $skipped ); ?>
		</p>
	</div>

	<?php
}