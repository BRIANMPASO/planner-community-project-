<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/community-functions.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$communityId = $_GET['id'] ?? 0;
$community = getCommunity($communityId);

if (!$community) {
    header('Location: ' . SITE_URL . 'community/');
    exit;
}

$isMember = isCommunityMember($communityId, $userId);

if (!$isMember) {
    header('Location: view.php?id=' . $communityId);
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$members = getCommunityMembers($communityId, $limit, $offset);

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="card border-0 shadow-lg">
        <div class="card-header bg-transparent border-0 pt-4">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="fw-bold mb-0">
                    <i class="bi bi-people me-2"></i> Members of <?php echo htmlspecialchars($community['name']); ?>
                </h4>
                <a href="view.php?id=<?php echo $communityId; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row g-4">
                <?php foreach ($members as $member): ?>
                    <div class="col-md-3 col-6">
                        <div class="card border-0 shadow-sm text-center p-3">
                            <div class="avatar mx-auto" style="width:64px;height:64px;border-radius:50%;background:var(--blue-lighter);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:var(--blue);">
                                <?php echo strtoupper(substr($member['full_name'] ?? $member['username'], 0, 2)); ?>
                            </div>
                            <h6 class="fw-bold mt-2 mb-0"><?php echo htmlspecialchars($member['full_name'] ?? $member['username']); ?></h6>
                            <small class="text-secondary"><?php echo htmlspecialchars($member['email']); ?></small>
                            <div class="mt-2">
                                <?php if ($member['role'] === 'admin'): ?>
                                    <span class="badge bg-warning text-dark">Admin</span>
                                <?php elseif ($member['role'] === 'moderator'): ?>
                                    <span class="badge bg-info">Moderator</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Member</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-secondary mt-2">
                                Joined <?php echo date('M d, Y', strtotime($member['joined_at'])); ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
