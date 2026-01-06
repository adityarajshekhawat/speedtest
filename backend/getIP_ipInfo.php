<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// This file is called when ISP info is requested
// For internal network, we'll just return basic info

$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
}

echo json_encode([
    'processedString' => $ip . ' - Internal Network',
    'rawIspInfo' => json_encode([
        'ip' => $ip,
        'isp' => 'Internal Network',
        'org' => 'Local Network'
    ])
]);
?>
