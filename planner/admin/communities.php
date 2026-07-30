<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/community-functions.php';

// Check admin authentication
checkAdminAuth();

$db = db();

// Get all communities
$stmt = $db->query("
    SELECT c.*, u.username as creator_name,
           (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) as member_count,
           (SELECT COUNT(*) FROM community_posts WHERE community_id = c.id) as post_count
    FROM communities c
    JOIN users u ON c.created_by = u.id
    ORDER BY c.created_at DESC
");
$communities = $stmt->fetchAll();

// Get stats
$stmt = $db->query("SELECT COUNT(*) as total FROM communities");
$totalCommunities = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM community_members");
$totalMembers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM community_posts");
$totalPosts = $stmt->fetch()['total'];

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold"><i class="bi bi-people me-2"></i> Communities</h1>
            <p class="text-secondary mb-0">Manage all communities on the platform</p>
        </div>
        <a href="<?php echo SITE_URL; ?>community/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Community
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-secondary">Total Communities</h6>
                    <h3 class="fw-bold"><?php echo $totalCommunities; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-secondary">Total Members</h6>
                    <h3 class="fw-bold"><?php echo $totalMembers; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-secondary">Total Posts</h6>
                    <h3 class="fw-bold"><?php echo $totalPosts; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Communities Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Community</th>
                            <th>Creator</th>
                            <th>Type</th>
                            <th>Members</th>
                            <th>Posts</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($communities as $community): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-2" style="width:36px;height:36px;border-radius:50%;background:var(--blue-lighter);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--blue);font-size:0.7rem;">
                                            <?php echo strtoupper(substr($community['name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($community['name']); ?></div>
                                            <?php if ($community['district']): ?>
                                                <small class="text-secondary">
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    <?php echo htmlspecialchars($community['district']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($community['creator_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $community['type'] === 'public' ? 'success' : ($community['type'] === 'private' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($community['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($community['member_count']); ?></td>
                                <td><?php echo number_format($community['post_count']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($community['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo SITE_URL; ?>community/view.php?id=<?php echo $community['id']; ?>"
                                           class="btn btn-outline-primary" target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>community/settings.php?id=<?php echo $community['id']; ?>"
                                           class="btn btn-outline-secondary">
                                            <i class="bi bi-gear"></i>
                                        </a>
                                        <button class="btn btn-outline-danger" onclick="deleteCommunity(<?php echo $community['id']; ?>)">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function deleteCommunity(id) {
    if (!confirm('Delete this community and all its data?')) return;

    fetch('<?php echo SITE_URL; ?>community/ajax.php?action=delete_community&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete community');
            }
        });
}
</script>

<?php include '../includes/footer.php'; ?>
