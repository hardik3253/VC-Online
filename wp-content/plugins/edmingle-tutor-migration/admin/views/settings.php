<?php
/**
 * Admin Settings View
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$settings   = get_option( 'etm_api_settings', array() );
$base_url   = isset( $settings['base_url'] ) ? $settings['base_url'] : '';
$api_token  = isset( $settings['api_token'] ) ? $settings['api_token'] : '';
$api_secret = isset( $settings['api_secret'] ) ? $settings['api_secret'] : '';
?>
<div class="wrap etm-settings">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle Migration Settings', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<form method="post" action="options.php">
		<?php
		// Output security fields for the registered setting "etm_settings_group"
		settings_fields( 'etm_settings_group' );
		// Output setting sections and their fields
		do_settings_sections( 'etm_settings_group' );
		?>
		
		<div class="postbox" style="margin-top:20px; max-width: 800px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'API Credentials', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="etm_base_url"><?php esc_html_e( 'Base URL', 'edmingle-tutor-migration' ); ?></label></th>
							<td>
								<input type="url" id="etm_base_url" name="etm_api_settings[base_url]" value="<?php echo esc_attr( $base_url ); ?>" class="regular-text ltr" required>
								<p class="description"><?php esc_html_e( 'The base URL of your Edmingle instance (e.g., https://api.edmingle.com/).', 'edmingle-tutor-migration' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="etm_api_token"><?php esc_html_e( 'API Token', 'edmingle-tutor-migration' ); ?></label></th>
							<td>
								<input type="password" id="etm_api_token" name="etm_api_settings[api_token]" value="<?php echo esc_attr( $api_token ); ?>" class="regular-text ltr">
								<p class="description"><?php esc_html_e( 'Your Edmingle API Bearer Token or primary API Key.', 'edmingle-tutor-migration' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="etm_api_secret"><?php esc_html_e( 'API Secret', 'edmingle-tutor-migration' ); ?></label></th>
							<td>
								<input type="password" id="etm_api_secret" name="etm_api_settings[api_secret]" value="<?php echo esc_attr( $api_secret ); ?>" class="regular-text ltr">
								<p class="description"><?php esc_html_e( 'Your Edmingle API Secret (if required by your authentication method).', 'edmingle-tutor-migration' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
