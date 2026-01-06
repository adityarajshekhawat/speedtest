#!/usr/bin/php
<?php
/**
 * api_fetch_cron_production.php
 * FIXED: Now processes NA records < 2 days old
 */

require_once '/var/www/html/librespeed/results/db_config.php';
require_once '/var/www/html/librespeed/results/api_functions.php';

$log_dir = '/var/www/html/librespeed/logs';
$log_file = $log_dir . '/speedtest_api_fetch.log';

if (!file_exists($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

$batch_size = 100;

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    writeLog("========================================");
    writeLog("Starting API fetch cron (6-hour cycle with 2-day grace period)");
    
    // FIXED: Now includes NA records < 2 days old for retry
    $where_conditions = [
        "(api_fetch_status = 'pending' OR api_fetch_status IS NULL)",  // All pending
        "api_fetch_status = 'failed'",  // All failed
        "(api_fetch_status = 'na' AND timestamp >= DATE_SUB(NOW(), INTERVAL 2 DAY))"  // NA < 2 days - RETRY THESE!
    ];
    
    $where_clause = "WHERE (" . implode(" OR ", $where_conditions) . ")";
    
    writeLog("Processing rules:");
    writeLog("  - PENDING records (any age)");
    writeLog("  - FAILED records (any age)");
    writeLog("  - NA records < 2 days old (RETRY)");
    writeLog("  - SKIP: NA records >= 2 days old");
    
    // Get count
    $count_query = "SELECT COUNT(*) as total FROM speedtest_users $where_clause";
    $stmt = $pdo->query($count_query);
    $total_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    writeLog("Total records to process: $total_pending");
    
    if ($total_pending == 0) {
        writeLog("No records to process. Exiting.");
        
        $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN api_fetch_status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN api_fetch_status = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN api_fetch_status = 'na' THEN 1 ELSE 0 END) as no_data,
                        SUM(CASE WHEN api_fetch_status = 'pending' OR api_fetch_status IS NULL THEN 1 ELSE 0 END) as pending
                        FROM speedtest_users";
        
        $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
        writeLog("DB Stats - Total: {$stats['total']}, Completed: {$stats['completed']}, Pending: {$stats['pending']}, Failed: {$stats['failed']}, NA: {$stats['no_data']}");
        writeLog("========================================");
        exit(0);
    }
    
    $success_count = 0;
    $failed_count = 0;
    $na_count = 0;
    $pending_retry_count = 0;
    $total_processed = 0;
    $batch_number = 0;
    
    while ($total_processed < $total_pending) {
        $batch_number++;
        
        $query = "SELECT id, ip, timestamp, api_fetch_status 
                  FROM speedtest_users 
                  $where_clause
                  ORDER BY timestamp DESC
                  LIMIT :batch_size";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $batch_count = count($records);
        
        if ($batch_count == 0) {
            break;
        }
        
        writeLog("Batch $batch_number: Processing $batch_count records...");
        
        foreach ($records as $record) {
            $total_processed++;
            $id = $record['id'];
            $ip = $record['ip'];
            $timestamp = $record['timestamp'];
            $current_status = $record['api_fetch_status'];
            
            if ($total_processed % 10 == 0 || $total_processed == 1) {
                writeLog("[$total_processed/$total_pending] ID: $id | IP: $ip | Current: $current_status | Time: $timestamp");
            }
            
            // Call API with 2-day logic
            $result = fetchAndUpdateRecord($pdo, $id, $ip, $timestamp);
            
            switch($result) {
                case 'success':
                    $success_count++;
                    break;
                case 'na':
                    $na_count++;
                    break;
                case 'pending_retry':
                    $pending_retry_count++;
                    break;
                case 'failed':
                    $failed_count++;
                    break;
            }
            
            if ($total_processed % 25 == 0) {
                writeLog("Progress: $total_processed | Success: $success_count | NA: $na_count | Pending: $pending_retry_count | Failed: $failed_count");
                sleep(2);
            }
            
            usleep(300000);
        }
        
        writeLog("Batch $batch_number completed. Total so far: $total_processed");
    }
    
    writeLog("========================================");
    writeLog("Cron job COMPLETED");
    writeLog("Total processed: $total_processed");
    writeLog("✅ Successful: $success_count");
    writeLog("⚠️  Marked NA (>= 2 days): $na_count");
    writeLog("🔄 Changed NA→Pending (< 2 days): $pending_retry_count");
    writeLog("❌ Failed: $failed_count");
    
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN api_fetch_status = 'pending' OR api_fetch_status IS NULL THEN 1 ELSE 0 END) as still_pending,
                    SUM(CASE WHEN api_fetch_status = 'completed' THEN 1 ELSE 0 END) as total_completed,
                    SUM(CASE WHEN api_fetch_status = 'failed' THEN 1 ELSE 0 END) as total_failed,
                    SUM(CASE WHEN api_fetch_status = 'na' THEN 1 ELSE 0 END) as total_no_data
                    FROM speedtest_users";
    
    $final_stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
    writeLog("========================================");
    writeLog("Final Database Statistics:");
    writeLog("- Total: {$final_stats['total']}");
    writeLog("- Pending: {$final_stats['still_pending']}");
    writeLog("- Completed: {$final_stats['total_completed']}");
    writeLog("- Failed: {$final_stats['total_failed']}");
    writeLog("- NA: {$final_stats['total_no_data']}");
    writeLog("========================================");
    
} catch (Exception $e) {
    writeLog("FATAL ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
?>