<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';

use ETM\Includes\Edmingle_API;

$tests = array(
    'organization' => 'institution/organizations?institution_id=' . get_option('etm_admin_institution_id'),
    'students_real' => 'organization/students?organization_id=' . get_option('etm_admin_orgid') . '&page=1&per_page=5',
    'students_old' => 'users?limit=1',
    'enrollments'  => 'enrollments?limit=1',
    'curriculum'   => 'curriculum?limit=1',
    'progress'     => 'progress?limit=1',
);

$results = array();
foreach ($tests as $key => $endpoint) {
    $res = Edmingle_API::request($endpoint);
    if (is_wp_error($res)) {
        $results[$key] = "Error: " . $res->get_error_message();
    } else {
        $results[$key] = "Success. Code or Message: " . (isset($res['data']['message']) ? $res['data']['message'] : (isset($res['data']['code']) ? $res['data']['code'] : 'OK'));
    }
}
echo json_encode($results, JSON_PRETTY_PRINT);
