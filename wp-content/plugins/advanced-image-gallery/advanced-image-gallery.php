<?php

/**
 * Plugin Name: Advanced Image Gallery for Elementor - Grid, Carousel & Slideshow
 * Description: Advanced Image Gallery is a versatile plugin for creating stunning media grids, carousels, gallery, and slideshows with an ease way.
 * Version: 1.3
 * Plugin URI: 
 * Author: Zluck Solutions
 * Author URI: https://zluck.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: advanced-image-gallery
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Keep plugin version constant in sync with the header
if (! function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$zlfms_aig_plugin_data = get_plugin_data(__FILE__);
define('ZLFMS_AIG_VERSION', $zlfms_aig_plugin_data['Version']);

$deactivation_feedback_file = plugin_dir_path(__FILE__) . 'include/deactivation-feedback.php';

// Check if the file exists before including it
if (file_exists($deactivation_feedback_file)) {
    require_once $deactivation_feedback_file;
} else {
    // Optional: Log an error or handle the missing file scenario gracefully
    error_log('Required file "deactivation-feedback.php" is missing.');
}

// Check if Elementor is active and display an admin notice if it's not
function zlfms_check_elementor_active()
{
    if (!did_action('elementor/loaded')) {
        // Admin notice to inform the user Elementor is required
        add_action('admin_notices', 'zlfms_elementor_inactive_notice');
    }
}
add_action('plugins_loaded', 'zlfms_check_elementor_active');


/**
 * Register all gallery-related styles so they can be enqueued on demand.
 */
function zlfms_register_advanced_gallery_styles()
{
    $plugin_url = plugin_dir_url(__FILE__);
    $version    = ZLFMS_AIG_VERSION;

    wp_register_style(
        'elementor_advanced_gallery_style',
        $plugin_url . 'assets/css/advanced-gallery-style.css',
        [],
        $version
    );

    wp_register_style(
        'elementor_advanced_gallery_bundle_css',
        $plugin_url . 'assets/swiper/swiper-bundle.min.css',
        [],
        $version
    );

    wp_register_style(
        'elementor_advanced_gallery_masonry_style',
        $plugin_url . 'assets/css/advanced-gallery-masonry.css',
        ['elementor_advanced_gallery_style'],
        $version
    );

    wp_register_style(
        'elementor_advanced_gallery_justified_style',
        $plugin_url . 'assets/css/advanced-gallery-justified.css',
        ['elementor_advanced_gallery_style'],
        $version
    );

    wp_register_style(
        'elementor_advanced_gallery_metro_style',
        $plugin_url . 'assets/css/advanced-gallery-metro.css',
        ['elementor_advanced_gallery_style'],
        $version
    );

    if (defined('ELEMENTOR_VERSION')) {
        $fontawesome_version = ELEMENTOR_VERSION;

        if (!wp_style_is('elementor-fontawesome', 'registered')) {
            wp_register_style(
                'elementor-fontawesome',
                plugins_url('/elementor/assets/lib/font-awesome/css/fontawesome.min.css'),
                [],
                $fontawesome_version
            );
        }

        if (!wp_style_is('elementor-fontawesome-regular', 'registered')) {
            wp_register_style(
                'elementor-fontawesome-regular',
                plugins_url('/elementor/assets/lib/font-awesome/css/regular.min.css'),
                ['elementor-fontawesome'],
                $fontawesome_version
            );
        }

        if (!wp_style_is('elementor-fontawesome-brands', 'registered')) {
            wp_register_style(
                'elementor-fontawesome-brands',
                plugins_url('/elementor/assets/lib/font-awesome/css/brands.min.css'),
                ['elementor-fontawesome'],
                $fontawesome_version
            );
        }

        if (!wp_style_is('elementor-fontawesome-solid', 'registered')) {
            wp_register_style(
                'elementor-fontawesome-solid',
                plugins_url('/elementor/assets/lib/font-awesome/css/solid.min.css'),
                ['elementor-fontawesome'],
                $fontawesome_version
            );
        }
    }
}
add_action('elementor/frontend/after_register_styles', 'zlfms_register_advanced_gallery_styles');

/**
 * Register gallery scripts ahead of time so Elementor can enqueue them per widget instance.
 */
function zlfms_register_advanced_gallery_scripts()
{
    $plugin_url = plugin_dir_url(__FILE__);
    $version    = ZLFMS_AIG_VERSION;

    wp_register_script(
        'elementor_swiper_gallery_carousel_element_js',
        $plugin_url . 'assets/swiper/swiper-bundle.min.js',
        ['jquery'],
        $version,
        true
    );

    wp_register_script(
        'elementor_advanced_gallery_carousel_js',
        $plugin_url . 'assets/js/advanced-gallery-carousel.js',
        ['jquery', 'elementor-frontend', 'elementor_swiper_gallery_carousel_element_js'],
        $version,
        true
    );

    wp_register_script(
        'elementor_advanced_gallery_slideshow_js',
        $plugin_url . 'assets/js/advanced-gallery-slideshow.js',
        ['jquery', 'elementor-frontend', 'elementor_swiper_gallery_carousel_element_js'],
        $version,
        true
    );

    wp_register_script(
        'elementor_advanced_gallery_masonry_js',
        $plugin_url . 'assets/js/advanced-gallery-masonry.js',
        ['jquery', 'elementor-frontend'],
        $version,
        true
    );

    wp_register_script(
        'elementor_advanced_gallery_justified_js',
        $plugin_url . 'assets/js/advanced-gallery-justified.js',
        ['jquery', 'elementor-frontend'],
        $version,
        true
    );

    wp_register_script(
        'elementor_advanced_gallery_metro_js',
        $plugin_url . 'assets/js/advanced-gallery-metro.js',
        ['jquery', 'elementor-frontend'],
        $version,
        true
    );

    wp_register_script(
        'elementor_advanced_gallery_lightbox_fix',
        $plugin_url . 'assets/js/lightbox-fix.js',
        ['jquery', 'elementor-frontend'],
        $version,
        true
    );
}
add_action('elementor/frontend/after_register_scripts', 'zlfms_register_advanced_gallery_scripts');

// Show admin notice if Elementor is not active
function zlfms_elementor_inactive_notice()
{
    if (current_user_can('activate_plugins')) {
        $activate_url = wp_nonce_url(
            admin_url('plugins.php?action=activate&plugin=elementor/elementor.php'),
            'activate-plugin_elementor/elementor.php'
        );
        echo wp_kses_post('
            <div class="notice notice-error is-dismissible">
                <p>' . esc_html__('Elementor Advanced Gallery requires Elementor plugin to be active. Please activate Elementor to continue.', 'advanced-image-gallery') . '</p>
                <p><a href="' . esc_url($activate_url) . '" class="button-primary">' . esc_html__('Activate Elementor', 'advanced-image-gallery') . '</a></p>
            </div>
        ');
    }
}

// Prevent plugin activation if Elementor is not active
function zlfms_activate_plugin()
{
    if (!did_action('elementor/loaded')) {
        deactivate_plugins(plugin_basename(__FILE__)); // Deactivate this plugin
        wp_die(
            esc_html__('Elementor Advanced Gallery requires Elementor to be installed and activated. The plugin has been deactivated.', 'advanced-image-gallery'),
            esc_html__('Plugin dependency check failed', 'advanced-image-gallery'),
            array('back_link' => true)
        );
    }
}

register_activation_hook(__FILE__, 'zlfms_activate_plugin');

/**
 * Store the plugin installation time for later use.
 */
function zlfms_aig_set_install_time()
{
    if (false === get_option('zlfms_aig_install_time')) {
        add_option('zlfms_aig_install_time', time());
    }
}
register_activation_hook(__FILE__, 'zlfms_aig_set_install_time');

// Register the widget only if Elementor is active
function zlfms_register_media_gallery($widgets_manager)
{
    if (!did_action('elementor/loaded')) {
        return; // Stop if Elementor is not active
    }
    require_once plugin_dir_path(__FILE__) . 'widgets/class-image-gallery-widget.php';
    $widgets_manager->register(new zlfms_advanced_image_gallery());
}
add_action('elementor/widgets/register', 'zlfms_register_media_gallery');



/**
 * Parse video URL and return video details for embedding.
 *
 * @param string $video_url The video URL to parse
 * @return array|false Array with 'video_id', 'embed_url', 'video_type' or false on failure
 */
function zlfms_parse_video_url($video_url)
{
    if (empty($video_url)) {
        return false;
    }

    $video_url = esc_url($video_url);
    $matches = [];

    // YouTube URL patterns
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video_url, $matches)) {
        return [
            'video_id' => $matches[1],
            'embed_url' => 'https://www.youtube.com/embed/' . esc_attr($matches[1]),
            'video_type' => 'youtube'
        ];
    }

    // Vimeo URL patterns
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?(?:vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/(?:\w+\/)?videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:$|\/|\?))/', $video_url, $matches)) {
        return [
            'video_id' => $matches[2],
            'embed_url' => 'https://player.vimeo.com/video/' . esc_attr($matches[2]),
            'video_type' => 'vimeo'
        ];
    }

    return false;
}

add_action('admin_enqueue_scripts', 'zlfms_register_advanced_gallery_admin_assets');
function zlfms_register_advanced_gallery_admin_assets($hook_suffix)
{
    // Register the style for the block editor.
    wp_register_style(
        'zlfms-counter-block-gallery',
        plugins_url('assets/css/admin_gallery.css', __FILE__), // URL to the CSS file
        [], // Dependencies: WordPress block editor styles
        filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin_gallery.css') // Version parameter: File modification time for cache-busting
    );

    // Load assets only on the Plugins page.
    if ($hook_suffix === 'plugins.php') {
        // Enqueue the registered style
        wp_enqueue_style('zlfms-counter-block-gallery');

        // Enqueue the script for the deactivation popup
        wp_enqueue_script(
            'zlfms-admin',
            plugin_dir_url(__FILE__) . 'assets/js/deactivation-feedback.js', // URL to the JavaScript file
            array('jquery'), // Dependencies: jQuery
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/deactivation-feedback.js'), // Version parameter: File modification time for cache-busting
            true // Load the script in the footer
        );

        // Localize the script with necessary data
        wp_localize_script('zlfms-admin', 'zl_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'), // URL for AJAX requests
            'nonce'    => wp_create_nonce('zlfms_deactivation_nonce') // Nonce for security
        ));
    }
}

/**
 * Handle review notice actions.
 */
function zlfms_aig_handle_review_actions()
{
    if (! isset($_GET['zlfms_aig_review_action']) || ! current_user_can('manage_options')) {
        return;
    }

    $action = sanitize_text_field(wp_unslash($_GET['zlfms_aig_review_action']));

    switch ($action) {
        case 'done':
            update_option('zlfms_aig_review_status', 'done');
            wp_safe_redirect(remove_query_arg('zlfms_aig_review_action'));
            exit;
        case 'review_now':
            $week_in_seconds = defined('WEEK_IN_SECONDS') ? WEEK_IN_SECONDS : 604800;
            update_option('zlfms_aig_review_dismissed_until', time() + $week_in_seconds);
            wp_redirect('https://wordpress.org/support/plugin/advanced-image-gallery/reviews/#new-post');
            exit;
        case 'later':
            $week_in_seconds = defined('WEEK_IN_SECONDS') ? WEEK_IN_SECONDS : 604800;
            update_option('zlfms_aig_review_dismissed_until', time() + $week_in_seconds);
            wp_safe_redirect(remove_query_arg('zlfms_aig_review_action'));
            exit;
    }
}
add_action('admin_init', 'zlfms_aig_handle_review_actions');

/**
 * Display a review notice in the admin area after seven days.
 */
function zlfms_aig_review_notice()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $status          = get_option('zlfms_aig_review_status');
    $install_time    = (int) get_option('zlfms_aig_install_time', 0);
    $dismissed_until = (int) get_option('zlfms_aig_review_dismissed_until', 0);

    if ('done' === $status || ! $install_time) {
        return;
    }

    // Check if 7 days have passed since installation (7 * 24 * 60 * 60 = 604800 seconds)
    $week_in_seconds = defined('WEEK_IN_SECONDS') ? WEEK_IN_SECONDS : 604800;

    if (time() - $install_time < $week_in_seconds || time() < $dismissed_until) {
        return;
    }

    $base_url   = remove_query_arg('zlfms_aig_review_action');
    $did_url    = esc_url(add_query_arg('zlfms_aig_review_action', 'done', $base_url));
    $now_url    = esc_url(add_query_arg('zlfms_aig_review_action', 'review_now', $base_url));
    $later_url  = esc_url(add_query_arg('zlfms_aig_review_action', 'later', $base_url));

    echo '<div class="notice notice-info"><p>' . esc_html__('Enjoying Advanced Image Gallery? Please consider leaving a review to support the project.', 'advanced-image-gallery') . '</p><p><a href="' . $did_url . '">' . esc_html__('I did', 'advanced-image-gallery') . '</a> | <a href="' . $now_url . '">' . esc_html__('Review now', 'advanced-image-gallery') . '</a> | <a href="' . $later_url . '">' . esc_html__('Later', 'advanced-image-gallery') . '</a></p></div>';
}
add_action('admin_notices', 'zlfms_aig_review_notice');
