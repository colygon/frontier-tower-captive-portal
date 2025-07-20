<?php
// Admin authentication check
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Check session timeout
if (isset($_SESSION['admin_login_time'])) {
    if (time() - $_SESSION['admin_login_time'] > ADMIN_SESSION_TIMEOUT) {
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
}

// Update last activity time
$_SESSION['admin_login_time'] = time();
?>
