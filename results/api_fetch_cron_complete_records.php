#!/usr/bin/php
<?php
/**
 * api_fetch_cron.php
 * Cron job to fetch user details for ALL pending speedtest records
 * Can be run manually or scheduled via cron
 * Manual run: php /var/www/librespeed/results/api_fetch_cron.php
 * Crontab (daily at 2 AM): 0 2 * * * /usr/bin/php /var/www/librespeed/results/api_fetch_cron.php
 * Crontab (every hour): 0 * * * * /usr/bin/php /var/www/librespeed/results/api_fetch_cron.php
 */

// Include required files
require_once '/var/www/html/librespeed/results/db_config.php';
require_once '/var/www/html/librespeed/results/api_functions.php';

// Log file - store in librespeed logs directory
$log_dir = '/var/www/html/librespeed/logs';
$log_file = $log_dir . '/complete_speedtest_api_fetch.log';

// Create logs directory if it doesn't exist
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Batch size - process records in batches to avoid memory issues
$batch_size = 100;

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    writeLog("========================================");
    writeLog("Starting API fetch cron job - Processing ALL pending records");
    
    // First, get the count of ALL pending records
    $count_query = "SELECT COUNT(*) as total 
                    FROM speedtest_users 
                    WHERE (api_fetch_status = 'pending' OR api_fetch_status IS NULL)";
    
    $stmt = $pdo->query($count_query);
    $total_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    writeLog("Total pending records found: $total_pending");
    
    if ($total_pending == 0) {
        writeLog("No pending records to process. Exiting.");
        
        // Log some statistics
        $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN api_fetch_status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN api_fetch_status = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN api_fetch_status = 'no_data' THEN 1 ELSE 0 END) as no_data
                        FROM speedtest_users";
        
        $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
        writeLog("Database stats - Total: {$stats['total']}, Completed: {$stats['completed']}, Failed: {$stats['failed']}, No Data: {$stats['no_data']}");
        exit(0);
    }
    
    $success_count = 0;
    $failed_count = 0;
    $na_count = 0;
    $total_processed = 0;
    $batch_number = 0;
    
    // Process records in batches
    while ($total_processed < $total_pending) {
        $batch_number++;
        
        // Get a batch of pending records
        $query = "SELECT id, ip, timestamp 
                  FROM speedtest_users 
                  WHERE (api_fetch_status = 'pending' OR api_fetch_status IS NULL)
                  ORDER BY timestamp DESC
                  LIMIT :batch_size";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':batch_size', $batch_size, PDO::PARAM_INT);
        $stmt->execute();
        $pending_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $batch_count = count($pending_records);
        
        if ($batch_count == 0) {
            break; // No more records to process
        }
        
        writeLog("Processing batch $batch_number ($batch_count records)...");
        
        foreach ($pending_records as $record) {
            $total_processed++;
            $id = $record['id'];
            $ip = $record['ip'];
            $timestamp = $record['timestamp'];
            
            // Show progress every 10 records
            if ($total_processed % 10 == 0 || $total_processed == 1) {
                writeLog("[$total_processed/$total_pending] Processing ID: $id, IP: $ip, Timestamp: $timestamp");
            }
            
            // Call API for this IP and timestamp
            $result = fetchAndUpdateRecord($pdo, $id, $ip, $timestamp);
            
            switch($result) {
                case 'success':
                    $success_count++;
                    break;
                case 'na':
                    $na_count++;
                    break;
                case 'failed':
                    $failed_count++;
                    break;
            }
            
            // Rate limiting to avoid overwhelming the API
            if ($total_processed % 25 == 0) {
                writeLog("Progress update: $total_processed records processed (Success: $success_count, No data: $na_count, Failed: $failed_count)");
                sleep(2); // 2 second pause every 25 records
            }
            
            // Small delay between each API call
            usleep(300000); // 300ms delay between each record
        }
        
        writeLog("Batch $batch_number completed. Total processed so far: $total_processed");
    }
    
    // Final summary
    writeLog("========================================");
    writeLog("API fetch cron job completed");
    writeLog("Total records processed: $total_processed");
    writeLog("Successful updates: $success_count");
    writeLog("No data (NA): $na_count");
    writeLog("Failed: $failed_count");
    
    // Get final statistics
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN api_fetch_status = 'pending' OR api_fetch_status IS NULL THEN 1 ELSE 0 END) as still_pending,
                    SUM(CASE WHEN api_fetch_status = 'completed' THEN 1 ELSE 0 END) as total_completed,
                    SUM(CASE WHEN api_fetch_status = 'failed' THEN 1 ELSE 0 END) as total_failed,
                    SUM(CASE WHEN api_fetch_status = 'no_data' THEN 1 ELSE 0 END) as total_no_data
                    FROM speedtest_users";
    
    $final_stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
    writeLog("Final database statistics:");
    writeLog("- Total records: {$final_stats['total']}");
    writeLog("- Still pending: {$final_stats['still_pending']}");
    writeLog("- Total completed: {$final_stats['total_completed']}");
    writeLog("- Total failed: {$final_stats['total_failed']}");
    writeLog("- Total no data: {$final_stats['total_no_data']}");
    writeLog("========================================");
    
} catch (Exception $e) {
    writeLog("Fatal error: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
?>