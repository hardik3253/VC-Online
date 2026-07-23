<?php
/*
 * This is the child theme for TutorStarter theme, generated with Generate Child Theme plugin by catchthemes.
 *
 * (Please see https://developer.wordpress.org/themes/advanced-topics/child-themes/#how-to-create-a-child-theme)
 */
add_action( 'wp_enqueue_scripts', 'vconline_child_enqueue_styles' );
function vconline_child_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('parent-style')
    );
}
/*
 * Your code goes below
 */

add_action( 'user_register', 'vcacademy_save_mobile' );

function vcacademy_save_mobile( $user_id ) {
    $phone_value = '';

    if ( isset( $_POST['mobile_number'] ) ) {
        $phone_value = sanitize_text_field( wp_unslash( $_POST['mobile_number'] ) );
    } elseif ( isset( $_POST['phone_number'] ) ) {
        $phone_value = sanitize_text_field( wp_unslash( $_POST['phone_number'] ) );
    }

    if ( empty( $phone_value ) ) {
        return;
    }

    $existing_phone = get_user_meta( $user_id, 'phone_number', true );
    $existing_mobile = get_user_meta( $user_id, 'mobile_number', true );

    if ( ! empty( $existing_phone ) || ! empty( $existing_mobile ) ) {
        return;
    }

    update_user_meta( $user_id, 'phone_number', $phone_value );
}

// --- A. Add column to Users list ---
add_filter( 'manage_users_columns', 'vca_add_phone_column' );
function vca_add_phone_column( $columns ) {
    $columns['phone_number'] = 'Phone Number';
    return $columns;
}

// --- B. Populate the column ---
add_filter( 'manage_users_custom_column', 'vca_show_phone_column', 10, 3 );
function vca_show_phone_column( $value, $column_name, $user_id ) {
    if ( 'phone_number' === $column_name ) {
        $phone = get_user_meta( $user_id, 'phone_number', true );
        return $phone ? esc_html( $phone ) : '—';
    }
    return $value;
}

// --- C. Make it sortable ---
add_filter( 'manage_users_sortable_columns', 'vca_phone_sortable' );
function vca_phone_sortable( $columns ) {
    $columns['phone_number'] = 'phone_number';
    return $columns;
}

// --- D. Show field on User Edit Profile page ---
add_action( 'show_user_profile', 'vca_show_phone_field' );
add_action( 'edit_user_profile', 'vca_show_phone_field' );
function vca_show_phone_field( $user ) {
    ?>
    <h3>Contact Information</h3>
    <table class="form-table">
        <tr>
            <th><label for="phone_number">Phone Number</label></th>
            <td>
                <input type="text" name="phone_number" id="phone_number"
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'phone_number', true ) ); ?>"
                       class="regular-text" />
                <p class="description">Entered during registration.</p>
            </td>
        </tr>
    </table>
    <?php
}

// --- E. Save edits made on the profile page ---
add_action( 'personal_options_update', 'vca_save_phone_field' );
add_action( 'edit_user_profile_update', 'vca_save_phone_field' );
function vca_save_phone_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return;
    if ( isset( $_POST['phone_number'] ) ) {
        update_user_meta( $user_id, 'phone_number', sanitize_text_field( $_POST['phone_number'] ) );
    }
}

add_filter( 'tutor_social_share_icons', 'custom_tutor_add_social_icons' );

function custom_tutor_add_social_icons( $icons ) {

    // ✅ WhatsApp — JS already supports this natively (s_whatsapp class)
    $icons['whatsapp'] = array(
        'share_class' => 's_whatsapp',
        'icon_html'   => '',
        'text'        => '',
        'color'       => '#25D366',
    );

    // ✅ Telegram — JS doesn't have built-in, handled via onclick below
    $icons['telegram'] = array(
        'share_class' => 'custom_telegram',
        'icon_html'   => '',
        'text'        => '',
        'color'       => '#0088CC',
    );

    return $icons;
}

add_action( 'wp_footer', 'custom_tutor_social_share_scripts' );

function custom_tutor_social_share_scripts() {
    if ( ! is_singular( 'courses' ) ) return;
    ?>
    <script>
    (function () {
 
        // WhatsApp SVG (white fill for coloured button background)
        var WA_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.122 1.532 5.855L.057 23.012a.75.75 0 0 0 .931.931l5.157-1.475A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.907 0-3.686-.536-5.197-1.467l-.372-.22-3.862 1.104 1.104-3.862-.22-.372A9.953 9.953 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>';
 
        // Telegram SVG (white fill for coloured button background)
        var TG_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="#ffffff"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>';
 
        function injectIcons() {
            // Inject WhatsApp icon
            document.querySelectorAll('.s_whatsapp').forEach(function (btn) {
                if ( btn.dataset.iconInjected ) return;
                btn.dataset.iconInjected = '1';
                // Find the first child span (the text span added by Tutor) and insert SVG before it
                var textSpan = btn.querySelector('span');
                var iconNode = document.createElement('span');
                iconNode.innerHTML = WA_SVG;
                iconNode.style.display        = 'inline-flex';
                iconNode.style.verticalAlign  = 'middle';
                iconNode.style.marginRight    = '4px';
                if ( textSpan ) {
                    btn.insertBefore( iconNode, textSpan );
                } else {
                    btn.prepend( iconNode );
                }
            });
 
            // Inject Telegram icon + click handler
            document.querySelectorAll('.custom_telegram').forEach(function (btn) {
                if ( btn.dataset.iconInjected ) return;
                btn.dataset.iconInjected = '1';
 
                var textSpan = btn.querySelector('span');
                var iconNode = document.createElement('span');
                iconNode.innerHTML = TG_SVG;
                iconNode.style.display        = 'inline-flex';
                iconNode.style.verticalAlign  = 'middle';
                iconNode.style.marginRight    = '4px';
                if ( textSpan ) {
                    btn.insertBefore( iconNode, textSpan );
                } else {
                    btn.prepend( iconNode );
                }
 
                // Telegram share handler
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var url   = encodeURIComponent(window.location.href);
                    var title = encodeURIComponent(document.title);
                    var w = Math.round(window.screen.width  * 0.6);
                    var h = Math.round(window.screen.height * 0.6);
                    var l = Math.round((window.screen.width  - w) / 2);
                    var t = Math.round((window.screen.height - h) / 2);
                    window.open(
                        'https://t.me/share/url?url=' + url + '&text=' + title,
                        '',
                        'width=' + w + ',height=' + h + ',top=' + t + ',left=' + l
                    );
                });
            });
        }
 
        // Run on page load (in case modal is already in DOM)
        document.addEventListener('DOMContentLoaded', injectIcons);
 
        // Run when user clicks the Share button (modal opens dynamically)
        document.addEventListener('click', function (e) {
            var shareBtn = e.target && e.target.closest ? e.target.closest('[data-tutor-modal-target="tutor-course-share-opener"]') : null;
            if ( shareBtn ) {
                // Small delay to let Tutor finish rendering the modal
                setTimeout(injectIcons, 200);
                setTimeout(injectIcons, 500); // safety retry
            }
        });
 
    })();
    </script>
    <?php
}

add_action('wp_enqueue_scripts', 'custom_tutor_avatar_script');

function custom_tutor_avatar_script() {

    wp_add_inline_script('jquery-core', "

        function changeTutorAvatarText() {

            jQuery('.tutor-avatar-text').each(function(){

                jQuery(this).text('VC');

            });

        }

        // Initial Load
        jQuery(document).ready(function(){
            changeTutorAvatarText();
        });

        // AJAX / Dynamic Load
        jQuery(document).ajaxComplete(function(){
            changeTutorAvatarText();
        });

        // Mutation Observer
        const observer = new MutationObserver(function() {
            changeTutorAvatarText();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

    ");
}

function import_edmingle_to_tutor() {

    $file_path = get_stylesheet_directory() . '/tutor_import_2.csv';

    if (!file_exists($file_path)) {
        die('CSV not found: ' . $file_path);
    }

    $file = fopen($file_path, 'r');

    if (!$file) {
        die('Unable to open CSV file.');
    }

    // Skip header
    $header = fgetcsv($file);

    while (($row = fgetcsv($file, 1000, ",")) !== FALSE) {

        $email       = trim($row[0]);
        $first_name  = trim($row[1]);
        $last_name   = trim($row[2]);
        $course_slug = trim($row[3]);

        if (empty($email) || empty($course_slug)) continue;

        // ✅ Create / Get user
        $user = get_user_by('email', $email);

        if (!$user) {
            $user_id = wp_create_user($email, wp_generate_password(), $email);

            if (is_wp_error($user_id)) continue;

            wp_update_user([
                'ID'         => $user_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'role'       => 'subscriber'
            ]);
        } else {
            $user_id = $user->ID;
        }

        // 🔥 IMPORTANT FIX (use 'yes' not time)
        update_user_meta($user_id, '_is_tutor_student', 'yes');

        // ✅ Get course
        $course = get_page_by_path($course_slug, OBJECT, 'courses');

        if (!$course) {
            error_log('Course not found: ' . $course_slug);
            continue;
        }

        $course_id = $course->ID;

        // ✅ Prevent duplicate enroll
        if (!tutor_utils()->is_enrolled($course_id, $user_id)) {

            // Primary enroll
            $enroll_id = tutor_utils()->do_enroll($course_id, $user_id);

            // 🔥 Fallback (important for some versions)
            if (!$enroll_id) {
                $enroll_id = wp_insert_post([
                    'post_type'   => 'tutor_enrolled',
                    'post_status' => 'completed',
                    'post_author' => $user_id,
                    'post_parent' => $course_id
                ]);

                update_post_meta($enroll_id, '_tutor_course_id', $course_id);
                update_post_meta($enroll_id, '_tutor_user_id', $user_id);
            }
        }

        // ✅ Mark course as started
        $lessons = tutor_utils()->get_course_contents_by_id($course_id);

        if (!empty($lessons)) {
            foreach ($lessons as $lesson) {
                if (isset($lesson->post_type) && $lesson->post_type === 'lesson') {
                    tutor_utils()->mark_lesson_complete($lesson->ID, $user_id);
                    break;
                }
            }
        }

        // 🔥 FINAL TRIGGER (VERY IMPORTANT)
        // do_action('tutor_after_enrolled', $course_id, $user_id);

    }

    fclose($file);

    // 🔥 Clear cache for Tutor LMS
    wp_cache_flush();
}
// add_action('init', 'import_edmingle_to_tutor');


// function custom_tutor_login_redirect($redirect_to, $request, $user) {
//     return site_url('/online/courses/');
// }
// add_filter('login_redirect', 'custom_tutor_login_redirect', 10, 3);
