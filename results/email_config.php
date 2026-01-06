<?php
/**
 * email_config.php
 * Email configuration - edit this file with your SMTP details
 */

// Email addresses
define('EMAIL_FROM', 'adityaraj.shekhawat@spectra.co');
define('EMAIL_TO', 'adityaraj.shekhawat@spectra.co');
define('EMAIL_FROM_NAME', 'Spectra Speedtest System');

// SMTP Configuration
// Option 1: Gmail SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'adityaraj.shekhawat@spectra.co');
define('SMTP_PASSWORD', 'YOUR_APP_PASSWORD_HERE');  // Gmail App Password if 2FA enabled
define('SMTP_ENCRYPTION', 'tls');  // 'tls' or 'ssl'

// Option 2: Spectra SMTP (uncomment and use if you have corporate SMTP)
// define('SMTP_HOST', 'smtp.spectra.co');
// define('SMTP_PORT', 587);
// define('SMTP_USERNAME', 'adityaraj.shekhawat@spectra.co');
// define('SMTP_PASSWORD', 'YOUR_PASSWORD');
// define('SMTP_ENCRYPTION', 'tls');

// Paths
define('REPORTS_DIR', '/var/www/html/librespeed/reports');
define('LOGS_DIR', '/var/www/html/librespeed/logs');

// Create directories if they don't exist
if (!file_exists(REPORTS_DIR)) {
    mkdir(REPORTS_DIR, 0755, true);
}
if (!file_exists(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}
?>