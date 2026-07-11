<?php
/**
 * Order placement success - Theme Override
 *
 * INSTALLATION:
 * Place this file at:
 *   wp-content/themes/YOUR-THEME/tutor/ecommerce/order-placement-success.php
 *
 * Tutor's tutor_get_template() checks the theme folder FIRST before the plugin,
 * so this file will automatically take priority — no functions.php code needed.
 *
 * CHANGES FROM ORIGINAL:
 *  1. "Order Confirmed"   → "Course Confirmed"
 *  2. "Check Order List" button → REMOVED
 *  3. "Continue Shopping" → "Continue Learning"
 *     Redirects to the purchased course page (if single course order),
 *     otherwise falls back to the Enrolled Courses dashboard page.
 *
 * Variables available (extracted by tutor_load_template before include):
 *   $order_id     (int)    — the order ID from the URL query param
 *   $order_status (string) — 'success'
 */

tutor_utils()->tutor_custom_header();

// ── Build the "Continue Learning" URL ────────────────────────────────────────
$continue_url = tutor_utils()->get_tutor_dashboard_page_permalink( 'enrolled-courses' );

if ( $order_id ) {
    $order_data = ( new \Tutor\Models\OrderModel() )->get_order_by_id( $order_id );

    if ( $order_data && ! empty( $order_data->items ) ) {
        $items = (array) $order_data->items;

        if ( count( $items ) === 1 ) {
            // Single course purchased — link directly to that course
            $course_id = (int) $items[0]->id;
            if ( $course_id ) {
                $course_url = get_permalink( $course_id );
                if ( $course_url ) {
                    $continue_url = $course_url;
                }
            }
        }
        // Multiple courses → keep enrolled-courses dashboard URL (default above)
    }
}
// ─────────────────────────────────────────────────────────────────────────────
?>

<div class="tutor-container">
    <div class="tutor-order-status-wrapper">
        <div class="tutor-d-flex tutor-flex-column tutor-align-center tutor-gap-4">

            <div class="tutor-order-status-icon">
                <img
                    src="<?php echo esc_attr( tutor()->url . 'assets/images/orders/order-confirmed.svg' ); ?>"
                    alt="<?php esc_attr_e( 'course confirmed', 'tutor' ); ?>"
                >
            </div>

            <div class="tutor-order-status-content">
                <!-- CHANGE 1: "Order Confirmed" → "Course Confirmed" -->
                <h2 class="tutor-fs-4 tutor-fw-medium tutor-color-black tutor-mb-4">
                    <?php esc_html_e( 'Welcome to VC Online! ', 'tutor' ); ?>
                </h2>
                <p class="tutor-fs-6 tutor-color-secondary tutor-mb-0">
                    <?php echo esc_html(
                        apply_filters(
                            'tutor_order_placement_success_message',
                            __( 'Your course access is now active. A confirmation email with all the details is on its way to you.', 'tutor' ),
                            $order_id,
                            $order_status
                        )
                    ); ?>
                </p>
            </div>

        </div>

        <div class="tutor-d-flex tutor-gap-2 tutor-align-center tutor-justify-center tutor-flex-wrap">

            <!-- CHANGE 2: "Check Order List" button removed entirely -->

            <!-- CHANGE 3: "Continue Shopping" → "Continue Learning" with smart redirect -->
            <a href="<?php echo esc_url( $continue_url ); ?>" class="tutor-btn tutor-btn-primary">
                <?php esc_html_e( 'Start Learning', 'tutor' ); ?>
            </a>

        </div>
    </div>
</div>

<?php tutor_utils()->tutor_custom_footer(); ?>