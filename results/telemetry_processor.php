<?php
// results/telemetry.php - Enhanced version with packet loss and latency support

error_reporting(0);
require_once 'telemetry_settings.php';

// Get IP address
$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
}

// Get POST data from speed test
$ispinfo = isset($_POST['ispinfo']) ? $_POST['ispinfo'] : '';
$extra = isset($_POST['extra']) ? $_POST['extra'] : '';
$ua = $_SERVER['HTTP_USER_AGENT'];
$lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
$dl = isset($_POST['dl']) ? floatval($_POST['dl']) : 0;
$ul = isset($_POST['ul']) ? floatval($_POST['ul']) : 0;
$ping = isset($_POST['ping']) ? floatval($_POST['ping']) : 0;
$jitter = isset($_POST['jitter']) ? floatval($_POST['jitter']) : 0;

// Extract packet loss and latency - THREE methods to ensure we get the values
$packet_loss = 0;
$latency = 0;

// Method 1: Direct POST parameters (if sent separately)
if (isset($_POST['packet_loss'])) {
    $packet_loss = floatval($_POST['packet_loss']);
}
if (isset($_POST['latency'])) {
    $latency = floatval($_POST['latency']);
}

// Method 2: Extract from log field (if sent as log)
if (isset($_POST['log']) && !empty($_POST['log'])) {
    $log = $_POST['log'];
    
    // Extract packet_loss from log
    if (preg_match('/packet_loss:([0-9.]+)/', $log, $matches)) {
        $packet_loss = floatval($matches[1]);
    }
    
    // Extract latency from log
    if (preg_match('/latency:([0-9.]+)/', $log, $matches)) {
        $latency = floatval($matches[1]);
    }
}

// Method 3: If latency is still 0, use ping as fallback (but this should not happen with our fix)
if ($latency == 0 && $ping > 0) {
    // DO NOT use ping as latency - keep them separate
    // $latency = $ping; // REMOVED - This was the problem!
    $latency = 0; // Keep it 0 if not measured
}

// Validate data
if($dl > 10000) $dl = 10000;
if($ul > 10000) $ul = 10000;
if($ping > 2000) $ping = 2000;
if($jitter > 2000) $jitter = 2000;
if($packet_loss > 100) $packet_loss = 100;
if($latency > 2000) $latency = 2000;

// Connect to database
try {
    if ($db_type == 'mysql') {
        $conn = new PDO("mysql:host=$MySql_hostname;dbname=$MySql_databasename;port=$MySql_port", 
                       $MySql_username, $MySql_password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // First, check if packet_loss and latency columns exist
        $checkColumns = $conn->query("SHOW COLUMNS FROM speedtest_users");
        $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
        
        $hasPacketLoss = in_array('packet_loss', $columns);
        $hasLatency = in_array('latency', $columns);
        
        // Add columns if they don't exist
        if (!$hasPacketLoss) {
            $conn->exec("ALTER TABLE speedtest_users ADD COLUMN packet_loss DECIMAL(5,2) DEFAULT NULL AFTER jitter");
        }
        if (!$hasLatency) {
            $conn->exec("ALTER TABLE speedtest_users ADD COLUMN latency DECIMAL(8,2) DEFAULT NULL AFTER packet_loss");
        }
        
        // Insert data with packet_loss and latency
        $stmt = $conn->prepare("INSERT INTO speedtest_users 
                               (ip, ispinfo, extra, ua, lang, dl, ul, ping, jitter, packet_loss, latency, log) 
                               VALUES 
                               (:ip, :ispinfo, :extra, :ua, :lang, :dl, :ul, :ping, :jitter, :packet_loss, :latency, :log)");
        
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':ispinfo', $ispinfo);
        $stmt->bindParam(':extra', $extra);
        $stmt->bindParam(':ua', $ua);
        $stmt->bindParam(':lang', $lang);
        $stmt->bindParam(':dl', $dl);
        $stmt->bindParam(':ul', $ul);
        $stmt->bindParam(':ping', $ping);
        $stmt->bindParam(':jitter', $jitter);
        $stmt->bindParam(':packet_loss', $packet_loss);
        $stmt->bindParam(':latency', $latency);
        $stmt->bindParam(':log', $_POST['log']);
        
        $stmt->execute();
        $id = $conn->lastInsertId();
        
        // Return the test ID
        echo "Test ID: " . $id;
        
        // Debug output (remove in production)
        if (isset($_GET['debug'])) {
            echo "\nDebug Info:";
            echo "\nPing: $ping ms";
            echo "\nLatency: $latency ms";
            echo "\nPacket Loss: $packet_loss %";
            echo "\nLog: " . $_POST['log'];
        }
        
    } elseif ($db_type == 'sqlite') {
        // SQLite implementation (similar structure)
        $conn = new PDO("sqlite:$Sqlite_db_file");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Similar column check and insertion for SQLite
        // ... (implement if needed)
    }
} catch(PDOException $e) {
    // Log error but don't expose details to user
    error_log("Telemetry error: " . $e->getMessage());
    echo "Error saving test results";
}
?>