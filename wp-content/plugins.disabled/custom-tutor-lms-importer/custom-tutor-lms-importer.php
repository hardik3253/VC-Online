<?php
/*
Plugin Name: Custom Tutor LMS Importer
Description: Import Students and Course Enrollments from CSV into Tutor LMS.
Version: 1.0
Author: Hardik
*/

if (!defined('ABSPATH')) {
    exit;
}

class VC_Custom_Tutor_Importer {

    public function __construct() {

        add_action('admin_menu', array($this, 'menu'));
        add_action('wp_ajax_vc_edmingle_test_connection', array($this, 'ajax_edmingle_test_connection'));
    }

    public function menu() {

        add_menu_page(
            'Tutor Importer',
            'Tutor Importer',
            'manage_options',
            'vc-tutor-importer',
            array($this, 'page'),
            'dashicons-upload'
        );
    }

    public function page() {

        ?>

        <div class="wrap">
            <?php
            $test_notice = get_option('vc_edmingle_test_result');
            if (!empty($test_notice)) {
                echo '<div class="updated"><p>' . esc_html($test_notice) . '</p></div>';
                delete_option('vc_edmingle_test_result');
            }
            ?>
            <h1>Tutor LMS CSV Import</h1>

            <form method="post" enctype="multipart/form-data">

                <h2>1. Import Students CSV</h2>
                <input type="file" name="students_csv">
                <br><br>
                <input type="submit" name="import_students" class="button button-primary" value="Import Students">

                <hr>

                <h2>2. Import Enrollment CSV</h2>
                <input type="file" name="enrollment_csv">
                <br><br>
                <input type="submit" name="import_enrollments" class="button button-primary" value="Import Enrollments">

                <hr>

                <h2>3. EdMingle API Settings</h2>
                <p>Configure EdMingle domain and API key to import orders and progress.</p>
                <label>Domain (include trailing slash):</label><br>
                <input type="text" name="edmingle_domain" value="<?php echo esc_attr(get_option('vc_edmingle_domain', '')); ?>" style="width:400px;" placeholder="https://example.com/">
                <br>
                <label>API Key (if available):</label><br>
                <input type="text" name="edmingle_api_key" value="<?php echo esc_attr(get_option('vc_edmingle_api_key', '')); ?>" style="width:400px;">
                <br>
                <label>Org ID (ORGID header, if required):</label><br>
                <input type="text" name="edmingle_orgid" value="<?php echo esc_attr(get_option('vc_edmingle_orgid', '')); ?>" style="width:400px;">
                <br>
                <label>Server Key (server_key header, if required):</label><br>
                <input type="text" name="edmingle_server_key" value="<?php echo esc_attr(get_option('vc_edmingle_server_key', '')); ?>" style="width:400px;">
                <br>
                <label>JWT (optional for SSO generate):</label><br>
                <input type="text" name="edmingle_jwt" value="<?php echo esc_attr(get_option('vc_edmingle_jwt', '')); ?>" style="width:400px;">
                <br><br>
                <input type="submit" name="save_edmingle_settings" class="button" value="Save Settings">
                &nbsp;
                <input type="submit" id="vc-edmingle-test-btn" name="edmingle_test_connection" class="button button-secondary" value="Test Connection">
                <div id="vc-edmingle-test-result" style="display:inline-block;margin-left:10px;"></div>

                <hr>

                <h2>4. Import Orders & Progress from EdMingle</h2>
                <p>Fetch student enrollments/orders and class progress from EdMingle and create/update local enrollments.</p>
                <input type="submit" name="import_edmingle_api" class="button button-primary" value="Import Orders & Progress">
            </form>
        </div>

        <?php

        if (isset($_POST['import_students'])) {
            $this->import_students();
        }

        if (isset($_POST['import_enrollments'])) {
            $this->import_enrollments();
        }
        
        if (isset($_POST['save_edmingle_settings'])) {
            if (!empty($_POST['edmingle_domain'])) {
                update_option('vc_edmingle_domain', esc_url_raw(trim($_POST['edmingle_domain'])));
            }
            update_option('vc_edmingle_api_key', sanitize_text_field($_POST['edmingle_api_key']));
            update_option('vc_edmingle_orgid', sanitize_text_field($_POST['edmingle_orgid']));
            update_option('vc_edmingle_server_key', sanitize_text_field($_POST['edmingle_server_key']));
            update_option('vc_edmingle_api_key', sanitize_text_field($_POST['edmingle_api_key']));
            update_option('vc_edmingle_jwt', sanitize_text_field($_POST['edmingle_jwt']));

            echo '<div class="updated"><p>EdMingle settings saved.</p></div>';
        }

        if (isset($_POST['edmingle_test_connection'])) {
            $this->edmingle_test_connection();
        }

        if (isset($_POST['import_edmingle_api'])) {
            $this->import_edmingle_api();
        }
        
        // Inject AJAX script for Test Connection
        ?>
        <script type="text/javascript">
        (function(){
            var btn = document.getElementById('vc-edmingle-test-btn');
            if (!btn) return;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var resultDiv = document.getElementById('vc-edmingle-test-result');
                resultDiv.innerText = 'Testing...';

                var data = new FormData();
                data.append('action','vc_edmingle_test_connection');
                data.append('edmingle_domain', document.querySelector('input[name="edmingle_domain"]').value);
                data.append('edmingle_api_key', document.querySelector('input[name="edmingle_api_key"]').value);
                data.append('edmingle_orgid', document.querySelector('input[name="edmingle_orgid"]').value);
                data.append('edmingle_server_key', document.querySelector('input[name="edmingle_server_key"]').value);

                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
                .then(function(res){ return res.json(); })
                .then(function(json){
                    if (json.success) {
                        resultDiv.innerText = json.data.message;
                    } else {
                        resultDiv.innerText = (json.data && json.data.message) ? json.data.message : 'Error';
                    }
                }).catch(function(err){
                    resultDiv.innerText = 'Request failed';
                });
            });
        })();
        </script>
        <?php
    }

    /*
    ========================================
    IMPORT STUDENTS
    ========================================
    */

    public function import_students() {

        if (empty($_FILES['students_csv']['tmp_name'])) {
            return;
        }

        $file = fopen($_FILES['students_csv']['tmp_name'], 'r');

        $header = fgetcsv($file);

        $count = 0;

        while (($row = fgetcsv($file)) !== false) {

            $data = array_combine($header, $row);

            $full_name = sanitize_text_field($data['full_name']);
            $email     = sanitize_email($data['email']);
            $phone     = sanitize_text_field($data['phone']);
            $username  = sanitize_user($data['username']);

            if (empty($email)) {
                continue;
            }

            $existing_user = get_user_by('email', $email);

            if (!$existing_user) {

                if (empty($username)) {
                    $username = current(explode('@', $email));
                }

                $password = wp_generate_password(10, true, true);

                $user_id = wp_create_user($username, $password, $email);

                if (!is_wp_error($user_id)) {

                    wp_update_user(array(
                        'ID'           => $user_id,
                        'display_name' => $full_name,
                        'first_name'   => $full_name,
                        'role'         => 'subscriber'
                    ));

                    update_user_meta($user_id, 'phone', $phone);

                    update_user_meta($user_id, '_is_tutor_student', 'yes');

                    $count++;
                }
            }
        }

        fclose($file);

        echo '<div class="updated"><p>' . $count . ' Students Imported Successfully.</p></div>';
    }


    /*
    ========================================
    IMPORT COURSE ENROLLMENTS
    ========================================
    */

    public function import_enrollments() {

        if (empty($_FILES['enrollment_csv']['tmp_name'])) {
            return;
        }

        $file = fopen($_FILES['enrollment_csv']['tmp_name'], 'r');

        $header = fgetcsv($file);

        $count = 0;

        while (($row = fgetcsv($file)) !== false) {

            $data = array_combine($header, $row);

            $student_email = sanitize_email($data['student_email']);
            $course_name   = sanitize_text_field($data['course_name']);
            $expiry_date   = sanitize_text_field($data['expiry_date']);
            $status        = sanitize_text_field($data['enrollment_status']);
            $enroll_date   = sanitize_text_field($data['enrollment_date']);

            if (empty($student_email) || empty($course_name)) {
                continue;
            }

            $user = get_user_by('email', $student_email);

            if (!$user) {
                continue;
            }

            $course = get_page_by_title($course_name, OBJECT, 'courses');

            if (!$course) {
                continue;
            }

            $course_id = $course->ID;
            $user_id   = $user->ID;


            // CHECK EXISTING ENROLLMENT
            $already_enrolled = tutor_utils()->is_enrolled($course_id, $user_id);

            if (!$already_enrolled) {

                $enroll_post = array(
                    'post_type'   => 'tutor_enrolled',
                    'post_status' => 'completed',
                    'post_author' => $user_id,
                    'post_parent' => $course_id,
                    'post_title'  => 'Enrollment',
                    'post_date'   => current_time('mysql'),
                );

                $enroll_id = wp_insert_post($enroll_post);

                if ($enroll_id) {

                    // REQUIRED META
                    update_post_meta($enroll_id, '_tutor_user_id', $user_id);
                    update_post_meta($enroll_id, '_tutor_course_id', $course_id);

                    update_post_meta($enroll_id, 'course_id', $course_id);
                    update_post_meta($enroll_id, 'user_id', $user_id);

                    update_post_meta($enroll_id, 'enrol_status', 'completed');

                    update_post_meta($enroll_id, '_enrolled_by_order_id', 0);

                    update_post_meta($enroll_id, '_is_manual_enrollment', 'yes');

                    update_post_meta($enroll_id, 'enrollment_date', current_time('mysql'));

                    // IMPORTANT
                    wp_update_post(array(
                        'ID' => $enroll_id,
                        'post_status' => 'completed'
                    ));
                }
            }

            // SAVE EXPIRY DATE
            if (!empty($expiry_date) && $expiry_date != '-') {

                update_user_meta(
                    $user_id,
                    'tutor_course_expiry_' . $course_id,
                    $expiry_date
                );
            }

            // SAVE ENROLLMENT DATE
            if (!empty($enroll_date)) {

                update_user_meta(
                    $user_id,
                    'tutor_course_enroll_date_' . $course_id,
                    $enroll_date
                );
            }

            // SAVE STATUS
            update_user_meta(
                $user_id,
                'tutor_course_status_' . $course_id,
                $status
            );

            // ENABLE COURSE ACCESS
            update_user_meta(
                $user_id,
                'tutor_course_access_' . $course_id,
                'yes'
            );

            $count++;
        }

        fclose($file);

        echo '<div class="updated"><p>' . $count . ' Course Enrollments Imported Successfully.</p></div>';
    }

    /**
     * Simple GET helper for EdMingle API
     */
    private function edmingle_api_get($path, $params = array()) {
        $domain = get_option('vc_edmingle_domain', '');

        if (empty($domain)) {
            return false;
        }

        $domain = rtrim($domain, '/') . '/';
        $url = $domain . ltrim($path, '/');

        if (!empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }

        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        );

        $api_key = get_option('vc_edmingle_api_key', '');
        $orgid = get_option('vc_edmingle_orgid', '');
        $server_key = get_option('vc_edmingle_server_key', '');

        if (!empty($api_key)) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
            $args['headers']['APIKEY'] = $api_key;
        }

        if (!empty($orgid)) {
            $args['headers']['ORGID'] = $orgid;
        }

        if (!empty($server_key)) {
            $args['headers']['server_key'] = $server_key;
        }

        $resp = wp_remote_get($url, $args);

        if (is_wp_error($resp)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return false;
        }

        $body = wp_remote_retrieve_body($resp);

        $decoded = json_decode($body, true);

        return $decoded;
    }

    /**
     * Import student orders/enrollments and progress from EdMingle for all local students
     */
    public function import_edmingle_api() {

        $students = get_users(array(
            'meta_key' => '_is_tutor_student',
            'meta_value' => 'yes',
            'number' => -1
        ));

        if (empty($students)) {
            echo '<div class="notice notice-warning"><p>No students found to sync.</p></div>';
            return;
        }

        $created = 0;
        $progress_updated = 0;

        foreach ($students as $student) {

            $student_identifier = get_user_meta($student->ID, 'edmingle_student_id', true);

            if (empty($student_identifier)) {
                $student_identifier = $student->user_email;
            }

            // Attempt to fetch enrollments for this student
            $path = 'admin/student/enrollcourses/' . rawurlencode($student_identifier);
            $resp = $this->edmingle_api_get($path, array('include_archived_batches' => 0, 'include_lastview_info' => 0));

            if (empty($resp)) {
                continue;
            }

            // normalize to array of enrollment items
            $items = array();

            if (isset($resp['data']) && is_array($resp['data'])) {
                $items = $resp['data'];
            } elseif (isset($resp['courses']) && is_array($resp['courses'])) {
                $items = $resp['courses'];
            } elseif (is_array($resp)) {
                $items = $resp;
            }

            foreach ($items as $item) {

                if (!is_array($item)) {
                    continue;
                }

                // Try to find course locally by title or id
                $course_post = null;
                $course_name = '';
                if (!empty($item['course_name'])) {
                    $course_name = $item['course_name'];
                } elseif (!empty($item['course_title'])) {
                    $course_name = $item['course_title'];
                } elseif (!empty($item['title'])) {
                    $course_name = $item['title'];
                }

                if (!empty($course_name)) {
                    $course_post = get_page_by_title($course_name, OBJECT, 'courses');
                }

                // fallback: if class_id/course_id present, try to locate by meta 'external_id'
                if (!$course_post && !empty($item['course_id'])) {
                    $posts = get_posts(array(
                        'post_type' => 'courses',
                        'meta_key' => 'external_course_id',
                        'meta_value' => $item['course_id'],
                        'numberposts' => 1
                    ));
                    if (!empty($posts)) {
                        $course_post = $posts[0];
                    }
                }

                if (!$course_post) {
                    continue;
                }

                $course_id = $course_post->ID;
                $user_id = $student->ID;

                // create local enrollment if not exists
                $already_enrolled = tutor_utils()->is_enrolled($course_id, $user_id);

                if (!$already_enrolled) {
                    $enroll_post = array(
                        'post_type'   => 'tutor_enrolled',
                        'post_status' => 'completed',
                        'post_author' => $user_id,
                        'post_parent' => $course_id,
                        'post_title'  => 'Enrollment',
                        'post_date'   => current_time('mysql'),
                    );

                    $enroll_id = wp_insert_post($enroll_post);

                    if ($enroll_id) {
                        update_post_meta($enroll_id, '_tutor_user_id', $user_id);
                        update_post_meta($enroll_id, '_tutor_course_id', $course_id);
                        update_post_meta($enroll_id, 'course_id', $course_id);
                        update_post_meta($enroll_id, 'user_id', $user_id);
                        update_post_meta($enroll_id, 'enrol_status', 'completed');
                        update_post_meta($enroll_id, '_is_manual_enrollment', 'no');

                        // store order id/amount if present
                        $order_id = !empty($item['order_id']) ? $item['order_id'] : (!empty($item['id']) ? $item['id'] : 0);
                        $order_amount = 0;
                        if (!empty($item['amount'])) $order_amount = $item['amount'];
                        if (!empty($item['order_amount'])) $order_amount = $item['order_amount'];

                        update_post_meta($enroll_id, '_enrolled_by_order_id', $order_id);
                        if (!empty($order_amount)) update_post_meta($enroll_id, 'order_amount', $order_amount);

                        // enrollment date
                        if (!empty($item['purchase_date'])) {
                            update_post_meta($enroll_id, 'enrollment_date', $item['purchase_date']);
                        } elseif (!empty($item['enroll_date'])) {
                            update_post_meta($enroll_id, 'enrollment_date', $item['enroll_date']);
                        }

                        wp_update_post(array('ID' => $enroll_id, 'post_status' => 'completed'));

                        $created++;
                    }
                }

                // fetch and store progress if class_id present
                $class_id = !empty($item['class_id']) ? $item['class_id'] : (!empty($item['course_id']) ? $item['course_id'] : 0);
                if (!empty($class_id)) {
                    $prog1 = $this->edmingle_api_get('report/class/progress', array('class_id' => $class_id, 'page' => 1, 'per_page' => 50, 'material_required' => 1));
                    $prog2 = $this->edmingle_api_get('reports/classprogress', array('class_id' => $class_id, 'page' => 1, 'per_page' => 50));

                    $combined = array();
                    if (!empty($prog1)) $combined['material_progress'] = $prog1;
                    if (!empty($prog2)) $combined['exercise_progress'] = $prog2;

                    if (!empty($combined)) {
                        update_user_meta($user_id, 'tutor_course_progress_' . $course_id, wp_json_encode($combined));
                        $progress_updated++;
                    }
                }
            }
        }

        echo '<div class="updated"><p>' . $created . ' Enrollments created from EdMingle. ' . $progress_updated . ' progress entries updated.</p></div>';
    }

    /**
     * Test connection to configured EdMingle domain using saved headers
     */
    public function edmingle_test_connection() {
        $domain = get_option('vc_edmingle_domain', '');
        if (empty($domain)) {
            echo '<div class="notice notice-error"><p>Please set the EdMingle domain first.</p></div>';
            return;
        }

        $domain = rtrim($domain, '/') . '/';

        $args = array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/json'
            )
        );

        $api_key = get_option('vc_edmingle_api_key', '');
        $orgid = get_option('vc_edmingle_orgid', '');
        $server_key = get_option('vc_edmingle_server_key', '');

        if (!empty($api_key)) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
            $args['headers']['APIKEY'] = $api_key;
        }
        if (!empty($orgid)) {
            $args['headers']['ORGID'] = $orgid;
        }
        if (!empty($server_key)) {
            $args['headers']['server_key'] = $server_key;
        }

        $resp = wp_remote_get($domain, $args);

        if (is_wp_error($resp)) {
            $msg = 'Connection error: ' . $resp->get_error_message();
            update_option('vc_edmingle_test_result', $msg);
            $redirect = menu_page_url('vc-tutor-importer', false);
            wp_safe_redirect($redirect);
            exit;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        $snippet = wp_trim_words(strip_tags($body), 50, '...');

        $msg = 'HTTP ' . intval($code) . ' — Response snippet: ' . $snippet;
        update_option('vc_edmingle_test_result', $msg);
        $redirect = menu_page_url('vc-tutor-importer', false);
        wp_safe_redirect($redirect);
        exit;
    }

    public function ajax_edmingle_test_connection() {
        // read posted overrides or fall back to saved options
        $domain = isset($_POST['edmingle_domain']) && !empty($_POST['edmingle_domain']) ? esc_url_raw(trim($_POST['edmingle_domain'])) : get_option('vc_edmingle_domain', '');
        $api_key = isset($_POST['edmingle_api_key']) ? sanitize_text_field($_POST['edmingle_api_key']) : get_option('vc_edmingle_api_key', '');
        $orgid = isset($_POST['edmingle_orgid']) ? sanitize_text_field($_POST['edmingle_orgid']) : get_option('vc_edmingle_orgid', '');
        $server_key = isset($_POST['edmingle_server_key']) ? sanitize_text_field($_POST['edmingle_server_key']) : get_option('vc_edmingle_server_key', '');

        if (empty($domain)) {
            wp_send_json_error(array('message' => 'Please set the EdMingle domain.'));
        }

        $domain = rtrim($domain, '/') . '/';

        $args = array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/json'
            )
        );

        if (!empty($api_key)) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
            $args['headers']['APIKEY'] = $api_key;
        }
        if (!empty($orgid)) {
            $args['headers']['ORGID'] = $orgid;
        }
        if (!empty($server_key)) {
            $args['headers']['server_key'] = $server_key;
        }

        $resp = wp_remote_get($domain, $args);

        if (is_wp_error($resp)) {
            wp_send_json_error(array('message' => 'Connection error: ' . $resp->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        $snippet = wp_trim_words(strip_tags($body), 50, '...');

        wp_send_json_success(array('message' => 'HTTP ' . intval($code) . ' — ' . $snippet));
    }
}

new VC_Custom_Tutor_Importer();
