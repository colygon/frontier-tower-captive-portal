<?php
// Frontier Tower Captive Portal Configuration - SAMPLE
// Copy this file to config.php and update with your actual values

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'frontier_portal');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_secure_password');
define('DB_CHARSET', 'utf8mb4');

// UniFi Controller Configuration
define('UNIFI_HOST', 'https://your-unifi-controller.local:8443');
define('UNIFI_USER', 'your_unifi_admin');
define('UNIFI_PASS', 'your_unifi_password');
define('UNIFI_SITE', 'default');
define('UNIFI_VERSION', 'UDMP-unifiOS'); // or 'v4', 'v5', etc.

// Site Configuration
define('SITE_NAME', 'Your WiFi Network Name');
define('SITE_LOGO', '/assets/logo.png');
define('REDIRECT_URL', 'https://www.google.com'); // Default redirect after login
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Security Configuration
define('ADMIN_SESSION_TIMEOUT', 1800); // 30 minutes for admin
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'notifications@yourdomain.com');
define('SMTP_PASS', 'your-email-password');
define('FROM_EMAIL', 'noreply@yourdomain.com');
define('FROM_NAME', 'Your WiFi Network');

// Debug Mode (set to false in production)
define('DEBUG_MODE', false);

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('America/Los_Angeles');

// Database Connection Function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Database connection failed: " . $e->getMessage());
        } else {
            die("Database connection failed. Please try again later.");
        }
    }
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
session_start();
?>
