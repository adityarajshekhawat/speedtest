<?php
session_start();
require_once 'telemetry_settings.php';

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: stats.php');
    exit;
}

try {
    // Connect to DB
    $conn = new PDO("mysql:host=$MySql_hostname;dbname=$MySql_databasename;port=$MySql_port", 
                   $MySql_username, $MySql_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Collect filters
    $from = isset($_GET['from']) && !empty($_GET['from']) ? $_GET['from'] : null;
    $to = isset($_GET['to']) && !empty($_GET['to']) ? $_GET['to'] : null;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 0;

    // Build WHERE clause
    $conditions = [];
    $params = [];

    if ($from !== null) {
        $conditions[] = "DATE(timestamp) >= :from";
        $params[':from'] = $from;
    }

    if ($to !== null) {
        $conditions[] = "DATE(timestamp) <= :to";
        $params[':to'] = $to;
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $limitClause = $limit > 0 ? "LIMIT $limit" : "";

    // Build final query with all new fields
    $query = "
        SELECT 
            id,
            timestamp,
            ip,
            ROUND(dl, 2) as dl,
            ROUND(ul, 2) as ul,
            ROUND(ping, 2) as ping,
            ROUND(jitter, 2) as jitter,
            ROUND(COALESCE(packet_loss, 0), 2) as packet_loss,
            ROUND(COALESCE(latency, ping), 2) as latency,
            account_name,
            service_group_id,
            domain_id,
            access_controller,
            mac_address,
            snat_ip,
            service_plan,
            bandwidth_policy_id,
            address,
            city,
            api_fetch_status,
            api_fetch_date
        FROM speedtest_users
        $whereClause
        ORDER BY timestamp DESC
        $limitClause
    ";

    $stmt = $conn->prepare($query);

    // Bind values
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    // Set proper headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="speedtest_results_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write column headers
    $headers = [
        'Test ID', 
        'Date & Time', 
        'Customer IP', 
        'Download (Mbps)', 
        'Upload (Mbps)', 
        'Ping (ms)', 
        'Jitter (ms)', 
        'Packet Loss (%)', 
        'Latency (ms)',
        'Account Name',
        'Service Group ID',
        'Domain ID',
        'Access Controller',
        'MAC Address',
        'SNAT IP',
        'Service Plan',
        'Bandwidth Policy',
        'Address',
        'City',
        'API Status',
        'API Fetch Date'
    ];
    fputcsv($output, $headers);

    // Write data rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $csvRow = [
            $row['id'],
            $row['timestamp'],
            $row['ip'],
            $row['dl'],
            $row['ul'],
            $row['ping'],
            $row['jitter'],
            $row['packet_loss'],
            $row['latency'],
            $row['account_name'] ?? '-',
            $row['service_group_id'] ?? '-',
            $row['domain_id'] ?? '-',
            $row['access_controller'] ?? '-',
            $row['mac_address'] ?? '-',
            $row['snat_ip'] ?? '-',
            $row['service_plan'] ?? '-',
            $row['bandwidth_policy_id'] ?? '-',
            $row['address'] ?? '-',
            $row['city'] ?? '-',
            $row['api_fetch_status'] ?? 'pending',
            $row['api_fetch_date'] ?? '-'
        ];
        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    // Don't output error to CSV, redirect back with error
    $_SESSION['export_error'] = "Database error: " . $e->getMessage();
    header('Location: stats.php?export_error=1');
    exit();
}
?>