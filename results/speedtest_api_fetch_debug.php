#!/usr/bin/php
<?php
/**
 * speedtest_api_fetch_debug_FINAL.php
 * FINAL WORKING VERSION
 * - Uses FULL DAY window (proven to work)
 * - Uses verbose API function to see all output
 * - Correct database column mappings
 */

// Include required files
require_once '/var/www/html/librespeed/results/db_config.php';
require_once '/var/www/html/librespeed/results/soap_api.php';

// Log file
$log_dir = '/var/www/html/librespeed/logs';
$log_file = $log_dir . '/speedtest_api_fetch_debug.log';

if (!file_exists($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

$batch_size = 10;

function writeLog($message, $also_echo = true) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    @file_put_contents($log_file, $log_message . "\n", FILE_APPEND);
    if ($also_echo) {
        echo $log_message . "\n";
    }
}

echo "\n========================================\n";
echo "FINAL DEBUG MODE - API Fetch Cron\n";
echo "Using FULL DAY window (proven to work)\n";
echo "========================================\n\n";

try {
    echo "Connecting to database...\n";
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connected\n\n";
    
    writeLog("========================================");
    writeLog("Starting FINAL DEBUG cron - Using FULL DAY window");
    
    echo "Fetching pending records...\n";
    $query = "SELECT id, ip, timestamp, api_fetch_status 
              FROM speedtest_users 
              WHERE (api_fetch_status = 'pending' OR api_fetch_status IS NULL)
              ORDER BY timestamp DESC
              LIMIT :batch_size";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_records = count($records);
    echo "Found $total_records pending records\n";
    echo "========================================\n\n";
    
    if ($total_records == 0) {
        echo "No pending records. Current status distribution:\n";
        $status_query = "SELECT api_fetch_status, COUNT(*) as count 
                        FROM speedtest_users GROUP BY api_fetch_status";
        foreach ($pdo->query($status_query)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            echo "  - " . ($row['api_fetch_status'] ?? 'NULL') . ": {$row['count']}\n";
        }
        exit(0);
    }
    
    $success_count = 0;
    $failed_count = 0;
    $na_count = 0;
    $record_num = 0;
    
    foreach ($records as $record) {
        $record_num++;
        $id = $record['id'];
        $ip = $record['ip'];
        $timestamp = $record['timestamp'];
        
        echo "\n========================================\n";
        echo "Record $record_num of $total_records\n";
        echo "========================================\n";
        echo "ID: $id\n";
        echo "IP: $ip\n";
        echo "Timestamp: $timestamp\n";
        echo "========================================\n\n";
        
        // USE FULL DAY WINDOW - This is what works!
        $date_only = date('Y-m-d', strtotime($timestamp));
        $fromDate = $date_only . 'T00:00:00';
        $toDate = $date_only . 'T23:59:59';
        
        echo "API Date Range (FULL DAY WINDOW):\n";
        echo "  From: $fromDate\n";
        echo "  To: $toDate\n";
        echo "========================================\n\n";
        
        try {
            // Use VERBOSE function to see all API details
            $api_result = getUserDetailsByIP($ip, $fromDate, $toDate);
            
            echo "\n========================================\n";
            echo "Processing Result for ID $id\n";
            echo "========================================\n";
            
            if ($api_result === false) {
                echo "✗ API CALL FAILED (SOAP error)\n\n";
                
                $pdo->prepare("UPDATE speedtest_users SET api_fetch_status='failed', api_fetch_date=NOW() WHERE id=?")
                    ->execute([$id]);
                
                $failed_count++;
                writeLog("ID $id: API call failed", false);
                
            } elseif ($api_result === null || empty($api_result)) {
                echo "⚠ NO DATA FOUND (user not in CRM for this time)\n\n";
                
                $update_sql = "UPDATE speedtest_users SET 
                    api_fetch_status = 'na',
                    api_fetch_date = NOW(),
                    account_name = 'NA',
                    service_group_id = 'NA',
                    domain_id = 'NA',
                    access_controller = 'NA',
                    mac_address = 'NA',
                    snat_ip = 'NA',
                    service_plan = 'NA',
                    bandwidth_policy_id = 'NA',
                    address = 'NA',
                    city = 'NA'
                    WHERE id = ?";
                
                $pdo->prepare($update_sql)->execute([$id]);
                
                $na_count++;
                writeLog("ID $id: No data found", false);
                
            } else {
                echo "✓✓✓ API SUCCESS! ✓✓✓\n\n";
                echo "Retrieved Data:\n";
                echo "  Account Name: " . ($api_result['account_name'] ?? 'N/A') . "\n";
                echo "  Service Group ID: " . ($api_result['service_group_id'] ?? 'N/A') . "\n";
                echo "  Domain ID: " . ($api_result['domain_id'] ?? 'N/A') . "\n";
                echo "  Access Controller: " . ($api_result['access_controller'] ?? 'N/A') . "\n";
                echo "  MAC Address: " . ($api_result['mac_address'] ?? 'N/A') . "\n";
                echo "  SNAT IP: " . ($api_result['snat_ip'] ?? 'N/A') . "\n";
                echo "  Service Plan: " . ($api_result['service_plan'] ?? 'N/A') . "\n";
                echo "  Bandwidth Policy: " . ($api_result['bandwidth_policy_id'] ?? 'N/A') . "\n";
                echo "  Address: " . ($api_result['address'] ?? 'N/A') . "\n";
                echo "  City: " . ($api_result['city'] ?? 'N/A') . "\n";
                echo "\n";
                
                // Update database with all fields
                $update_sql = "UPDATE speedtest_users SET 
                    api_fetch_status = ?,
                    api_fetch_date = NOW(),
                    account_name = ?,
                    service_group_id = ?,
                    domain_id = ?,
                    access_controller = ?,
                    mac_address = ?,
                    snat_ip = ?,
                    service_plan = ?,
                    bandwidth_policy_id = ?,
                    address = ?,
                    city = ?
                    WHERE id = ?";
                
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    'completed',
                    $api_result['account_name'] ?? '',
                    $api_result['service_group_id'] ?? '',
                    $api_result['domain_id'] ?? '',
                    $api_result['access_controller'] ?? '',
                    $api_result['mac_address'] ?? '',
                    $api_result['snat_ip'] ?? '',
                    $api_result['service_plan'] ?? '',
                    $api_result['bandwidth_policy_id'] ?? '',
                    $api_result['address'] ?? '',
                    $api_result['city'] ?? '',
                    $id
                ]);
                
                echo "✓ Database updated successfully!\n\n";
                $success_count++;
                writeLog("ID $id: Successfully updated with account: " . ($api_result['account_name'] ?? 'Unknown'), false);
            }
            
        } catch (Exception $e) {
            echo "\n✗✗✗ EXCEPTION CAUGHT ✗✗✗\n";
            echo "Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n\n";
            writeLog("ID $id: Exception - " . $e->getMessage(), false);
            $failed_count++;
        }
        
        echo "========================================\n";
        echo "Current Progress:\n";
        echo "  ✓ Success: $success_count\n";
        echo "  ⚠ No Data: $na_count\n";
        echo "  ✗ Failed: $failed_count\n";
        echo "========================================\n";
        
        if ($record_num < $total_records) {
            echo "\nWaiting 2 seconds before next record...\n";
            sleep(2);
        }
    }
    
    echo "\n\n";
    echo "========================================\n";
    echo "FINAL SUMMARY\n";
    echo "========================================\n";
    echo "Total Records Processed: $total_records\n";
    echo "✓ Successful Updates: $success_count\n";
    echo "⚠ No Data Found: $na_count\n";
    echo "✗ Failed: $failed_count\n";
    echo "========================================\n\n";
    
    writeLog("========================================", false);
    writeLog("Final Summary - Success: $success_count, NA: $na_count, Failed: $failed_count", false);
    writeLog("========================================", false);
    
} catch (Exception $e) {
    echo "\n✗✗✗ FATAL ERROR ✗✗✗\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    writeLog("FATAL ERROR: " . $e->getMessage(), false);
    exit(1);
}
?>