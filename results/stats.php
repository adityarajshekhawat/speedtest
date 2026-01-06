<?php
session_start();
require_once 'telemetry_settings.php';

// Check authentication
$authenticated = false;
if (isset($_POST['password'])) {
    if ($_POST['password'] === $stats_password) {
        $_SESSION['authenticated'] = true;
        $authenticated = true;
    } else {
        $error = "Invalid password";
    }
} elseif (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $authenticated = true;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: stats.php');
    exit;
}

// Get stats from database if authenticated
$stats = [];
$summary = [];
if ($authenticated) {
    try {
        $conn = new PDO("mysql:host=$MySql_hostname;dbname=$MySql_databasename;port=$MySql_port", 
                       $MySql_username, $MySql_password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get filters
        $dateFilter = isset($_GET['date']) ? $_GET['date'] : 'all';
        $ipFilter = isset($_GET['ip']) ? $_GET['ip'] : '';
        
        // Build date condition
        $dateCondition = '';
        switch($dateFilter) {
            case 'today':
                $dateCondition = "WHERE DATE(timestamp) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = "WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
        
        // Add IP filter if provided
        if (!empty($ipFilter)) {
            $dateCondition .= empty($dateCondition) ? "WHERE" : " AND";
            $dateCondition .= " ip LIKE :ip";
        }
        
        // Get page number
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10; // Number of results per page
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $countQuery = "SELECT COUNT(*) FROM speedtest_users $dateCondition";
        $countStmt = $conn->prepare($countQuery);
        if (!empty($ipFilter)) {
            $countStmt->bindValue(':ip', "%$ipFilter%");
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();
        $totalPages = ceil($totalCount / $limit);
        
        // Get data - ORDER BY timestamp DESC for latest first
        $dataQuery = "SELECT * FROM speedtest_users $dateCondition ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($dataQuery);
        if (!empty($ipFilter)) {
            $stmt->bindValue(':ip', "%$ipFilter%");
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get summary statistics
        $summaryQuery = "
            SELECT 
                COUNT(*) as total_tests,
                AVG(dl) as avg_download,
                AVG(ul) as avg_upload,
                AVG(ping) as avg_ping,
                AVG(jitter) as avg_jitter,
                AVG(COALESCE(packet_loss, 0)) as avg_packet_loss,
                AVG(COALESCE(latency, ping)) as avg_latency,
                MAX(dl) as max_download,
                MAX(ul) as max_upload,
                MIN(ping) as min_ping,
                COUNT(DISTINCT ip) as unique_ips,
                COUNT(DISTINCT account_name) as unique_accounts,
                COUNT(DISTINCT domain_id) as unique_domains
            FROM speedtest_users
            $dateCondition
        ";
        $summaryStmt = $conn->prepare($summaryQuery);
        if (!empty($ipFilter)) {
            $summaryStmt->bindValue(':ip', "%$ipFilter%");
        }
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $error = "Database connection failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../favicon.png" type="image/png">
    <title>Spectra Speed Test - Statistics Dashboard</title>
    <style>
        @font-face {
            font-family: 'Spectra';
            src: url('../Spectra-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #000000;
            color: #FFFFFF;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Header */
        .header {
            text-align: center;
            padding: 2rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
            font-family: 'Spectra', sans-serif;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1rem;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-family: sans-serif;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Enhanced Login Form */
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #000000;
            position: relative;
        }

        .login-wrapper::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 300px;
            background-image: url("../banner-element.png");
            background-repeat: no-repeat;
            background-position: top right;
            background-size: contain;
            z-index: 1;
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            padding: 0 1rem;
            position: relative;
            z-index: 10;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo h1 {
            font-family: 'Spectra', sans-serif;
            font-size: 2.5rem;
            font-weight: bold;
            letter-spacing: 4px;
            background: linear-gradient(135deg, #ffffff 0%, #a0a0a0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-logo p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #FFFFFF;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .submit-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #FFFFFF;
            border: none;
            padding: 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            position: relative;
            overflow: hidden;
        }

        .submit-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .submit-button:hover::before {
            left: 100%;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .error-message {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
        }

        /* Dashboard Controls */
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-button {
            padding: 0.5rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            color: #FFFFFF;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filter-button:hover, .filter-button.active {
            background: #FFFFFF;
            color: #000000;
        }

        .search-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .search-box {
            padding: 0.5rem 1.5rem;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            color: #FFFFFF;
            width: 250px;
            font-family: sans-serif;
        }

        .search-button {
            padding: 0.5rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            color: #FFFFFF;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .search-button:hover {
            background: #FFFFFF;
            color: #000000;
        }

        .logout-button {
            position: absolute;
            top: 2rem;
            right: 2rem;
            padding: 0.3rem 1rem;
            background: rgba(255, 0, 0, 0.1);
            border: 0.5px solid rgba(255, 0, 0, 0.3);
            color: #FF6B6B;
            border-radius: 18px;
            text-decoration: none;
            transition: all 0.3s ease;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .logout-button:hover {
            background: rgba(255, 0, 0, 0.2);
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
            justify-content: center;
        }

        .summary-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        /* Individual Gradient Backgrounds */
        .summary-card:nth-child(1) {
            background: linear-gradient(135deg, rgba(148, 101, 170, 0.2), rgba(148, 101, 170, 0.1));
            border-color: rgba(148, 101, 170, 0.3);
        }

        .summary-card:nth-child(2) {
            background: linear-gradient(135deg, rgba(74, 167, 189, 0.2), rgba(74, 167, 189, 0.1));
            border-color: rgba(74, 167, 189, 0.3);
        }

        .summary-card:nth-child(3) {
            background: linear-gradient(135deg, rgba(66, 192, 187, 0.2), rgba(66, 192, 187, 0.1));
            border-color: rgba(66, 192, 187, 0.3);
        }

        .summary-card:nth-child(4) {
            background: linear-gradient(135deg, rgba(91, 138, 195, 0.2), rgba(91, 138, 195, 0.1));
            border-color: rgba(91, 138, 195, 0.3);
        }

        .summary-card:nth-child(5) {
            background: linear-gradient(135deg, rgba(245, 107, 107, 0.2), rgba(245, 107, 107, 0.1));
            border-color: rgba(245, 107, 107, 0.3);
        }

        .summary-card:nth-child(6) {
            background: linear-gradient(135deg, rgba(249, 143, 110, 0.2), rgba(249, 143, 110, 0.1));
            border-color: rgba(249, 143, 110, 0.3);
        }

        .summary-card:nth-child(7) {
            background: linear-gradient(135deg, rgba(251, 183, 144, 0.2), rgba(251, 183, 144, 0.1));
            border-color: rgba(251, 183, 144, 0.3);
        }

        .summary-card:nth-child(8) {
            background: linear-gradient(135deg, rgba(254, 196, 109, 0.2), rgba(254, 196, 109, 0.1));
            border-color: rgba(254, 196, 109, 0.3);
        }

        .summary-card:nth-child(9) {
            background: linear-gradient(135deg, rgba(255, 162, 158, 0.2), rgba(255, 162, 158, 0.1));
            border-color: rgba(255, 162, 158, 0.3);
        }

        .summary-card:nth-child(10) {
            background: linear-gradient(135deg, rgba(181, 234, 215, 0.2), rgba(181, 234, 215, 0.1));
            border-color: rgba(181, 234, 215, 0.3);
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .summary-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: #FFFFFF;
        }

        .summary-unit {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Data Table */
        .table-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 1rem;
        }
        
        .table-wrapper::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        .data-table {
            width: 100%;
            min-width: 1800px;
            border-collapse: collapse;
        }

        .data-table th {
            background: rgba(0, 0, 0, 0.5);
            padding: 1rem 0.75rem;
            text-align: left;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .data-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .data-table th:first-child,
        .data-table td:first-child {
            position: sticky;
            left: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 5;
        }
        
        .data-table th:first-child {
            z-index: 11;
        }
        
        /* API Status Badge */
        .api-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .api-status.pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .api-status.completed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .api-status.failed {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .api-status.na {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .scroll-indicator {
            position: absolute;
            right: 2rem;
            top: 1rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .scroll-indicator::before {
            content: '←';
            animation: scroll-hint 1.5s ease-in-out infinite;
        }
        
        @keyframes scroll-hint {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-5px); }
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: #FFFFFF;
            text-decoration: none;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .page-link.active {
            background: #FFFFFF;
            color: #000000;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Export Form */
        .export-form {
            display: flex;
            align-items: flex-end;
            gap: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            border-radius: 16px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .export-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .export-form-group label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .export-input {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: sans-serif;
            transition: all 0.2s ease;
            min-width: 150px;
        }

        .export-input:hover {
            border-color: rgba(255, 255, 255, 0.5);
            background-color: rgba(0, 0, 0, 0.7);
        }

        .export-input:focus {
            outline: none;
            border-color: #ffffff;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .export-input[type="date"] {
            position: relative;
            color-scheme: dark;
            padding-right: 2.5rem;
        }

        .export-button {
            background: #FFFFFF;
            color: #000000;
            border: none;
            padding: 0.75rem 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-family: sans-serif;
            white-space: nowrap;
        }

        .export-button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
        }

        .export-input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: rgba(0, 0, 0, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .logout-button {
                position: static;
                display: block;
                text-align: center;
                margin: 1rem auto;
            }
        }
    </style>
</head>
<body>
    <?php if (!$authenticated): ?>
    <!-- Enhanced Login Form -->
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-card">
                <div class="login-logo">
                    <h1>SPECTRA</h1>
                    <p>Network Analytics Portal</p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Admin Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Enter password" required autofocus>
                    </div>
                    <button type="submit" class="submit-button">Sign In</button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Dashboard -->
    <div class="header">
        <div class="logo">SPECTRA</div>
        <div class="subtitle">Network Performance Analytics</div>
    </div>

    <a href="?logout=1" class="logout-button">Logout</a>
    
    <div class="container">
        <!-- Simplified Controls - IP Search Only -->
        <div class="controls">
            <div class="filters">
                <a href="?date=all" class="filter-button <?php echo $dateFilter == 'all' ? 'active' : ''; ?>">All Time</a>
                <a href="?date=today" class="filter-button <?php echo $dateFilter == 'today' ? 'active' : ''; ?>">Today</a>
                <a href="?date=week" class="filter-button <?php echo $dateFilter == 'week' ? 'active' : ''; ?>">7 Days</a>
                <a href="?date=month" class="filter-button <?php echo $dateFilter == 'month' ? 'active' : ''; ?>">30 Days</a>
            </div>
            <form method="GET" class="search-form">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                <input type="text" name="ip" placeholder="Search IP address..." class="search-box" value="<?php echo htmlspecialchars($ipFilter); ?>">
                <button type="submit" class="search-button">Search</button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Tests</div>
                <div class="summary-value"><?php echo number_format($summary['total_tests']); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Unique IPs</div>
                <div class="summary-value"><?php echo number_format($summary['unique_ips']); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Unique Accounts</div>
                <div class="summary-value"><?php echo number_format($summary['unique_accounts'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Unique Domains</div>
                <div class="summary-value"><?php echo number_format($summary['unique_domains'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Avg Download</div>
                <div class="summary-value"><?php echo number_format($summary['avg_download'], 1); ?></div>
                <div class="summary-unit">Mbps</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Avg Upload</div>
                <div class="summary-value"><?php echo number_format($summary['avg_upload'], 1); ?></div>
                <div class="summary-unit">Mbps</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Avg Ping</div>
                <div class="summary-value"><?php echo number_format($summary['avg_ping'], 1); ?></div>
                <div class="summary-unit">ms</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Avg Jitter</div>
                <div class="summary-value"><?php echo number_format($summary['avg_jitter'], 1); ?></div>
                <div class="summary-unit">ms</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Avg Packet Loss</div>
                <div class="summary-value"><?php echo number_format($summary['avg_packet_loss'], 2); ?></div>
                <div class="summary-unit">%</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Avg Latency</div>
                <div class="summary-value"><?php echo number_format($summary['avg_latency'], 1); ?></div>
                <div class="summary-unit">ms</div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-container">
            <div class="scroll-indicator">Scroll for more columns</div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Test ID</th>
                            <th>Date & Time</th>
                            <th>Customer IP</th>
                            <th>Download</th>
                            <th>Upload</th>
                            <th>Ping</th>
                            <th>Jitter</th>
                            <th>Packet Loss</th>
                            <th>Latency</th>
                            <th>Account Name</th>
                            <th>Service ID</th>
                            <th>Domain ID</th>
                            <th>Controller</th>
                            <th>MAC Address</th>
                            <th>SNAT IP</th>
                            <th>Service Plan</th>
                            <th>Bandwidth Policy</th>
                            <th>Address</th>
                            <th>City</th>
                            <th>API Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $row): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($row['timestamp'])); ?></td>
                            <td><?php echo htmlspecialchars($row['ip']); ?></td>
                            <td><?php echo number_format($row['dl'], 1); ?> Mbps</td>
                            <td><?php echo number_format($row['ul'], 1); ?> Mbps</td>
                            <td><?php echo number_format($row['ping'], 1); ?> ms</td>
                            <td><?php echo number_format($row['jitter'], 1); ?> ms</td>
                            <td><?php echo isset($row['packet_loss']) ? number_format($row['packet_loss'], 2) : '0.00'; ?>%</td>
                            <td><?php echo isset($row['latency']) ? number_format($row['latency'], 1) : number_format($row['ping'], 1); ?> ms</td>
                            <td><?php echo htmlspecialchars($row['account_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['service_group_id'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['domain_id'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['access_controller'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['mac_address'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['snat_ip'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['service_plan'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['bandwidth_policy_id'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['address'] ?? '-', 0, 30)); ?><?php echo (strlen($row['address'] ?? '') > 30) ? '...' : ''; ?></td>
                            <td><?php echo htmlspecialchars($row['city'] ?? '-'); ?></td>
                            <td>
                                <?php 
                                $status = $row['api_fetch_status'] ?? 'pending';
                                echo '<span class="api-status ' . $status . '">' . ucfirst($status) . '</span>';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?date=<?php echo $dateFilter; ?>&ip=<?php echo urlencode($ipFilter); ?>&page=1" class="page-link">First</a>
                <a href="?date=<?php echo $dateFilter; ?>&ip=<?php echo urlencode($ipFilter); ?>&page=<?php echo $page - 1; ?>" class="page-link"><</a>
            <?php else: ?>
                <span class="page-link disabled">First</span>
                <span class="page-link disabled"><</span>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <a href="?date=<?php echo $dateFilter; ?>&ip=<?php echo urlencode($ipFilter); ?>&page=<?php echo $i; ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?date=<?php echo $dateFilter; ?>&ip=<?php echo urlencode($ipFilter); ?>&page=<?php echo $page + 1; ?>" class="page-link">></a>
                <a href="?date=<?php echo $dateFilter; ?>&ip=<?php echo urlencode($ipFilter); ?>&page=<?php echo $totalPages; ?>" class="page-link">Last</a>
            <?php else: ?>
                <span class="page-link disabled">></span>
                <span class="page-link disabled">Last</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Export Section -->
    <div class="container" style="margin: 3rem auto;">
        <h6 style="margin-bottom: 1.5rem; text-align: center; letter-spacing: 2px;">Export Speed Test Results</h6>

        <form id="exportForm" action="export.php" method="get" class="export-form">
            <div class="export-form-group">
                <label for="from">From Date</label>
                <input type="date" name="from" id="from" class="export-input">
            </div>

            <div class="export-form-group">
                <label for="to">To Date</label>
                <input type="date" name="to" id="to" class="export-input">
            </div>

            <div class="export-form-group">
                <label for="limit">Or Select Count</label>
                <select name="limit" id="limit" class="export-input">
                    <option value="">-- No Limit --</option>
                    <option value="50">Last 50</option>
                    <option value="100">Last 100</option>
                    <option value="500">Last 500</option>
                    <option value="1000">Last 1000</option>
                </select>
            </div>
            
            <div class="export-form-group">
                <button type="submit" class="export-button">Export CSV</button>
            </div>
        </form>
    </div>

    <script>
        const fromInput = document.getElementById('from');
        const toInput = document.getElementById('to');
        const limitSelect = document.getElementById('limit');
        const form = document.getElementById('exportForm');

        // Disable logic
        function updateExportControls() {
            const hasDate = fromInput.value || toInput.value;
            const hasLimit = limitSelect.value !== "";

            if (hasLimit) {
                fromInput.disabled = true;
                toInput.disabled = true;
            } else {
                fromInput.disabled = false;
                toInput.disabled = false;
            }

            if (hasDate) {
                limitSelect.disabled = true;
            } else {
                limitSelect.disabled = false;
            }
        }

        fromInput.addEventListener('input', updateExportControls);
        toInput.addEventListener('input', updateExportControls);
        limitSelect.addEventListener('change', updateExportControls);

        // Confirm before export
        form.addEventListener('submit', function (e) {
            const confirmed = confirm("Are you sure you want to export the selected data?");
            if (!confirmed) {
                e.preventDefault();
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>