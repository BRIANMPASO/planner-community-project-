<?php
// Database configuration for InfinityFree
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// Site configuration - FORCE HTTPS
define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/planner/');
define('SITE_NAME', 'Make Your Plan Today');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . 'uploads/');

// Security
define('SALT', 'pamodzi_secure_salt_2024');
define('SESSION_TIMEOUT', 3600);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
