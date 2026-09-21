<?php
/**
 * My Certificates Dashboard Template
 *
 * @package VCOnlineChild
 * @subpackage Tutor\Dashboard
 */

defined( 'ABSPATH' ) || exit;

$current_user_id = get_current_user_id();
$completed_course_ids = tutor_utils()->get_completed_courses_ids_by_user( $current_user_id );

$certificates = array();

if ( ! empty( $completed_course_ids ) && is_array( $completed_course_ids ) ) {
	$cert_obj = function_exists( 'TUTOR_CERT' ) ? TUTOR_CERT() : null;

	foreach ( $completed_course_ids as $course_id ) {
		$course = get_post( $course_id );
		if ( ! $course || 'publish' !== $course->post_status ) {
			continue;
		}

		$course_template = get_post_meta( $course_id, 'tutor_course_certificate_template', true );
		if ( in_array( $course_template, array( 'none', 'off' ), true ) ) {
			continue;
		}

		$completed = tutor_utils()->is_completed_course( $course_id, $current_user_id, false );
		if ( ! $completed || empty( $completed->completed_hash ) ) {
			continue;
		}

		$cert_hash = $completed->completed_hash;
		$cert_url  = apply_filters( 'tutor_certificate_public_url', $cert_hash );

		$certificates[] = array(
			'course_id'       => $course_id,
			'course_title'    => get_the_title( $course_id ),
			'course_url'      => get_permalink( $course_id ),
			'thumbnail_url'   => get_tutor_course_thumbnail_src( 'medium', $course_id ),
			'cert_hash'       => $cert_hash,
			'cert_url'        => $cert_url,
			'completion_date' => $completed->completion_date,
		);
	}
}
?>

<div class="vco-dashboard-certificates-wrap">
	<div class="tutor-dashboard-content-header tutor-mb-24">
		<h3 class="tutor-fs-5 tutor-fw-medium tutor-m-0" style="color:var(--tutor-text-primary, #f0f1f1);">
			<?php esc_html_e( 'My Certificates', 'vconline' ); ?>
		</h3>
		<p class="tutor-fs-7 tutor-mt-4 tutor-mb-0" style="color:var(--tutor-text-secondary, #cecfd2);">
			<?php esc_html_e( 'View and download your earned certificates for completed courses.', 'vconline' ); ?>
		</p>
	</div>

	<?php if ( ! empty( $certificates ) ) : ?>
		<div class="vco-certificates-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:24px; padding-top:28px;">
			<?php foreach ( $certificates as $item ) : ?>
				<div class="vco-certificate-card tutor-card" style="border: 1px solid var(--tutor-border-idle, #2d3039); border-radius:12px; overflow:hidden; background:var(--tutor-surface-l1, #1f242f); display:flex; flex-direction:column; box-shadow:0 4px 12px rgba(0,0,0,0.15); transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s;">
					<div class="vco-certificate-thumb" style="position:relative; height:180px; overflow:hidden; background:#0f172a;">
						<?php if ( ! empty( $item['thumbnail_url'] ) ) : ?>
							<img src="<?php echo esc_url( $item['thumbnail_url'] ); ?>" alt="<?php echo esc_attr( $item['course_title'] ); ?>" style="width:100%; height:100%; object-fit:cover;" />
						<?php else : ?>
							<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#1e293b;">
								<span class="tutor-icon-certificate-landscape" style="font-size:48px; color:#64748b;"></span>
							</div>
						<?php endif; ?>
						<span style="position:absolute; top:12px; right:12px; background:rgba(15, 23, 42, 0.85); color:#22c55e; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:4px; backdrop-filter:blur(4px);">
							<span class="tutor-icon-check-circle" style="font-size:13px;"></span> <?php esc_html_e( 'Verified', 'vconline' ); ?>
						</span>
					</div>

					<div class="vco-certificate-body" style="padding:20px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
						<div>
							<h4 style="font-size:16px; font-weight:600; line-height:1.4; margin:0 0 8px 0;">
								<a href="<?php echo esc_url( $item['course_url'] ); ?>" style="color:var(--tutor-text-primary, #f0f1f1); text-decoration:none;">
									<?php echo esc_html( $item['course_title'] ); ?>
								</a>
							</h4>
							<div style="font-size:13px; color:var(--tutor-text-secondary, #cecfd2); margin-bottom:16px; display:flex; flex-direction:column; gap:4px;">
								<div>
									<span style="color:var(--tutor-text-muted, #94969c);"><?php esc_html_e( 'Issued:', 'vconline' ); ?></span>
									<span style="font-weight:500; color:var(--tutor-text-primary, #f0f1f1);"><?php echo esc_html( tutor_i18n_get_formated_date( $item['completion_date'], get_option( 'date_format' ) ) ); ?></span>
								</div>
								<div>
									<span style="color:var(--tutor-text-muted, #94969c);"><?php esc_html_e( 'ID:', 'vconline' ); ?></span>
									<span style="font-family:monospace; font-weight:500; color:var(--tutor-text-primary, #f0f1f1);">#<?php echo esc_html( $item['cert_hash'] ); ?></span>
								</div>
							</div>
						</div>

						<div class="vco-certificate-actions" style="display:flex; justify-content:center; align-items:center; width:100%; padding-top:18px; border-top:1px solid var(--tutor-border-idle, #2d3039);">
							<a href="<?php echo esc_url( $item['cert_url'] ); ?>" target="_blank" class="tutor-btn tutor-btn-primary tutor-btn-sm vco-cert-view-btn" style="min-width:180px; justify-content:center; border-radius:8px; display:inline-flex; align-items:center; gap:8px; text-decoration:none; margin:0 auto; padding:8px 20px;">
								<span class="tutor-icon-certificate-landscape"></span>
								<span><?php esc_html_e( 'View / Download', 'vconline' ); ?></span>
							</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="tutor-empty-state tutor-text-center tutor-py-48 tutor-px-24 tutor-surface-l1 tutor-border tutor-rounded-2xl" style="background:var(--tutor-surface-l1, #1f242f); border:1px dashed var(--tutor-border-idle, #2d3039); border-radius:16px; padding:48px 24px; text-align:center;">
			<div style="width:72px; height:72px; background:var(--tutor-surface-l1-hover, #2d3039); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto;">
				<span class="tutor-icon-certificate-landscape" style="font-size:36px; color:var(--tutor-text-secondary, #cecfd2);"></span>
			</div>
			<h4 style="font-size:18px; font-weight:600; color:var(--tutor-text-primary, #f0f1f1); margin-bottom:8px;">
				<?php esc_html_e( 'No certificates earned yet', 'vconline' ); ?>
			</h4>
			<p style="font-size:14px; color:var(--tutor-text-secondary, #cecfd2); max-width:400px; margin:0 auto 20px auto;">
				<?php esc_html_e( 'Complete your enrolled courses to earn certificates and verify your skills.', 'vconline' ); ?>
			</p>
			<a href="<?php echo esc_url( tutor_utils()->get_tutor_dashboard_page_permalink( 'enrolled-courses' ) ); ?>" class="tutor-btn tutor-btn-primary tutor-btn-sm" style="border-radius:8px; text-decoration:none;">
				<?php esc_html_e( 'View Enrolled Courses', 'vconline' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>
