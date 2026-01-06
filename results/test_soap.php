<?php
/**
 * test_api_debug.php
 * Comprehensive API test script to debug SOAP issues
 */

echo "========================================\n";
echo "SOAP API COMPREHENSIVE TEST\n";
echo "========================================\n";
echo "Start Time: " . date('Y-m-d H:i:s') . "\n\n";

// Check if SOAP extension is loaded
echo "[CHECK 1] Verifying PHP SOAP extension...\n";
if (!extension_loaded('soap')) {
    echo "[ERROR] PHP SOAP extension is not installed!\n";
    echo "Please run: sudo apt install php-soap\n";
    echo "Then restart PHP-FPM: sudo systemctl restart php-fpm\n";
    exit(1);
}
echo "[OK] PHP SOAP extension is loaded\n\n";

// Include the SOAP API file
echo "[CHECK 2] Including soap_api.php file...\n";
$soap_file = '/var/www/html/librespeed/results/soap_api.php';
if (!file_exists($soap_file)) {
    echo "[ERROR] soap_api.php file not found at: $soap_file\n";
    exit(1);
}
require_once $soap_file;
echo "[OK] soap_api.php included successfully\n\n";

// Test parameters - using the EXACT failing record
$test_ip = "10.201.105.21";
$timestamp = "2025-11-12 16:02:52";

echo "========================================\n";
echo "TEST PARAMETERS\n";
echo "========================================\n";
echo "IP Address: $test_ip\n";
echo "Timestamp: $timestamp\n\n";

// TEST 1: Using ±2 hour window (current cron method)
echo "========================================\n";
echo "TEST 1: Using ±2 Hour Window (Cron Method)\n";
echo "========================================\n";
$fromDate1 = date('Y-m-d\TH:i:s', strtotime($timestamp . ' -2 hours'));
$toDate1 = date('Y-m-d\TH:i:s', strtotime($timestamp . ' +2 hours'));

echo "From Date: $fromDate1\n";
echo "To Date: $toDate1\n";
echo "Calling API...\n\n";

$result1 = getUserDetailsByIP($test_ip, $fromDate1, $toDate1);

if ($result1 === false) {
    echo "[RESULT 1] ✗ API call FAILED\n";
} elseif ($result1 === null || empty($result1)) {
    echo "[RESULT 1] ⚠ No data found\n";
} else {
    echo "[RESULT 1] ✓ SUCCESS!\n";
    echo "Data received:\n";
    foreach ($result1 as $key => $value) {
        echo "  " . str_pad($key, 20) . ": " . ($value ?? 'NULL') . "\n";
    }
}

echo "\n";
sleep(2);

// TEST 2: Using full day window (00:00:00 to 23:59:59)
echo "========================================\n";
echo "TEST 2: Using Full Day Window\n";
echo "========================================\n";
$date_only = date('Y-m-d', strtotime($timestamp));
$fromDate2 = $date_only . 'T00:00:00';
$toDate2 = $date_only . 'T23:59:59';

echo "From Date: $fromDate2\n";
echo "To Date: $toDate2\n";
echo "Calling API...\n\n";

$result2 = getUserDetailsByIP($test_ip, $fromDate2, $toDate2);

if ($result2 === false) {
    echo "[RESULT 2] ✗ API call FAILED\n";
} elseif ($result2 === null || empty($result2)) {
    echo "[RESULT 2] ⚠ No data found\n";
} else {
    echo "[RESULT 2] ✓ SUCCESS!\n";
    echo "Data received:\n";
    foreach ($result2 as $key => $value) {
        echo "  " . str_pad($key, 20) . ": " . ($value ?? 'NULL') . "\n";
    }
}

echo "\n";
sleep(2);

// TEST 3: Using exact time window from working example
echo "========================================\n";
echo "TEST 3: Using Working Example Format\n";
echo "========================================\n";
$fromDate3 = '2025-11-12T00:00:00';
$toDate3 = '2025-11-12T17:10:00';

echo "From Date: $fromDate3\n";
echo "To Date: $toDate3\n";
echo "Calling API...\n\n";

$result3 = getUserDetailsByIP($test_ip, $fromDate3, $toDate3);

if ($result3 === false) {
    echo "[RESULT 3] ✗ API call FAILED\n";
} elseif ($result3 === null || empty($result3)) {
    echo "[RESULT 3] ⚠ No data found\n";
} else {
    echo "[RESULT 3] ✓ SUCCESS!\n";
    echo "Data received:\n";
    foreach ($result3 as $key => $value) {
        echo "  " . str_pad($key, 20) . ": " . ($value ?? 'NULL') . "\n";
    }
}

echo "\n";
sleep(2);

// TEST 4: Test with a different known working IP
echo "========================================\n";
echo "TEST 4: Testing with Known Working IP\n";
echo "========================================\n";
$working_ip = "180.151.224.239";
$fromDate4 = '2025-09-09T09:53:12';
$toDate4 = '2025-09-10T09:54:12';

echo "IP Address: $working_ip\n";
echo "From Date: $fromDate4\n";
echo "To Date: $toDate4\n";
echo "Calling API...\n\n";

$result4 = getUserDetailsByIP($working_ip, $fromDate4, $toDate4);

if ($result4 === false) {
    echo "[RESULT 4] ✗ API call FAILED\n";
} elseif ($result4 === null || empty($result4)) {
    echo "[RESULT 4] ⚠ No data found\n";
} else {
    echo "[RESULT 4] ✓ SUCCESS!\n";
    echo "Data received:\n";
    foreach ($result4 as $key => $value) {
        echo "  " . str_pad($key, 20) . ": " . ($value ?? 'NULL') . "\n";
    }
}

echo "\n";

// TEST 5: Check SOAP connection directly
echo "========================================\n";
echo "TEST 5: Direct SOAP Connection Test\n";
echo "========================================\n";

try {
    $wsdl_url = "https://unify.spectra.co/unifyejb/UnifyWS?wsdl";
    echo "WSDL URL: $wsdl_url\n";
    echo "Testing connection...\n";
    
    $client = new SoapClient($wsdl_url, array(
        'trace' => 1,
        'exceptions' => true,
        'connection_timeout' => 30,
        'cache_wsdl' => WSDL_CACHE_NONE
    ));
    
    echo "[OK] SOAP client created successfully\n";
    echo "Available functions:\n";
    $functions = $client->__getFunctions();
    foreach ($functions as $func) {
        if (strpos($func, 'getDetailsByIp') !== false) {
            echo "  - $func\n";
        }
    }
    
} catch (Exception $e) {
    echo "[ERROR] SOAP connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n";
echo "Test 1 (±2 hours): " . ($result1 === false ? "FAILED" : ($result1 ? "SUCCESS" : "NO DATA")) . "\n";
echo "Test 2 (Full day): " . ($result2 === false ? "FAILED" : ($result2 ? "SUCCESS" : "NO DATA")) . "\n";
echo "Test 3 (Working format): " . ($result3 === false ? "FAILED" : ($result3 ? "SUCCESS" : "NO DATA")) . "\n";
echo "Test 4 (Known IP): " . ($result4 === false ? "FAILED" : ($result4 ? "SUCCESS" : "NO DATA")) . "\n";
echo "========================================\n";
echo "End Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";
?>