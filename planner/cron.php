<?php
/**
 * Cron Job Script - Run this via system cron daily
 * Example: 0 1 * * * php /path/to/cron.php
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "Running scheduled tasks...\n";

// Clean up expired tasks
echo "Cleaning expired tasks... ";
$result = cleanupExpiredTasks();
echo $result ? "Done\n" : "Failed\n";

// Clean up old activity logs
echo "Cleaning old activity logs... ";
$result = cleanupOldLogs();
echo $result ? "Done\n" : "Failed\n";

// Reset daily activity counts for all users
$db = db();
$today = date('Y-m-d');
$stmt = $db->prepare("UPDATE users SET daily_activity_count = 0 WHERE last_activity_date != ?");
$stmt->execute([$today]);
$count = $stmt->rowCount();
echo "Reset daily counts for $count users\n";

echo "All tasks completed.\n";
