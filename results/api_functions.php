<?php
/**
 * api_functions.php
 * UPDATED: With 2-day grace period logic
 * - Records < 2 days old with no data → stay 'pending'
 * - Records >= 2 days old with no data → mark 'na'
 */

require_once '/var/www/html/librespeed/results/soap_api.php';

/**
 * Fetch data from API and update database record
 * WITH 2-DAY GRACE PERIOD
 */
function fetchAndUpdateRecord($pdo, $id, $ip, $timestamp) {
    global $log_file;
    
    // Calculate age of record in hours
    $record_age_hours = (time() - strtotime($timestamp)) / 3600;
    $is_old_record = $record_age_hours >= 48; // 2 days = 48 hours
    
    // USE FULL DAY WINDOW - This works!
    $date_only = date('Y-m-d', strtotime($timestamp));
    $fromDate = $date_only . 'T00:00:00';
    $toDate = $date_only . 'T23:59:59';
    
    try {
        // Call the SOAP API (silent version)
        $data = getUserDetailsByIP_Silent($ip, $fromDate, $toDate);
        
        if ($data === false) {
            // API call failed
            updateRecordStatus($pdo, $id, 'failed');
            writeLogMessage("API call FAILED for ID: $id, IP: $ip");
            return 'failed';
            
        } elseif ($data === null || empty($data['account_name'])) {
            // No data found
            if ($is_old_record) {
                // Record is >= 2 days old, mark as NA
                updateRecordWithNA($pdo, $id);
                writeLogMessage("No data for ID: $id (>= 2 days old, age: " . round($record_age_hours, 1) . "h) - Marked as NA");
                return 'na';
            } else {
                // Record is < 2 days old, keep as PENDING
                updateRecordStatus($pdo, $id, 'pending');
                writeLogMessage("No data for ID: $id (< 2 days old, age: " . round($record_age_hours, 1) . "h) - Kept as PENDING");
                return 'pending_retry';
            }
            
        } else {
            // Data found - update with actual values
            updateRecordWithData($pdo, $id, $data);
            writeLogMessage("SUCCESS ID: $id - Account: " . $data['account_name']);
            return 'success';
        }
        
    } catch (Exception $e) {
        writeLogMessage("ERROR ID $id: " . $e->getMessage());
        updateRecordStatus($pdo, $id, 'failed');
        return 'failed';
    }
}

/**
 * Silent version of getUserDetailsByIP without debug output
 */
function getUserDetailsByIP_Silent($ip, $fromDate, $toDate) {
    try {
        $wsdl = "https://unify.spectra.co/unifyejb/UnifyWS?wsdl";
        
        $opts = array(
            'http' => array(
                'header' => "username: admin\r\n" .
                           "password: admin\r\n"
            )
        );
        $context = stream_context_create($opts);
        
        $client = new SoapClient($wsdl, [
            'trace' => 1,
            'exceptions' => true,
            'connection_timeout' => 30,
            'default_socket_timeout' => 30,
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
        
        if (isset($response->return)) {
            $data = is_array($response->return) ? $response->return[0] : $response->return;
            
            return [
                'account_name' => $data->actName ?? null,
                'service_group_id' => $data->actid ?? null,
                'domain_id' => $data->domId ?? null,
                'access_controller' => $data->accessController ?? null,
                'mac_address' => $data->macaddr ?? null,
                'snat_ip' => $data->snatIp ?? null,
                'service_plan' => $data->pkgDescription ?? null,
                'bandwidth_policy_id' => $data->bandwidthPolicy ?? null,
                'address' => $data->address ?? null,
                'city' => $data->city ?? null
            ];
        }
        return null;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update record with actual data from API
 */
function updateRecordWithData($pdo, $id, $data) {
    $query = "UPDATE speedtest_users SET 
              account_name = :account_name,
              service_group_id = :service_group_id,
              domain_id = :domain_id,
              access_controller = :access_controller,
              mac_address = :mac_address,
              snat_ip = :snat_ip,
              service_plan = :service_plan,
              bandwidth_policy_id = :bandwidth_policy_id,
              address = :address,
              city = :city,
              api_fetch_status = 'completed',
              api_fetch_date = NOW()
              WHERE id = :id";
    
    $stmt = $pdo->prepare($query);
    $params = $data;
    $params['id'] = $id;
    $stmt->execute($params);
}

/**
 * Update record with NA values when no data found (>= 2 days)
 */
function updateRecordWithNA($pdo, $id) {
    $query = "UPDATE speedtest_users SET 
              account_name = 'NA',
              service_group_id = 'NA',
              domain_id = 'NA',
              access_controller = 'NA',
              mac_address = 'NA',
              snat_ip = 'NA',
              service_plan = 'NA',
              bandwidth_policy_id = 'NA',
              address = 'NA',
              city = 'NA',
              api_fetch_status = 'na',
              api_fetch_date = NOW()
              WHERE id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
}

/**
 * Update only the status
 */
function updateRecordStatus($pdo, $id, $status) {
    $query = "UPDATE speedtest_users SET 
              api_fetch_status = :status,
              api_fetch_date = NOW()
              WHERE id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'status' => $status,
        'id' => $id
    ]);
}

/**
 * Write log message
 */
function writeLogMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}
?>