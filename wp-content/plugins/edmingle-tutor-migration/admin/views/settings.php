<?php
/**
 * Settings View (Phase 0)
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$base_url = get_option( 'etm_api_base_url', '' );
$email    = get_option( 'etm_admin_email', '' );
$password = get_option( 'etm_admin_password', '' ) ? '************' : ''; // Do not display raw password

?>
<div class="wrap etm-settings">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle Migration Settings', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<form method="post" action="options.php">
		<?php settings_fields( 'etm_settings_group' ); ?>
		<?php do_settings_sections( 'etm_settings_group' ); ?>
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="etm_api_base_url"><?php esc_html_e( 'Edmingle Base URL', 'edmingle-tutor-migration' ); ?></label></th>
				<td>
					<input type="url" id="etm_api_base_url" name="etm_api_base_url" value="<?php echo esc_attr( $base_url ); ?>" class="regular-text" placeholder="https://yourcompany.edmingle.com" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="etm_admin_email"><?php esc_html_e( 'Admin Email', 'edmingle-tutor-migration' ); ?></label></th>
				<td>
					<input type="email" id="etm_admin_email" name="etm_admin_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" required />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="etm_admin_password"><?php esc_html_e( 'Admin Password', 'edmingle-tutor-migration' ); ?></label></th>
				<td>
					<input type="password" id="etm_admin_password" name="etm_admin_password" value="<?php echo esc_attr( $password ); ?>" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'Password is encrypted before saving.', 'edmingle-tutor-migration' ); ?></p>
				</td>
			</tr>
		</table>
		
		<?php submit_button(); ?>
	</form>
</div>
