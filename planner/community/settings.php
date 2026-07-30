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

// Check if user is admin
if (!isCommunityAdmin($communityId, $userId)) {
    header('Location: view.php?id=' . $communityId);
    exit;
}

$error = '';
$success = '';
$districts = getDistricts();
$categories = getCategories();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'General';
        $district = $_POST['district'] ?? '';
        $type = $_POST['type'] ?? 'public';
        $tags = trim($_POST['tags'] ?? '');

        $data = [
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'district' => $district,
            'type' => $type,
            'tags' => $tags
        ];

        if (updateCommunity($communityId, $data)) {
            $success = 'Community updated successfully!';
            $community = getCommunity($communityId);
        } else {
            $error = 'Failed to update community';
        }
    } elseif ($action === 'delete') {
        if (deleteCommunity($communityId)) {
            header('Location: ' . SITE_URL . 'community/?deleted=1');
            exit;
        } else {
            $error = 'Failed to delete community';
        }
    } elseif ($action === 'update_member') {
        $memberId = $_POST['member_id'] ?? 0;
        $role = $_POST['role'] ?? 'member';

        $db = db();
        $stmt = $db->prepare("UPDATE community_members SET role = ? WHERE id = ? AND community_id = ?");
        if ($stmt->execute([$role, $memberId, $communityId])) {
            $success = 'Member role updated!';
        }
    } elseif ($action === 'remove_member') {
        $memberId = $_POST['member_id'] ?? 0;

        $db = db();
        $stmt = $db->prepare("DELETE FROM community_members WHERE id = ? AND community_id = ? AND role != 'admin'");
        if ($stmt->execute([$memberId, $communityId])) {
            $success = 'Member removed!';
            updateCommunityMemberCount($communityId);
        }
    }
}

// Get members
$members = getCommunityMembers($communityId, 50);

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-transparent border-0 pt-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="fw-bold mb-0">
                            <i class="bi bi-gear me-2"></i> Community Settings
                        </h4>
                        <a href="view.php?id=<?php echo $communityId; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                    </div>
                    <p class="text-secondary mb-0">Manage your community <?php echo htmlspecialchars($community['name']); ?></p>
                </div>

                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button">
                                <i class="bi bi-info-circle me-1"></i> General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#members" type="button">
                                <i class="bi bi-people me-1"></i> Members
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#danger" type="button">
                                <i class="bi bi-exclamation-triangle me-1"></i> Danger
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- General Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Community Name</label>
                                    <input type="text" class="form-control" name="name"
                                           value="<?php echo htmlspecialchars($community['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Description</label>
                                    <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($community['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Category</label>
                                        <select class="form-select" name="category">
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat; ?>" <?php echo $community['category'] === $cat ? 'selected' : ''; ?>>
                                                    <?php echo $cat; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">District</label>
                                        <select class="form-select" name="district">
                                            <option value="">None</option>
                                            <?php foreach ($districts as $district): ?>
                                                <option value="<?php echo $district; ?>" <?php echo $community['district'] === $district ? 'selected' : ''; ?>>
                                                    <?php echo $district; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Tags</label>
                                    <input type="text" class="form-control" name="tags"
                                           value="<?php echo htmlspecialchars($community['tags'] ?? ''); ?>"
                                           placeholder="e.g., study, students, motivation">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Community Type</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" value="public"
                                                   id="typePublic" <?php echo $community['type'] === 'public' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="typePublic">
                                                <i class="bi bi-globe text-success"></i> Public
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" value="private"
                                                   id="typePrivate" <?php echo $community['type'] === 'private' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="typePrivate">
                                                <i class="bi bi-lock text-warning"></i> Private
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" value="district"
                                                   id="typeDistrict" <?php echo $community['type'] === 'district' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="typeDistrict">
                                                <i class="bi bi-geo-alt text-info"></i> District
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Save Changes
                                </button>
                            </form>

                            <hr class="my-4">

                            <div class="bg-light p-3 rounded">
                                <h6 class="fw-bold"><i class="bi bi-link-45deg me-1"></i> Invite Link</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="inviteLink"
                                           value="<?php echo SITE_URL; ?>community/join.php?code=<?php echo $community['invite_code']; ?>" readonly>
                                    <button class="btn btn-primary" onclick="copyInviteLink()">
                                        <i class="bi bi-copy"></i> Copy
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="regenerateInvite()">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </div>
                                <small class="text-secondary">Share this link to invite people to your community</small>
                            </div>
                        </div>

                        <!-- Members Tab -->
                        <div class="tab-pane fade" id="members" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Role</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-2" style="width:32px;height:32px;border-radius:50%;background:var(--blue-lighter);display:flex;align-items:center;justify-content:center;font-weight:600;color:var(--blue);font-size:0.7rem;">
                                                            <?php echo strtoupper(substr($member['full_name'] ?? $member['username'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold small"><?php echo htmlspecialchars($member['full_name'] ?? $member['username']); ?></div>
                                                            <div class="text-secondary small"><?php echo htmlspecialchars($member['email']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($member['user_id'] == $community['created_by']): ?>
                                                        <span class="badge bg-warning text-dark">Owner</span>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_member">
                                                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                            <select name="role" class="form-select form-select-sm" style="width:auto;display:inline-block;" onchange="this.form.submit()">
                                                                <option value="admin" <?php echo $member['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                <option value="moderator" <?php echo $member['role'] === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                                                                <option value="member" <?php echo $member['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                                                            </select>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($member['joined_at'])); ?></td>
                                                <td>
                                                    <?php if ($member['user_id'] != $community['created_by']): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this member?')">
                                                            <input type="hidden" name="action" value="remove_member">
                                                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-person-x"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Danger Tab -->
                        <div class="tab-pane fade" id="danger" role="tabpanel">
                            <div class="alert alert-danger">
                                <h5 class="fw-bold"><i class="bi bi-exclamation-triangle me-2"></i> Danger Zone</h5>
                                <p>These actions cannot be undone. Please be careful.</p>
                            </div>

                            <div class="bg-light p-4 rounded">
                                <h6>Delete Community</h6>
                                <p class="text-secondary small">This will permanently delete the community and all its posts, comments, and member data.</p>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this community? This cannot be undone!')">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash3 me-1"></i> Delete Community
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyInviteLink() {
    const link = document.getElementById('inviteLink');
    link.select();
    document.execCommand('copy');
    alert('Invite link copied to clipboard!');
}

function regenerateInvite() {
    if (!confirm('Generate a new invite link? The old one will expire.')) return;

    fetch('ajax.php?action=generate_invite', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'community_id=<?php echo $communityId; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('inviteLink').value = data.link;
            alert('New invite link generated!');
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
