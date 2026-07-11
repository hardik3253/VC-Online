<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// AJAX request handler for feedback submission
add_action('wp_ajax_zlfms_handle_deactivation_form', 'zlfms_handle_deactivation_form');
function zlfms_handle_deactivation_form()
{
    // Verify the nonce to ensure the request is coming from a valid source
    if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'zlfms_deactivation_nonce')) {
        wp_send_json_error('Nonce verification failed.'); // Send a JSON error response if nonce verification fails
        wp_die(); // Terminate script execution
    }

    // Check if 'reason' is set in the POST request
    if (isset($_POST['reason'])) {
        // Get the current user information
        $current_user = wp_get_current_user();

        // Send data to Google Apps Script via a POST request
        $webhook_url = esc_url_raw('https://script.google.com/macros/s/AKfycbzAiS3hs_ByyPzuNf_SvzG9WTfCpIGyFAOaLxUC984aO2rxswrLqDh3mQw-CXHdTh--vA/exec');

        // Unsplash and sanitize the reason for deactivation immediately after access
        $unslashed_reason = sanitize_textarea_field(wp_unslash($_POST['reason']));

        // Sanitize and prepare data
        $data = array(
            'sheet_id'   => 915042170,
            'user_name'  => sanitize_text_field($current_user->display_name), // Sanitize the user's display name
            'user_email' => sanitize_email($current_user->user_email), // Sanitize the user's email
            'user_site'  => esc_url_raw(get_home_url()), // Sanitize and escape the URL of the user's site
            'reason'     => $unslashed_reason, // Sanitize the reason for deactivation
        );

        // Send the sanitized data to the webhook URL
        $response = wp_remote_post($webhook_url, array(
            'method'    => 'POST',
            'body'      => wp_json_encode($data), // Convert sanitized data to JSON
            'headers'   => array(
                'Content-Type' => 'application/json', // Set the content type to JSON
            ),
        ));

        // Log an error if the request to Google Sheets fails
        if (is_wp_error($response)) {
            error_log('Error sending data to Google Sheets: ' . $response->get_error_message());
        }
    }

    // Redirect to the URL provided in the POST request if it exists
    if (isset($_POST['redirect'])) {
        wp_redirect(esc_url_raw(wp_unslash($_POST['redirect']))); // Redirect to the specified URL
        exit;
    }

    wp_die();
}
