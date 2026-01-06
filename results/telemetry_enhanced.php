<?php
error_reporting(0);
require 'telemetry_settings.php';

// Get POST data
$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
}

$ispinfo = isset($_POST['ispinfo']) ? $_POST['ispinfo'] : '';
$extra = isset($_POST['extra']) ? $_POST['extra'] : '';
$ua = $_SERVER['HTTP_USER_AGENT'];
$lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
$dl = isset($_POST['dl']) ? $_POST['dl'] : 0;
$ul = isset($_POST['ul']) ? $_POST['ul'] : 0;
$ping = isset($_POST['ping']) ? $_POST['ping'] : 0;
$jitter = isset($_POST['jitter']) ? $_POST['jitter'] : 0;
$log = isset($_POST['log']) ? $_POST['log'] : '';

// New fields
$packet_loss = isset($_POST['packet_loss']) ? $_POST['packet_loss'] : 0;
$latency = isset($_POST['latency']) ? $_POST['latency'] : $ping; // Use ping as latency if not provided

try {
    $conn = new PDO("mysql:host=$MySql_hostname;dbname=$MySql_databasename;port=$MySql_port;charset=utf8mb4", 
                   $MySql_username, $MySql_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("INSERT INTO speedtest_users 
        (ip, ispinfo, extra, ua, lang, dl, ul, ping, jitter, log, packet_loss, latency, timestamp) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([$ip, $ispinfo, $extra, $ua, $lang, $dl, $ul, $ping, $jitter, $log, $packet_loss, $latency]);
    
    echo "id " . $conn->lastInsertId();
} catch(Exception $e) {
    echo "error " . $e->getMessage();
}
?>
