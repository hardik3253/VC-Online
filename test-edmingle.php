<?php
require_once dirname(__FILE__) . '/wp-load.php';

$api = new \ETM\Includes\ApiClient();

echo "Testing standard 'page' parameter...\n";
$res1 = $api->execute_request('GET', 'organization/students', array(), array(), array('limit' => 50, 'page' => 1));
$res2 = $api->execute_request('GET', 'organization/students', array(), array(), array('limit' => 50, 'page' => 2));

$id1 = isset($res1['data']['students'][0]['id']) ? $res1['data']['students'][0]['id'] : 'Unknown';
$id2 = isset($res2['data']['students'][0]['id']) ? $res2['data']['students'][0]['id'] : 'Unknown';
echo "Page 1 ID: $id1 | Page 2 ID: $id2\n";

if ($id1 === $id2) {
    echo "Pagination failed. Testing 'offset'...\n";
    $res3 = $api->execute_request('GET', 'organization/students', array(), array(), array('limit' => 50, 'offset' => 50));
    $id3 = isset($res3['data']['students'][0]['id']) ? $res3['data']['students'][0]['id'] : 'Unknown';
    echo "Offset 50 ID: $id3\n";
    
    if ($id1 === $id3) {
        echo "Pagination failed. Testing 'start'...\n";
        $res4 = $api->execute_request('GET', 'organization/students', array(), array(), array('limit' => 50, 'start' => 50));
        $id4 = isset($res4['data']['students'][0]['id']) ? $res4['data']['students'][0]['id'] : 'Unknown';
        echo "Start 50 ID: $id4\n";
        
        if ($id1 === $id4) {
            echo "Pagination failed. Testing 'page_no'...\n";
            $res5 = $api->execute_request('GET', 'organization/students', array(), array(), array('limit' => 50, 'page_no' => 2));
            $id5 = isset($res5['data']['students'][0]['id']) ? $res5['data']['students'][0]['id'] : 'Unknown';
            echo "Page_no 2 ID: $id5\n";

            if ($id1 === $id5) {
                echo "Pagination failed. Testing 'pageNumber'...\n";
                $res6 = $api->execute_request('GET', 'organization/students', array(), array(), array('limit' => 50, 'pageNumber' => 2));
                $id6 = isset($res6['data']['students'][0]['id']) ? $res6['data']['students'][0]['id'] : 'Unknown';
                echo "PageNumber 2 ID: $id6\n";
            }
        }
    }
}
