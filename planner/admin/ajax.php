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
    case 'delete_community':
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $db = db();
            $stmt = $db->prepare("DELETE FROM communities WHERE id = ?");
            $response['success'] = $stmt->execute([$id]);
        }
        break;

    default:
        $response['error'] = 'Invalid action';
}

echo json_encode($response);
?>
