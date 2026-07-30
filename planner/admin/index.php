<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/community-functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

$limit = 12;
$offset = ($page - 1) * $limit;

// Get communities
if ($search) {
    $communities = searchCommunities($search, $limit, $offset);
    $totalCommunities = count($communities);
} else {
    $communities = getCommunities($limit, $offset, $type);
    $totalCommunities = count($communities);
}

// Get trending communities
$trending = getTrendingCommunities(4);

// Get user's communities
$userCommunities = [];
$db = db();
$stmt = $db->prepare("
    SELECT c.*, cm.role
    FROM communities c
    JOIN community_members cm ON c.id = cm.community_id
    WHERE cm.user_id = ? AND c.is_active = 1
    ORDER BY cm.joined_at DESC
");
$stmt->execute([$userId]);
$userCommunities = $stmt->fetchAll();

// Get notification count
$unreadNotifications = getUnreadNotifications($userId);

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">Community Hub</h1>
                    <p class="text-secondary mb-0">Connect, share, and grow together</p>
                </div>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Create Community
                </a>
            </div>

            <!-- Search & Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="search"
                                       placeholder="Search communities..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="type" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="public" <?php echo $type === 'public' ? 'selected' : ''; ?>>Public</option>
                                <option value="private" <?php echo $type === 'private' ? 'selected' : ''; ?>>Private</option>
                                <option value="district" <?php echo $type === 'district' ? 'selected' : ''; ?>>District</option>
                            </select>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="<?php echo SITE_URL; ?>community/" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Trending Communities -->
            <?php if (!empty($trending) && !$search): ?>
            <div class="mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-fire me-1" style="color: #f59e0b;"></i> Trending Communities</h5>
                <div class="row g-3">
                    <?php foreach ($trending as $community): ?>
                        <div class="col-md-3 col-6">
                            <div class="card border-0 shadow-sm h-100 text-center">
                                <div class="card-body p-3">
                                    <div class="community-avatar mx-auto mb-2" style="width:60px;height:60px;border-radius:50%;background:var(--blue-lighter);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:var(--blue);">
                                        <?php echo strtoupper(substr($community['name'], 0, 2)); ?>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($community['name']); ?></h6>
                                    <small class="text-secondary"><?php echo number_format(getFakeMemberCount($community['id'])); ?> members</small>
                                    <br>
                                    <a href="view.php?slug=<?php echo $community['slug']; ?>" class="btn btn-sm btn-outline-primary mt-2">View</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- My Communities -->
            <?php if (!empty($userCommunities) && !$search): ?>
            <div class="mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-people me-1"></i> Your Communities</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($userCommunities as $community): ?>
                        <a href="view.php?slug=<?php echo $community['slug']; ?>" class="badge bg-primary bg-opacity-10 text-primary p-2 text-decoration-none">
                            <?php echo htmlspecialchars($community['name']); ?>
                            <?php if ($community['role'] === 'admin'): ?>
                                <span class="badge bg-warning text-dark ms-1">Admin</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Community Grid -->
            <?php if (empty($communities)): ?>
                <div class="card border-0 shadow-sm text-center p-5">
                    <i class="bi bi-people display-1 text-secondary opacity-25"></i>
                    <h4 class="mt-3">No communities found</h4>
                    <p class="text-secondary">Be the first to create a community!</p>
                    <div>
                        <a href="create.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Create Community
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4" id="communityGrid">
                    <?php foreach ($communities as $community): ?>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100 community-card">
                                <?php if ($community['image']): ?>
                                    <img src="<?php echo UPLOAD_URL . $community['image']; ?>"
                                         class="card-img-top" alt="<?php echo htmlspecialchars($community['name']); ?>"
                                         style="height:160px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="card-img-top bg-gradient-primary text-white d-flex align-items-center justify-content-center"
                                         style="height:160px;font-size:3rem;font-weight:700;">
                                        <?php echo strtoupper(substr($community['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title fw-bold mb-0"><?php echo htmlspecialchars($community['name']); ?></h5>
                                        <span class="badge bg-<?php echo $community['type'] === 'public' ? 'success' : ($community['type'] === 'private' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($community['type']); ?>
                                        </span>
                                    </div>

                                    <p class="card-text text-secondary small">
                                        <?php echo htmlspecialchars(substr($community['description'] ?? '', 0, 100)); ?>
                                    </p>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-secondary small">
                                                <i class="bi bi-people me-1"></i>
                                                <?php echo number_format(getFakeMemberCount($community['id'])); ?> members
                                            </span>
                                            <?php if (isCommunityMember($community['id'], $userId)): ?>
                                                <span class="badge bg-success ms-1">Member</span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="view.php?slug=<?php echo $community['slug']; ?>" class="btn btn-sm btn-primary">
                                            <?php echo isCommunityMember($community['id'], $userId) ? 'Enter' : 'Join'; ?>
                                        </a>
                                    </div>

                                    <?php if ($community['district']): ?>
                                        <div class="mt-2">
                                            <small class="text-secondary">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                <?php echo htmlspecialchars($community['district']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Load More -->
                <?php if (count($communities) >= $limit): ?>
                    <div class="text-center mt-4" id="loadMoreContainer">
                        <button class="btn btn-outline-primary" id="loadMoreBtn" onclick="loadMoreCommunities()">
                            <i class="bi bi-arrow-down me-1"></i> Load More
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="create.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> New Community
                        </a>
                        <button class="btn btn-outline-primary btn-sm" onclick="showInviteModal()">
                            <i class="bi bi-envelope me-1"></i> Invite Friends
                        </button>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Categories</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <?php
                        $categories = getCategories();
                        foreach ($categories as $cat):
                        ?>
                            <a href="?search=<?php echo urlencode($cat); ?>" class="badge bg-light text-dark text-decoration-none p-2">
                                <?php echo htmlspecialchars($cat); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Districts -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Districts</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <?php
                        $districts = getDistricts();
                        foreach ($districts as $district):
                        ?>
                            <a href="?search=<?php echo urlencode($district); ?>" class="badge bg-light text-dark text-decoration-none p-2">
                                <?php echo htmlspecialchars($district); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Community Stats</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Total Communities</span>
                        <span class="fw-bold"><?php
                            $stmt = $db->query("SELECT COUNT(*) as count FROM communities WHERE is_active = 1");
                            echo $stmt->fetch()['count'] ?? 0;
                        ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Total Members</span>
                        <span class="fw-bold"><?php
                            $stmt = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM community_members");
                            echo $stmt->fetch()['count'] ?? 0;
                        ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Total Posts</span>
                        <span class="fw-bold"><?php
                            $stmt = $db->query("SELECT COUNT(*) as count FROM community_posts");
                            echo $stmt->fetch()['count'] ?? 0;
                        ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invite Modal -->
<div class="modal fade" id="inviteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Invite to Community</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary">Invite friends to join your communities</p>
                <form id="inviteForm">
                    <div class="mb-3">
                        <label class="form-label">Select Community</label>
                        <select class="form-select" id="inviteCommunity" required>
                            <option value="">Choose a community...</option>
                            <?php foreach ($userCommunities as $community): ?>
                                <option value="<?php echo $community['id']; ?>">
                                    <?php echo htmlspecialchars($community['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Addresses (one per line)</label>
                        <textarea class="form-control" id="inviteEmails" rows="4" placeholder="friend@email.com&#10;another@email.com" required></textarea>
                        <small class="text-secondary">Max 100 emails</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> Send Invites
                    </button>
                </form>
                <hr>
                <div class="text-center">
                    <p class="text-secondary mb-1">Or share invite link</p>
                    <button class="btn btn-outline-secondary btn-sm" onclick="generateInviteLink()">
                        <i class="bi bi-link-45deg me-1"></i> Generate Link
                    </button>
                    <div id="inviteLinkContainer" class="mt-2" style="display:none;">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="inviteLink" readonly>
                            <button class="btn btn-primary btn-sm" onclick="copyInviteLink()">
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load more communities
let currentPage = <?php echo $page; ?>;
let loading = false;
let hasMore = true;

function loadMoreCommunities() {
    if (loading || !hasMore) return;

    loading = true;
    currentPage++;

    const btn = document.getElementById('loadMoreBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';

    const search = '<?php echo addslashes($search); ?>';
    const type = '<?php echo addslashes($type); ?>';

    fetch(`ajax.php?action=get_communities&page=${currentPage}&search=${search}&type=${type}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.html) {
                document.getElementById('communityGrid').insertAdjacentHTML('beforeend', data.html);
                hasMore = data.hasMore;
                if (!hasMore) {
                    document.getElementById('loadMoreContainer').style.display = 'none';
                }
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-down me-1"></i> Load More';
            loading = false;
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-down me-1"></i> Load More';
            loading = false;
        });
}

// Invite modal
function showInviteModal() {
    const modal = new bootstrap.Modal(document.getElementById('inviteModal'));
    modal.show();
}

document.getElementById('inviteForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const communityId = document.getElementById('inviteCommunity').value;
    const emails = document.getElementById('inviteEmails').value;

    if (!communityId) {
        alert('Please select a community');
        return;
    }

    if (!emails.trim()) {
        alert('Please enter at least one email');
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

    fetch('ajax.php?action=bulk_invite', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'community_id=' + communityId + '&emails=' + encodeURIComponent(emails)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Invites sent successfully!');
            document.getElementById('inviteEmails').value = '';
            const modal = bootstrap.Modal.getInstance(document.getElementById('inviteModal'));
            modal.hide();
        } else {
            alert(data.error || 'Failed to send invites');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Send Invites';
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Send Invites';
    });
});

function generateInviteLink() {
    const communityId = document.getElementById('inviteCommunity').value;
    if (!communityId) {
        alert('Please select a community first');
        return;
    }

    fetch('ajax.php?action=generate_invite', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'community_id=' + communityId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('inviteLink').value = data.link;
            document.getElementById('inviteLinkContainer').style.display = 'block';
            document.getElementById('inviteLinkContainer').scrollIntoView({ behavior: 'smooth' });
        } else {
            alert(data.error || 'Failed to generate link');
        }
    });
}

function copyInviteLink() {
    const link = document.getElementById('inviteLink');
    link.select();
    document.execCommand('copy');
    alert('Invite link copied to clipboard!');
}

// Auto-refresh notifications count
function refreshNotificationCount() {
    fetch('ajax.php?action=get_notification_count')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            if (badge && data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'inline';
            } else if (badge) {
                badge.style.display = 'none';
            }
        });
}

setInterval(refreshNotificationCount, 30000);
</script>

<style>
.community-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.community-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md) !important;
}
.bg-gradient-primary {
    background: linear-gradient(135deg, #0B2647 0%, #2D7BDE 100%);
}
</style>

<?php include '../includes/footer.php'; ?>
