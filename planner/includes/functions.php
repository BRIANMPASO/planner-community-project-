<?php
require_once 'db.php';

// Authentication functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function login($username, $password) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && verifyPassword($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['is_admin'] = $user['is_admin'];

        // Log the login
        logActivity($user['id'], 'login', 'User logged in');

        return true;
    }
    return false;
}

function logout() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    session_destroy();
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . 'login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL);
        exit;
    }
}

// User functions
function getUser($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return getUser($_SESSION['user_id']);
    }
    return null;
}

// Task functions
function getTasks($userId, $day = null, $status = 'active') {
    $db = db();
    $sql = "SELECT * FROM tasks WHERE user_id = ? AND status = ?";
    $params = [$userId, $status];

    if ($day) {
        $sql .= " AND task_day = ?";
        $params[] = $day;
    }

    $sql .= " ORDER BY created_at ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createTask($userId, $taskText, $day, $category = null, $priority = 'medium') {
    $db = db();

    // Check daily activity limit
    if (!canUserCreateTask($userId)) {
        return ['error' => 'Daily activity limit reached'];
    }

    // Calculate expiry date (90 days from now)
    $expiryDate = date('Y-m-d', strtotime('+90 days'));

    $stmt = $db->prepare("
        INSERT INTO tasks (user_id, task_text, task_day, category, priority, expiry_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$userId, $taskText, $day, $category, $priority, $expiryDate]);
    $taskId = $db->lastInsertId();

    // Update daily activity count
    updateDailyActivity($userId);

    // Log the activity
    logActivity($userId, 'task_created', "Task created: $taskText");

    return ['success' => true, 'task_id' => $taskId];
}

function updateTask($taskId, $data) {
    $db = db();
    $sets = [];
    $params = [];

    foreach ($data as $key => $value) {
        $sets[] = "$key = ?";
        $params[] = $value;
    }

    $params[] = $taskId;
    $sql = "UPDATE tasks SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

function deleteTask($taskId) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
    return $stmt->execute([$taskId]);
}

function completeTask($taskId) {
    $db = db();
    $stmt = $db->prepare("
        UPDATE tasks
        SET status = 'completed', completed_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$taskId]);
}

function renewTask($taskId) {
    $db = db();

    // Get task details
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task || $task['renewal_count'] >= $task['max_renewals']) {
        return ['error' => 'Cannot renew this task'];
    }

    // Update task
    $newExpiry = date('Y-m-d', strtotime($task['expiry_date'] . ' +30 days'));
    $stmt = $db->prepare("
        UPDATE tasks
        SET expiry_date = ?,
            renewal_count = renewal_count + 1,
            last_renewed_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([$newExpiry, $taskId]);

    // Log activity
    logActivity($task['user_id'], 'task_renewed', "Task renewed: {$task['task_text']}");

    return ['success' => true, 'new_expiry' => $newExpiry];
}

function canUserCreateTask($userId) {
    $db = db();
    $stmt = $db->prepare("
        SELECT daily_activity_count, last_activity_date
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $maxActivities = getSetting('max_daily_activities', 40);

    // Reset daily count if it's a new day
    $today = date('Y-m-d');
    if ($user['last_activity_date'] != $today) {
        $stmt = $db->prepare("UPDATE users SET daily_activity_count = 0, last_activity_date = ? WHERE id = ?");
        $stmt->execute([$today, $userId]);
        return true;
    }

    return $user['daily_activity_count'] < $maxActivities;
}

function updateDailyActivity($userId) {
    $db = db();
    $today = date('Y-m-d');

    $stmt = $db->prepare("
        UPDATE users
        SET daily_activity_count = daily_activity_count + 1,
            last_activity_date = ?
        WHERE id = ?
    ");
    return $stmt->execute([$today, $userId]);
}

// Activity logging
function logActivity($userId, $eventType, $details = null) {
    $db = db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, event_type, event_details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");

    return $stmt->execute([$userId, $eventType, $details, $ip, $userAgent]);
}

// Setting functions
function getSetting($key, $default = null) {
    $db = db();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();

    return $result ? $result['setting_value'] : $default;
}

function updateSetting($key, $value) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    return $stmt->execute([$key, $value, $value]);
}

// Dashboard statistics
function getDashboardStats() {
    $db = db();

    $stats = [];

    // Total users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['total_users'] = $stmt->fetch()['count'];

    // Total tasks
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'active'");
    $stats['active_tasks'] = $stmt->fetch()['count'];

    // Completed today
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed' AND DATE(completed_at) = CURDATE()");
    $stats['completed_today'] = $stmt->fetch()['count'];

    // Expired tasks
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'expired'");
    $stats['expired_tasks'] = $stmt->fetch()['count'];

    // Recent activity
    $stmt = $db->query("
        SELECT * FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stats['recent_activity'] = $stmt->fetchAll();

    return $stats;
}

// Clean up expired tasks
function cleanupExpiredTasks() {
    $db = db();

    // Get tasks expiring today
    $stmt = $db->prepare("
        UPDATE tasks
        SET status = 'expired'
        WHERE expiry_date < CURDATE() AND status = 'active'
    ");
    $stmt->execute();

    // Delete tasks that have been expired for more than 7 days
    $stmt = $db->prepare("
        DELETE FROM tasks
        WHERE status = 'expired' AND expiry_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    return $stmt->execute();
}

// Clean up old activity logs
function cleanupOldLogs() {
    $db = db();
    $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    return $stmt->execute();
}
