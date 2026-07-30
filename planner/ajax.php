<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$response = ['success' => false];

switch ($action) {
    case 'add_task':
        $taskText = $_POST['task_text'] ?? '';
        $day = $_POST['day'] ?? '';

        if (empty($taskText) || empty($day)) {
            $response['error'] = 'Task text and day are required';
        } else {
            $result = createTask($userId, $taskText, $day);
            $response = $result;
        }
        break;

    case 'complete_task':
        $taskId = $_POST['task_id'] ?? 0;
        if ($taskId) {
            $response['success'] = completeTask($taskId);
            if ($response['success']) {
                logActivity($userId, 'task_completed', "Task completed: ID $taskId");
            }
        }
        break;

    case 'delete_task':
        $taskId = $_POST['task_id'] ?? 0;
        if ($taskId) {
            $response['success'] = deleteTask($taskId);
            if ($response['success']) {
                logActivity($userId, 'task_deleted', "Task deleted: ID $taskId");
            }
        }
        break;

    case 'renew_task':
        $taskId = $_POST['task_id'] ?? 0;
        if ($taskId) {
            $result = renewTask($taskId);
            $response = $result;
        } else {
            $response['error'] = 'Task ID required';
        }
        break;

    default:
        $response['error'] = 'Invalid action';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
