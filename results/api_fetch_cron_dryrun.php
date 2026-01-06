#!/usr/bin/php
<?php
/**
 * api_fetch_cron_production_DRYRUN.php
 * DRY RUN - Shows what the 6-hour cron would do WITHOUT updating database
 */

// Include required files
require_once '/var/www/html/librespeed/results/db_config.php';
require_once '/var/www/html/librespeed/results/soap_api.php';

// Batch size
$batch_size = 50; // Smaller for dry run

echo "\n";
echo "========================================\n";
echo "DRY RUN - 6-HOUR PRODUCTION CRON\n";
echo "NO DATABASE CHANGES WILL BE MADE\n";
echo "========================================\n";
echo "Start Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Database connected\n\n";
    
    // Build WHERE clause for all three conditions
    $where_conditions = [
        "(api_fetch_status = 'pending' OR api_fetch_status IS NULL)",  // All pending
        "api_fetch_status = 'failed'",  // All failed
        "(api_fetch_status = 'na' AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR))"  // NA < 24 hours
    ];
    
    $where_clause = "WHERE (" . implode(" OR ", $where_conditions) . ")";
    
    echo "Processing Rules:\n";
    echo "  1. All PENDING records (any age)\n";
    echo "  2. All FAILED records (any age)\n";
    echo "  3. NA records < 24 hours old\n";
    echo "  4. SKIP: NA records > 24 hours old\n\n";
    
    // Get count of records to process
    $count_query = "SELECT COUNT(*) as total FROM speedtest_users $where_clause";
    $stmt = $pdo->query($count_query);
    $total_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "Total records that would be processed: $total_pending\n\n";
    
    // Show breakdown by status
    echo "Breakdown:\n";
    $breakdown_query = "SELECT 
        SUM(CASE WHEN api_fetch_status = 'pending' OR api_fetch_status IS NULL THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN api_fetch_status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN api_fetch_status = 'na' AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as na_recent
        FROM speedtest_users
        $where_clause";
    $breakdown = $pdo->query($breakdown_query)->fetch(PDO::FETCH_ASSOC);
    echo "  - Pending: {$breakdown['pending']}\n";
    echo "  - Failed: {$breakdown['failed']}\n";
    echo "  - NA (< 24h): {$breakdown['na_recent']}\n\n";
    
    // Show NA records that would be SKIPPED
    $skip_query = "SELECT COUNT(*) as skipped 
                   FROM speedtest_users 
                   WHERE api_fetch_status = 'na' 
                   AND timestamp < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $skipped = $pdo->query($skip_query)->fetch(PDO::FETCH_ASSOC)['skipped'];
    echo "Records that would be SKIPPED:\n";
    echo "  - NA (> 24h old): $skipped\n\n";
    
    if ($total_pending == 0) {
        echo "No records to process. Cron would exit.\n\n";
        exit(0);
    }
    
    echo "========================================\n";
    echo "Processing first $batch_size records...\n";
    echo "========================================\n\n";
    
    // Get a batch of records
    $query = "SELECT id, ip, timestamp, api_fetch_status 
              FROM speedtest_users 
              $where_clause
              ORDER BY timestamp DESC
              LIMIT :batch_size";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $would_succeed = 0;
    $would_be_na = 0;
    $would_fail = 0;
    $processed = 0;
    
    foreach ($records as $record) {
        $processed++;
        $id = $record['id'];
        $ip = $record['ip'];
        $timestamp = $record['timestamp'];
        $current_status = $record['api_fetch_status'] ?? 'NULL';
        
        echo "\n----------------------------------------\n";
        echo "Record $processed of " . min($batch_size, $total_pending) . "\n";
        echo "----------------------------------------\n";
        echo "ID: $id\n";
        echo "IP: $ip\n";
        echo "Timestamp: $timestamp\n";
        echo "Current Status: $current_status\n";
        
        // Calculate date range - FULL DAY WINDOW
        $date_only = date('Y-m-d', strtotime($timestamp));
        $fromDate = $date_only . 'T00:00:00';
        $toDate = $date_only . 'T23:59:59';
        
        echo "API Query: $date_only (full day)\n";
        echo "Calling API... ";
        
        try {
            $wsdl = "https://unify.spectra.co/unifyejb/UnifyWS?wsdl";
            $opts = array(
                'http' => array(
                    'header' => "username: admin\r\npassword: admin\r\n"
                )
            );
            $context = stream_context_create($opts);
            
            $client = new SoapClient($wsdl, [
                'trace' => 1,
                'exceptions' => true,
                'connection_timeout' => 30,
                'stream_context' => $context
            ]);
            
            $params = [
                'ipaddr' => $ip,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'start' => 0,
                'limit' => 1
            ];
            
            $response = $client->getDetailsByIp($params);
            
            if (isset($response->return) && !empty($response->return)) {
                $data = is_array($response->return) ? $response->return[0] : $response->return;
                
                echo "✅ SUCCESS\n";
                echo "→ Would update to: 'completed'\n";
                echo "→ Account: " . ($data->actName ?? 'N/A') . "\n";
                echo "→ Domain: " . ($data->domId ?? 'N/A') . "\n";
                echo "→ Service Plan: " . ($data->pkgDescription ?? 'N/A') . "\n";
                $would_succeed++;
                
            } else {
                echo "⚠️  NO DATA\n";
                echo "→ Would update to: 'na'\n";
                echo "→ All fields would be set to: 'NA'\n";
                $would_be_na++;
            }
            
        } catch (Exception $e) {
            echo "❌ FAILED\n";
            echo "→ Would update to: 'failed'\n";
            echo "→ Error: " . $e->getMessage() . "\n";
            $would_fail++;
        }
        
        if ($processed < min($batch_size, $total_pending)) {
            sleep(1); // 1 second delay
        }
    }
    
    echo "\n\n";
    echo "========================================\n";
    echo "DRY RUN SUMMARY\n";
    echo "========================================\n";
    echo "Records tested: $processed\n";
    echo "Would succeed: $would_succeed\n";
    echo "Would be NA: $would_be_na\n";
    echo "Would fail: $would_fail\n";
    echo "========================================\n";
    echo "Total remaining: " . ($total_pending - $processed) . "\n";
    echo "========================================\n";
    echo "NO DATABASE CHANGES WERE MADE\n";
    echo "========================================\n";
    echo "End Time: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n\n";
    
} catch (Exception $e) {
    echo "\n❌ FATAL ERROR\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>