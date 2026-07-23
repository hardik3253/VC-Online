<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tdrg_create_demo_users() {

	$created = 0;
	$existing = 0;

	for ( $i = 1; $i <= 30; $i++ ) {

		$username = 'demo_student_' . sprintf( '%02d', $i );
		$email    = $username . '@example.com';

		$user = get_user_by( 'login', $username );

		if ( $user ) {

			update_user_meta( $user->ID, 'dummy_user', 1 );
			$existing++;

			continue;
		}

		$password = wp_generate_password( 12, false );

		$user_id = wp_create_user(
			$username,
			$password,
			$email
		);

		if ( is_wp_error( $user_id ) ) {
			continue;
		}

		wp_update_user( array(
			'ID'           => $user_id,
			'role'         => 'subscriber',
			'display_name' => 'Student ' . sprintf( '%02d', $i ),
			'nickname'     => 'Student ' . sprintf( '%02d', $i ),
			'first_name'   => 'Student',
			'last_name'    => sprintf( '%02d', $i ),
		) );

		update_user_meta( $user_id, 'dummy_user', 1 );

		$created++;
	}

	?>

	<div class="notice notice-success is-dismissible">
		<p>
			<strong>Done!</strong><br>
			New Users Created: <?php echo esc_html( $created ); ?><br>
			Existing Users Found: <?php echo esc_html( $existing ); ?>
		</p>
	</div>

	<?php
}