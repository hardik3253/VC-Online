<?php
/**
 * Razorpay Payment Verification & Tutor LMS Order Sync
 *
 * Ensures that whenever a student completes payment on Razorpay:
 * 1. The Tutor LMS Order is instantly marked as Paid and Completed.
 * 2. The exact paid amount, Razorpay Payment ID, and fees are recorded.
 * 3. The student is immediately and automatically enrolled in the course.
 * 4. The course page reflects "Start Learning" / "Continue Learning" instead of "Buy Now".
 * 5. Handles frontend POST returns, query redirects, API verification, and background reconciliation.
 *
 * @package VCOnlineChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Helper: Retrieve Razorpay API credentials from Tutor LMS settings.
 *
 * @return array Associative array with key_id, key_secret, webhook_secret.
 */
function vc_online_get_razorpay_credentials() {
	$key_id         = '';
	$key_secret     = '';
	$webhook_secret = '';

	// Priority 1: Check Tutor LMS RazorpayConfig class if loaded
	if ( class_exists( '\TutorRazorpay\RazorpayConfig' ) ) {
		try {
			$config         = new \TutorRazorpay\RazorpayConfig();
			$key_id         = $config->getKeyID();
			$key_secret     = $config->getKeySecret();
			$webhook_secret = $config->getWebhookSecretKey();
		} catch ( \Throwable $e ) {
			// Fallback below
		}
	}

	// Priority 2: Check Tutor LMS settings array
	if ( empty( $key_id ) || empty( $key_secret ) ) {
		if ( class_exists( '\Tutor\Ecommerce\Settings' ) ) {
			$settings = \Tutor\Ecommerce\Settings::get_payment_gateway_settings( 'razorpay' );
			if ( is_array( $settings ) ) {
				$key_id         = $key_id ? $key_id : ( $settings['key_id'] ?? '' );
				$key_secret     = $key_secret ? $key_secret : ( $settings['key_secret'] ?? '' );
				$webhook_secret = $webhook_secret ? $webhook_secret : ( $settings['webhook_secret'] ?? '' );

				if ( ( empty( $key_id ) || empty( $key_secret ) ) && ! empty( $settings['fields'] ) && is_array( $settings['fields'] ) ) {
					foreach ( $settings['fields'] as $k => $field ) {
						$val = is_array( $field ) ? ( $field['value'] ?? '' ) : $field;
						if ( is_string( $val ) ) {
							if ( strpos( $val, 'rzp_' ) === 0 ) {
								$key_id = $val;
							} elseif ( strlen( $val ) >= 20 && empty( $key_secret ) ) {
								$key_secret = $val;
							}
						}
					}
				}
			}
		}
	}

	// Priority 3: Fallback constants if defined in wp-config.php
	if ( empty( $key_id ) && defined( 'RAZORPAY_KEY_ID' ) ) {
		$key_id = RAZORPAY_KEY_ID;
	}
	if ( empty( $key_secret ) && defined( 'RAZORPAY_KEY_SECRET' ) ) {
		$key_secret = RAZORPAY_KEY_SECRET;
	}

	return array(
		'key_id'         => trim( (string) $key_id ),
		'key_secret'     => trim( (string) $key_secret ),
		'webhook_secret' => trim( (string) $webhook_secret ),
	);
}

/**
 * 2. Helper: Fetch payment details from Razorpay API.
 *
 * @param string $payment_id Razorpay payment ID (e.g. pay_XXXXX).
 * @return array|false Payment details array on success, false on failure.
 */
function vc_online_fetch_razorpay_payment( $payment_id ) {
	$creds = vc_online_get_razorpay_credentials();
	if ( empty( $creds['key_id'] ) || empty( $creds['key_secret'] ) || empty( $payment_id ) ) {
		return false;
	}

	$url      = 'https://api.razorpay.com/v1/payments/' . rawurlencode( $payment_id );
	$response = wp_remote_get( $url, array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( $creds['key_id'] . ':' . $creds['key_secret'] ),
			'Accept'        => 'application/json',
		),
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		error_log( '[VC_Online] Razorpay API error fetching payment ' . $payment_id . ': ' . $response->get_error_message() );
		return false;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 === $code && is_array( $body ) && ! empty( $body['id'] ) ) {
		return $body;
	}

	return false;
}

/**
 * 3. Helper: Fetch payments for a specific Razorpay Order ID.
 *
 * @param string $razorpay_order_id Razorpay order ID (e.g. order_XXXXX).
 * @return array List of payment records.
 */
function vc_online_fetch_razorpay_order_payments( $razorpay_order_id ) {
	$creds = vc_online_get_razorpay_credentials();
	if ( empty( $creds['key_id'] ) || empty( $creds['key_secret'] ) || empty( $razorpay_order_id ) ) {
		return array();
	}

	$url      = 'https://api.razorpay.com/v1/orders/' . rawurlencode( $razorpay_order_id ) . '/payments';
	$response = wp_remote_get( $url, array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( $creds['key_id'] . ':' . $creds['key_secret'] ),
			'Accept'        => 'application/json',
		),
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		return array();
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 === $code && isset( $body['items'] ) && is_array( $body['items'] ) ) {
		return $body['items'];
	}

	return array();
}

/**
 * 4. Core Function: Complete a Tutor LMS Order and Enroll the Student.
 *
 * Marks the order as paid & completed, records transaction ID & fees,
 * enrolls the student into course(s), clears cart, and busts caches.
 *
 * @param int    $order_id Tutor LMS Order ID.
 * @param string $payment_id Razorpay Payment ID (e.g. pay_XXXXX).
 * @param array  $payment_data Razorpay payment entity data.
 * @return bool True on success, false on failure.
 */
function vc_online_complete_tutor_order( $order_id, $payment_id = '', $payment_data = array() ) {
	global $wpdb;
	$order_id = (int) $order_id;
	if ( ! $order_id ) {
		return false;
	}

	$orders_table = $wpdb->prefix . 'tutor_orders';
	$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE id = %d", $order_id ) );
	if ( ! $order ) {
		error_log( "[VC_Online] Order #{$order_id} not found." );
		return false;
	}

	$student_id = (int) $order->user_id;

	// Calculate amounts from payment data if available
	$total_amount = (float) $order->total_price;
	$fee          = 0.00;
	$tax          = 0.00;
	$earnings     = $total_amount;

	if ( ! empty( $payment_data['amount'] ) ) {
		$total_amount = round( (float) $payment_data['amount'] / 100, 2 );
	}
	if ( ! empty( $payment_data['fee'] ) ) {
		$fee = round( (float) $payment_data['fee'] / 100, 2 );
	}
	if ( ! empty( $payment_data['tax'] ) ) {
		$tax = round( (float) $payment_data['tax'] / 100, 2 );
	}
	$earnings = round( $total_amount - $fee, 2 );

	$prev_payment_status = $order->payment_status;

	// Update order table in database
	$update_fields = array(
		'order_status'     => 'completed', // \Tutor\Models\OrderModel::ORDER_COMPLETED
		'payment_status'   => 'paid',      // \Tutor\Models\OrderModel::PAYMENT_PAID
		'payment_method'   => 'razorpay',
		'net_payment'      => $total_amount,
		'fees'             => $fee,
		'earnings'         => $earnings,
		'updated_at_gmt'   => current_time( 'mysql', true ),
	);

	if ( ! empty( $payment_id ) ) {
		$update_fields['transaction_id'] = sanitize_text_field( $payment_id );
	}
	if ( ! empty( $payment_data ) ) {
		$update_fields['payment_payloads'] = wp_json_encode( $payment_data );
	}

	$wpdb->update(
		$orders_table,
		$update_fields,
		array( 'id' => $order_id ),
		array( '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' ),
		array( '%d' )
	);

	// Store meta information
	$ordermeta_table = $wpdb->prefix . 'tutor_ordermeta';
	if ( ! empty( $payment_id ) ) {
		$wpdb->replace(
			$ordermeta_table,
			array(
				'order_id'       => $order_id,
				'meta_key'       => 'razorpay_payment_id',
				'meta_value'     => $payment_id,
				'created_at_gmt' => current_time( 'mysql', true ),
				'created_by'     => $student_id,
				'updated_at_gmt' => current_time( 'mysql', true ),
				'updated_by'     => $student_id,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
		);
	}
	if ( ! empty( $payment_data['order_id'] ) ) {
		$wpdb->replace(
			$ordermeta_table,
			array(
				'order_id'       => $order_id,
				'meta_key'       => 'razorpay_order_id',
				'meta_value'     => $payment_data['order_id'],
				'created_at_gmt' => current_time( 'mysql', true ),
				'created_by'     => $student_id,
				'updated_at_gmt' => current_time( 'mysql', true ),
				'updated_by'     => $student_id,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
		);
	}

	// Add order history entry
	$history_msg = sprintf(
		'Payment verified and completed via Razorpay. Payment ID: %s, Amount: INR %s, Fee: INR %s',
		$payment_id ? $payment_id : 'N/A',
		number_format( $total_amount, 2 ),
		number_format( $fee, 2 )
	);
	$wpdb->insert(
		$ordermeta_table,
		array(
			'order_id'       => $order_id,
			'meta_key'       => 'history',
			'meta_value'     => $history_msg,
			'created_at_gmt' => current_time( 'mysql', true ),
			'created_by'     => $student_id,
			'updated_at_gmt' => current_time( 'mysql', true ),
			'updated_by'     => $student_id,
		),
		array( '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
	);

	// Retrieve items purchased in this order
	$items_table = $wpdb->prefix . 'tutor_order_items';
	$order_items = $wpdb->get_results( $wpdb->prepare( "SELECT item_id FROM {$items_table} WHERE order_id = %d", $order_id ) );

	foreach ( $order_items as $item ) {
		$course_id = (int) $item->item_id;
		if ( ! $course_id ) {
			continue;
		}

		$is_enrolled = false;
		if ( function_exists( 'tutor_utils' ) ) {
			$is_enrolled = tutor_utils()->is_enrolled( $course_id, $student_id );
		}

		if ( ! $is_enrolled ) {
			$enroll_id = 0;
			if ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'do_enroll' ) ) {
				$enroll_id = tutor_utils()->do_enroll( $course_id, $student_id );
			}

			// Direct fallback if do_enroll did not complete
			if ( ! $enroll_id ) {
				$enroll_id = wp_insert_post( array(
					'post_type'   => 'tutor_enrolled',
					'post_status' => 'completed',
					'post_author' => $student_id,
					'post_parent' => $course_id,
					'post_title'  => sprintf( 'Enrollment for course #%d by user #%d', $course_id, $student_id ),
					'post_date'   => current_time( 'mysql' ),
				) );
			}

			if ( $enroll_id ) {
				update_post_meta( $enroll_id, '_tutor_course_id', $course_id );
				update_post_meta( $enroll_id, '_tutor_user_id', $student_id );
				update_post_meta( $enroll_id, '_tutor_enrolled_by_order_id', $order_id );
				update_post_meta( $enroll_id, 'tutor_order_id', $order_id );
				update_post_meta( $enroll_id, 'order_id', $order_id );

				// Trigger Tutor LMS core enrolled hooks
				do_action( 'tutor_after_enrolled', $course_id, $student_id, $enroll_id );

				// Update runtime cache directly so same-request checks are immediately truthy
				if ( class_exists( '\Tutor\Cache\TutorCache' ) ) {
					$cached_enrollment = (object) array(
						'ID'          => (string) $enroll_id,
						'post_author' => (string) $student_id,
						'post_date'   => current_time( 'mysql' ),
						'order_id'    => (int) $order_id,
						'product_id'  => 0,
					);
					\Tutor\Cache\TutorCache::set( "tutor_is_enrolled_{$course_id}_{$student_id}_1", $cached_enrollment );
					\Tutor\Cache\TutorCache::set( "tutor_is_enrolled_{$course_id}_{$student_id}_", $cached_enrollment );
				}
				wp_cache_delete( "tutor_is_enrolled_{$course_id}_{$student_id}_1", 'tutor' );
				wp_cache_delete( "tutor_is_enrolled_{$course_id}_{$student_id}_", 'tutor' );
				clean_post_cache( $course_id );
			}
		}
	}

	// Trigger core Tutor LMS status change hooks so earnings and reports update
	if ( 'paid' !== $prev_payment_status ) {
		do_action( 'tutor_order_payment_status_changed', $order_id, $prev_payment_status, 'paid' );
	}

	// Clear student's cart
	if ( class_exists( '\Tutor\Models\CartModel' ) ) {
		( new \Tutor\Models\CartModel() )->clear_user_cart( $student_id );
	}

	wp_cache_delete( "tutor_enrolled_courses_{$student_id}", 'tutor' );
	wp_cache_flush();

	return true;
}

/**
 * 5. Handle Razorpay Frontend Return & Webhook Verification
 *
 * Runs on template_redirect (priority 1) before Tutor LMS template_include.
 */
add_action( 'template_redirect', 'vc_online_handle_razorpay_return', 1 );
function vc_online_handle_razorpay_return() {
	// A. Check if this request contains Razorpay payment callback parameters
	$payment_id   = sanitize_text_field( $_POST['razorpay_payment_id'] ?? $_GET['razorpay_payment_id'] ?? '' );
	$rzp_order_id = sanitize_text_field( $_POST['razorpay_order_id'] ?? $_GET['razorpay_order_id'] ?? '' );
	$signature    = sanitize_text_field( $_POST['razorpay_signature'] ?? $_GET['razorpay_signature'] ?? '' );

	$is_order_placement = ( isset( $_GET['tutor_order_placement'] ) && 'success' === $_GET['tutor_order_placement'] );
	$tutor_order_id     = isset( $_REQUEST['order_id'] ) ? (int) $_REQUEST['order_id'] : 0;

	// If no payment params and not an order placement success page, nothing to do
	if ( empty( $payment_id ) && ! $is_order_placement ) {
		return;
	}

	global $wpdb;
	$orders_table    = $wpdb->prefix . 'tutor_orders';
	$ordermeta_table = $wpdb->prefix . 'tutor_ordermeta';

	// B. If tutor_order_id is missing, try to resolve it from Razorpay order ID or user session
	if ( ! $tutor_order_id && ! empty( $rzp_order_id ) ) {
		$found_order_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT order_id FROM {$ordermeta_table} WHERE meta_key = 'razorpay_order_id' AND meta_value = %s ORDER BY id DESC LIMIT 1",
			$rzp_order_id
		) );
		if ( $found_order_id ) {
			$tutor_order_id = (int) $found_order_id;
		}
	}

	if ( ! $tutor_order_id && is_user_logged_in() ) {
		$current_user_id = get_current_user_id();
		$latest_order_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$orders_table} WHERE user_id = %d AND payment_method = 'razorpay' ORDER BY id DESC LIMIT 1",
			$current_user_id
		) );
		if ( $latest_order_id ) {
			$tutor_order_id = (int) $latest_order_id;
		}
	}

	if ( ! $tutor_order_id ) {
		return;
	}

	// C. Check current order status
	$current_order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE id = %d", $tutor_order_id ) );
	if ( ! $current_order ) {
		return;
	}

	// If already paid and completed, ensure enrollment and return
	if ( 'paid' === $current_order->payment_status && 'completed' === $current_order->order_status ) {
		vc_online_ensure_order_enrollment( $tutor_order_id );
		return;
	}

	// D. Case 1: We have razorpay_payment_id
	if ( ! empty( $payment_id ) ) {
		$creds      = vc_online_get_razorpay_credentials();
		$is_verified = false;

		// Verify signature if signature & key_secret are available
		if ( ! empty( $signature ) && ! empty( $rzp_order_id ) && ! empty( $creds['key_secret'] ) ) {
			$expected_signature = hash_hmac( 'sha256', $rzp_order_id . '|' . $payment_id, $creds['key_secret'] );
			if ( hash_equals( $expected_signature, $signature ) ) {
				$is_verified = true;
			}
		}

		// Fetch payment details from Razorpay API to confirm capture and amount
		$payment_details = vc_online_fetch_razorpay_payment( $payment_id );
		if ( is_array( $payment_details ) ) {
			$status = $payment_details['status'] ?? '';
			if ( 'captured' === $status || 'authorized' === $status ) {
				$is_verified = true;
			}
		} else {
			$payment_details = array(
				'id'       => $payment_id,
				'order_id' => $rzp_order_id,
				'amount'   => (int) ( (float) $current_order->total_price * 100 ),
				'status'   => 'captured',
			);
		}

		if ( $is_verified ) {
			vc_online_complete_tutor_order( $tutor_order_id, $payment_id, $payment_details );
			return;
		}
	}

	// E. Case 2: Arrived at ?tutor_order_placement=success without POST data
	if ( $is_order_placement && 'unpaid' === $current_order->payment_status ) {
		// Look up razorpay_order_id from ordermeta
		$stored_rzp_order_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$ordermeta_table} WHERE order_id = %d AND meta_key = 'razorpay_order_id' ORDER BY id DESC LIMIT 1",
			$tutor_order_id
		) );

		if ( $stored_rzp_order_id ) {
			$payments = vc_online_fetch_razorpay_order_payments( $stored_rzp_order_id );
			if ( ! empty( $payments ) ) {
				foreach ( $payments as $p ) {
					if ( isset( $p['status'] ) && ( 'captured' === $p['status'] || 'authorized' === $p['status'] ) ) {
						vc_online_complete_tutor_order( $tutor_order_id, $p['id'], $p );
						return;
					}
				}
			}
		}
	}
}

/**
 * 6. Helper: Ensure enrollment exists for an order.
 *
 * @param int $order_id Tutor LMS Order ID.
 */
function vc_online_ensure_order_enrollment( $order_id ) {
	global $wpdb;
	$orders_table = $wpdb->prefix . 'tutor_orders';
	$order        = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM {$orders_table} WHERE id = %d", $order_id ) );
	if ( ! $order ) {
		return;
	}

	$student_id  = (int) $order->user_id;
	$items_table = $wpdb->prefix . 'tutor_order_items';
	$order_items = $wpdb->get_results( $wpdb->prepare( "SELECT item_id FROM {$items_table} WHERE order_id = %d", $order_id ) );

	foreach ( $order_items as $item ) {
		$course_id = (int) $item->item_id;
		if ( ! $course_id ) {
			continue;
		}

		$is_enrolled = function_exists( 'tutor_utils' ) ? tutor_utils()->is_enrolled( $course_id, $student_id ) : false;
		if ( ! $is_enrolled ) {
			$enroll_id = tutor_utils()->do_enroll( $course_id, $student_id );
			if ( ! $enroll_id ) {
				$enroll_id = wp_insert_post( array(
					'post_type'   => 'tutor_enrolled',
					'post_status' => 'completed',
					'post_author' => $student_id,
					'post_parent' => $course_id,
					'post_title'  => sprintf( 'Enrollment for course #%d by user #%d', $course_id, $student_id ),
					'post_date'   => current_time( 'mysql' ),
				) );
			}

			if ( $enroll_id ) {
				update_post_meta( $enroll_id, '_tutor_course_id', $course_id );
				update_post_meta( $enroll_id, '_tutor_user_id', $student_id );
				update_post_meta( $enroll_id, '_tutor_enrolled_by_order_id', $order_id );
				update_post_meta( $enroll_id, 'tutor_order_id', $order_id );
				do_action( 'tutor_after_enrolled', $course_id, $student_id, $enroll_id );
			}
		}
	}
}

/**
 * 7. Safety Net: Auto-sync on Single Course Page
 *
 * If a logged-in user visits a course page and has a paid/completed order
 * but is not enrolled, or has an unpaid order recently captured on Razorpay,
 * auto-complete and enroll them so "Start Learning" is immediately shown.
 */
add_action( 'template_redirect', 'vc_online_auto_sync_course_view', 5 );
function vc_online_auto_sync_course_view() {
	if ( ! is_singular( 'courses' ) || ! is_user_logged_in() ) {
		return;
	}

	$course_id = get_the_ID();
	$user_id   = get_current_user_id();

	if ( ! $course_id || ! $user_id ) {
		return;
	}

	// If user is already enrolled, no action needed
	if ( function_exists( 'tutor_utils' ) && tutor_utils()->is_enrolled( $course_id, $user_id ) ) {
		return;
	}

	global $wpdb;
	$orders_table = $wpdb->prefix . 'tutor_orders';
	$items_table  = $wpdb->prefix . 'tutor_order_items';

	// Find the user's latest order for this specific course
	$order = $wpdb->get_row( $wpdb->prepare(
		"SELECT o.* FROM {$orders_table} o
		 INNER JOIN {$items_table} i ON o.id = i.order_id
		 WHERE o.user_id = %d AND i.item_id = %d
		 ORDER BY o.id DESC LIMIT 1",
		$user_id,
		$course_id
	) );

	if ( ! $order ) {
		return;
	}

	// If order is already paid, fix missing enrollment
	if ( 'paid' === $order->payment_status && 'completed' === $order->order_status ) {
		vc_online_ensure_order_enrollment( (int) $order->id );
		wp_cache_flush();
		return;
	}

	// If order is unpaid with Razorpay, check if a captured payment exists in Razorpay
	if ( 'unpaid' === $order->payment_status && 'razorpay' === $order->payment_method ) {
		$ordermeta_table     = $wpdb->prefix . 'tutor_ordermeta';
		$stored_rzp_order_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$ordermeta_table} WHERE order_id = %d AND meta_key = 'razorpay_order_id' ORDER BY id DESC LIMIT 1",
			$order->id
		) );

		if ( $stored_rzp_order_id ) {
			$payments = vc_online_fetch_razorpay_order_payments( $stored_rzp_order_id );
			if ( ! empty( $payments ) ) {
				foreach ( $payments as $p ) {
					if ( isset( $p['status'] ) && ( 'captured' === $p['status'] || 'authorized' === $p['status'] ) ) {
						vc_online_complete_tutor_order( (int) $order->id, $p['id'], $p );
						wp_cache_flush();
						return;
					}
				}
			}
		} else {
			// Fallback: Check recent payments from Razorpay API by student phone/email
			$creds = vc_online_get_razorpay_credentials();
			if ( ! empty( $creds['key_id'] ) && ! empty( $creds['key_secret'] ) ) {
				$url      = 'https://api.razorpay.com/v1/payments?count=15';
				$response = wp_remote_get( $url, array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $creds['key_id'] . ':' . $creds['key_secret'] ),
					),
					'timeout' => 15,
				) );
				if ( ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( ! empty( $body['items'] ) && is_array( $body['items'] ) ) {
						$user_info  = get_userdata( $user_id );
						$user_email = $user_info ? strtolower( $user_info->user_email ) : '';
						$user_phone = preg_replace( '/[^0-9]/', '', (string) get_user_meta( $user_id, 'phone_number', true ) );
						if ( ! $user_phone ) {
							$user_phone = preg_replace( '/[^0-9]/', '', (string) get_user_meta( $user_id, 'mobile_number', true ) );
						}

						foreach ( $body['items'] as $item ) {
							if ( 'captured' !== ( $item['status'] ?? '' ) ) {
								continue;
							}
							$item_email = strtolower( (string) ( $item['email'] ?? '' ) );
							$item_phone = preg_replace( '/[^0-9]/', '', (string) ( $item['contact'] ?? '' ) );

							$matches = false;
							if ( $user_phone && $item_phone && ( strpos( $item_phone, $user_phone ) !== false || strpos( $user_phone, $item_phone ) !== false ) ) {
								$matches = true;
							} elseif ( $user_email && $item_email && $user_email === $item_email ) {
								$matches = true;
							}

							if ( $matches ) {
								vc_online_complete_tutor_order( (int) $order->id, $item['id'], $item );
								wp_cache_flush();
								return;
							}
						}
					}
				}
			}
		}
	}
}

/**
 * 8. Admin Manual Sync Action
 *
 * Allows administrators to click "Sync with Razorpay" for any stuck or incomplete order.
 */
add_action( 'admin_action_vc_online_sync_order', 'vc_online_admin_sync_order' );
function vc_online_admin_sync_order() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
	check_admin_referer( 'vc_online_sync_order_' . $order_id );

	if ( ! $order_id ) {
		wp_redirect( admin_url( 'admin.php?page=tutor_orders' ) );
		exit;
	}

	global $wpdb;
	$orders_table    = $wpdb->prefix . 'tutor_orders';
	$ordermeta_table = $wpdb->prefix . 'tutor_ordermeta';

	$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE id = %d", $order_id ) );
	if ( ! $order ) {
		wp_die( 'Order not found.' );
	}

	$stored_rzp_order_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$ordermeta_table} WHERE order_id = %d AND meta_key = 'razorpay_order_id' ORDER BY id DESC LIMIT 1",
		$order_id
	) );

	$matched_payment = null;

	if ( $stored_rzp_order_id ) {
		$payments = vc_online_fetch_razorpay_order_payments( $stored_rzp_order_id );
		foreach ( $payments as $p ) {
			if ( isset( $p['status'] ) && ( 'captured' === $p['status'] || 'authorized' === $p['status'] ) ) {
				$matched_payment = $p;
				break;
			}
		}
	}

	if ( ! $matched_payment ) {
		// Search recent payments by student contact info
		$user_id    = (int) $order->user_id;
		$user_info  = get_userdata( $user_id );
		$user_email = $user_info ? strtolower( $user_info->user_email ) : '';
		$user_phone = preg_replace( '/[^0-9]/', '', (string) get_user_meta( $user_id, 'phone_number', true ) );

		$creds = vc_online_get_razorpay_credentials();
		if ( ! empty( $creds['key_id'] ) && ! empty( $creds['key_secret'] ) ) {
			$url      = 'https://api.razorpay.com/v1/payments?count=25';
			$response = wp_remote_get( $url, array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $creds['key_id'] . ':' . $creds['key_secret'] ),
				),
				'timeout' => 15,
			) );

			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $body['items'] ) && is_array( $body['items'] ) ) {
					foreach ( $body['items'] as $item ) {
						if ( 'captured' !== ( $item['status'] ?? '' ) ) {
							continue;
						}
						$item_email = strtolower( (string) ( $item['email'] ?? '' ) );
						$item_phone = preg_replace( '/[^0-9]/', '', (string) ( $item['contact'] ?? '' ) );

						if ( ( $user_phone && $item_phone && ( strpos( $item_phone, $user_phone ) !== false || strpos( $user_phone, $item_phone ) !== false ) )
							|| ( $user_email && $item_email && $user_email === $item_email ) ) {
							$matched_payment = $item;
							break;
						}
					}
				}
			}
		}
	}

	if ( $matched_payment ) {
		vc_online_complete_tutor_order( $order_id, $matched_payment['id'], $matched_payment );
		wp_cache_flush();
		wp_redirect( add_query_arg( array(
			'page'               => 'tutor_orders',
			'vc_sync_status'     => 'success',
			'vc_synced_order_id' => $order_id,
			'vc_payment_id'      => $matched_payment['id'],
		), admin_url( 'admin.php' ) ) );
		exit;
	} else {
		wp_redirect( add_query_arg( array(
			'page'               => 'tutor_orders',
			'vc_sync_status'     => 'not_found',
			'vc_synced_order_id' => $order_id,
		), admin_url( 'admin.php' ) ) );
		exit;
	}
}

/**
 * 9. Display Admin Notice after manual sync
 */
add_action( 'admin_notices', 'vc_online_display_sync_admin_notices' );
function vc_online_display_sync_admin_notices() {
	if ( ! isset( $_GET['vc_sync_status'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$status   = sanitize_text_field( $_GET['vc_sync_status'] );
	$order_id = isset( $_GET['vc_synced_order_id'] ) ? (int) $_GET['vc_synced_order_id'] : 0;
	$pay_id   = isset( $_GET['vc_payment_id'] ) ? sanitize_text_field( $_GET['vc_payment_id'] ) : '';

	if ( 'success' === $status && $order_id ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Razorpay Sync Successful!', 'vconline' ); ?></strong>
				<?php printf( esc_html__( 'Order #%1$d has been marked as Paid & Completed. Payment ID: %2$s. The student has been enrolled in the course.', 'vconline' ), $order_id, esc_html( $pay_id ) ); ?>
			</p>
		</div>
		<?php
	} elseif ( 'not_found' === $status && $order_id ) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Razorpay Sync Notice:', 'vconline' ); ?></strong>
				<?php printf( esc_html__( 'No captured payment was found on Razorpay for Order #%d.', 'vconline' ), $order_id ); ?>
			</p>
		</div>
		<?php
	}
}

