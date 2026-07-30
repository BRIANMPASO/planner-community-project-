<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$user = getUser($userId);

$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
$tasks = [];
foreach ($days as $day) {
    $tasks[$day] = getTasks($userId, $day);
}

$db = db();
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_tasks
    FROM tasks
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

$stmt = $db->prepare("
    SELECT daily_activity_count, last_activity_date
    FROM users
    WHERE id = ?
");
$stmt->execute([$userId]);
$activity = $stmt->fetch();

$maxActivities = getSetting('max_daily_activities', 40);
$remainingActivities = max(0, $maxActivities - $activity['daily_activity_count']);
$today = date('Y-m-d');
$isNewDay = ($activity['last_activity_date'] != $today);

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="content">
            <div>
                <h2>Welcome back, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>!</h2>
                <p><i class="bi bi-calendar3 me-1"></i> <?php echo date('l, F d, Y'); ?></p>
            </div>
            <div class="activity-counter">
                <span class="number"><?php echo $isNewDay ? 0 : $activity['daily_activity_count']; ?></span>
                <span class="label">/ <?php echo $maxActivities; ?> Today</span>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon blue"><i class="bi bi-list-check"></i></div>
                <span class="number"><?php echo $stats['total_tasks'] ?? 0; ?></span>
                <span class="label">Total Tasks</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon green"><i class="bi bi-check-circle"></i></div>
                <span class="number" style="color: #22c55e;"><?php echo $stats['completed_tasks'] ?? 0; ?></span>
                <span class="label">Completed</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon red"><i class="bi bi-clock"></i></div>
                <span class="number" style="color: #dc3545;"><?php echo $stats['expired_tasks'] ?? 0; ?></span>
                <span class="label">Expired</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon purple"><i class="bi bi-arrow-repeat"></i></div>
                <span class="number" style="color: #8b5cf6;"><?php echo $isNewDay ? $maxActivities : $remainingActivities; ?></span>
                <span class="label">Remaining Today</span>
            </div>
        </div>
    </div>

    <!-- Task Board -->
    <div class="row g-3">
        <?php foreach ($days as $day): ?>
            <div class="col-xl-4 col-lg-6">
                <div class="day-card">
                    <div class="card-header">
                        <h6><i class="bi bi-calendar2-week"></i> <?php echo $day; ?></h6>
                        <span class="badge"><?php echo count($tasks[$day] ?? []); ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks[$day])): ?>
                            <div class="empty-tasks">
                                <i class="bi bi-plus-circle"></i>
                                <p>No tasks yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tasks[$day] as $task): ?>
                                <div class="task-item" data-task="<?php echo $task['id']; ?>">
                                    <div class="task-check <?php echo $task['status'] === 'completed' ? 'completed' : ''; ?>"
                                         onclick="toggleTask(<?php echo $task['id']; ?>, this)">
                                        <?php if ($task['status'] === 'completed'): ?>
                                            <i class="bi bi-check"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="task-text <?php echo $task['status'] === 'completed' ? 'completed-text' : ''; ?>">
                                        <?php echo htmlspecialchars($task['task_text']); ?>
                                    </span>
                                    <div class="task-meta">
                                        <?php if ($task['priority'] !== 'medium'): ?>
                                            <span class="badge priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php
                                        $daysRemaining = (strtotime($task['expiry_date']) - time()) / 86400;
                                        if ($daysRemaining <= 0): ?>
                                            <span class="badge expired">Expired</span>
                                        <?php elseif ($daysRemaining < 7): ?>
                                            <span class="badge expiring"><?php echo ceil($daysRemaining); ?>d left</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="task-actions">
                                        <?php if ($task['status'] !== 'completed'): ?>
                                            <button class="btn btn-complete" onclick="completeTask(<?php echo $task['id']; ?>, this)" title="Complete">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-renew" onclick="renewTask(<?php echo $task['id']; ?>, this)"
                                                <?php echo ($task['renewal_count'] >= $task['max_renewals'] || $task['status'] === 'expired') ? 'disabled' : ''; ?>
                                                title="Renew for 30 more days">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <button class="btn btn-delete" onclick="deleteTask(<?php echo $task['id']; ?>, this)" title="Delete">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3 px-3">
                        <form class="add-task-form" onsubmit="return addTask(this, '<?php echo $day; ?>')">
                            <div class="input-group-custom">
                                <input type="text" class="form-control" placeholder="Add a task..."
                                       maxlength="60" <?php echo ($remainingActivities <= 0 && !$isNewDay) ? 'disabled' : ''; ?>>
                                <button type="submit" class="btn-add" <?php echo ($remainingActivities <= 0 && !$isNewDay) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            </div>
                            <?php if ($remainingActivities <= 0 && !$isNewDay): ?>
                                <small class="limit-message">Daily limit reached. Come back tomorrow!</small>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// ===== TASK FUNCTIONS =====

function addTask(form, day) {
    const input = form.querySelector('input');
    const taskText = input.value.trim();

    if (!taskText) {
        alert('Please enter a task.');
        return false;
    }

    const btn = form.querySelector('.btn-add');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    fetch('ajax.php?action=add_task', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'task_text=' + encodeURIComponent(taskText) + '&day=' + day
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Error adding task');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add';
    });

    return false;
}

function completeTask(taskId, btn) {
    if (!confirm('Mark this task as completed?')) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('ajax.php?action=complete_task', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'task_id=' + taskId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error completing task');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        }
    });
}

function renewTask(taskId, btn) {
    if (!confirm('Renew this task for 30 more days?')) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('ajax.php?action=renew_task', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'task_id=' + taskId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Cannot renew this task');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
        }
    });
}

function deleteTask(taskId, btn) {
    if (!confirm('Delete this task?')) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('ajax.php?action=delete_task', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'task_id=' + taskId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error deleting task');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash3"></i>';
        }
    });
}

function toggleTask(taskId, el) {
    const isCompleted = el.classList.contains('completed');
    if (!isCompleted) {
        completeTask(taskId, el);
    }
}

// Enter key support for task input
document.querySelectorAll('.add-task-form input').forEach(input => {
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.closest('.add-task-form').querySelector('.btn-add').click();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
