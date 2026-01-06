<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Get client IP
function getClientIp() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    
    return $ip;
}

$ip = getClientIp();

// Check if we need ISP info
$isp = isset($_GET['isp']) ? $_GET['isp'] : '';
$distance = isset($_GET['distance']) ? $_GET['distance'] : '';

if ($isp == 'true') {
    // Return with ISP info (simplified)
    echo json_encode([
        'processedString' => $ip . ' - Internal Network',
        'rawIspInfo' => json_encode([
            'ip' => $ip,
            'isp' => 'Internal Network',
            'org' => 'Local Network'
        ])
    ]);
} else {
    // Return just IP
    echo json_encode([
        'processedString' => $ip,
        'rawIspInfo' => ''
    ]);
}
?>
