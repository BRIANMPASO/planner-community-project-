<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check admin authentication
checkAdminAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$response = ['success' => false];

switch ($action) {
    case 'cleanup_tasks':
        $response['success'] = cleanupExpiredTasks();
        break;

    case 'cleanup_logs':
        $response['success'] = cleanupOldLogs();
        break;

    case 'clear_all':
        $db = db();
        try {
            $db->beginTransaction();
            $db->query("TRUNCATE TABLE activity_logs");
            $db->query("TRUNCATE TABLE tasks");
            $db->query("TRUNCATE TABLE users");
            $db->query("TRUNCATE TABLE referrals");
            $db->commit();
            $response['success'] = true;
        } catch (Exception $e) {
            $db->rollBack();
            $response['error'] = $e->getMessage();
        }
        break;

    default:
        $response['error'] = 'Invalid action';
}

echo json_encode($response);
?>
