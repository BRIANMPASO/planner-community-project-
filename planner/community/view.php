<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/community-functions.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$slug = $_GET['slug'] ?? $_GET['id'] ?? '';
$community = null;

if (is_numeric($slug)) {
    $community = getCommunity($slug);
} else {
    $community = getCommunityBySlug($slug);
}

if (!$community) {
    header('Location: ' . SITE_URL . 'community/');
    exit;
}

$communityId = $community['id'];
$isMember = isCommunityMember($communityId, $userId);
$isAdmin = isCommunityAdmin($communityId, $userId);

// Check if private community
if ($community['type'] === 'private' && !$isMember) {
    $inviteCode = $_GET['invite'] ?? '';
    if ($inviteCode) {
        $invite = validateInvite($inviteCode);
        if ($invite && $invite['community_id'] == $communityId) {
            // Valid invite, allow viewing
        } else {
            header('Location: ' . SITE_URL . 'community/');
            exit;
        }
    } else {
        header('Location: ' . SITE_URL . 'community/');
        exit;
    }
}

// Get posts
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$posts = getCommunityPosts($communityId, $limit, $offset);

// Get members
$members = getCommunityMembers($communityId, 9);

// Get tasks
$tasks = getCommunityTasks($communityId);

// Get activity
$activity = getCommunityActivity($communityId);

// Get real vs fake member count
$memberCount = getFakeMemberCount($communityId);
$realMemberCount = getMemberCount($communityId, true);

// Mark page as community page for navbar fix
$isCommunityPage = true;

include '../includes/header.php';
?>

<style>
/* ===== FIX: NAVBAR OVERLAP ===== */
.community-page-wrapper {
    padding-top: 130px !important;
}

.back-to-tasks {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    margin-top: 12px !important;
}

.back-to-tasks:hover {
    background: #e9ecef;
    color: #0B2647;
    transform: translateX(-2px);
}

/* Modern Card Styling */
.modern-card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
    padding: 1.5rem;
}

.modern-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.10);
    transform: translateY(-2px);
}

/* Community Header */
.community-header {
    background: linear-gradient(135deg, #0B2647 0%, #1a4a7a 50%, #2D7BDE 100%);
    border-radius: 16px;
    padding: 2rem 2.5rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 8px 30px rgba(11, 38, 71, 0.20);
    position: relative;
    overflow: hidden;
}

.community-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}

.community-header::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
}

.community-header .content {
    position: relative;
    z-index: 1;
}

.community-header h1 {
    font-weight: 800;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    color: white !important;
}

.community-header .opacity-75 {
    opacity: 0.9 !important;
    color: rgba(255, 255, 255, 0.95) !important;
}

.community-header .badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.community-header p {
    color: rgba(255, 255, 255, 0.9) !important;
}

.community-header .btn-light {
    background: rgba(255, 255, 255, 0.9);
    color: #0B2647;
    border: none;
    font-weight: 600;
}

.community-header .btn-light:hover {
    background: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.community-header .btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.6);
    color: white;
    font-weight: 600;
}

.community-header .btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: white;
}

/* Stats Mini Cards */
.stats-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-mini-card {
    background: white;
    border-radius: 12px;
    padding: 1.2rem;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
}

.stat-mini-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
}

.stat-mini-card .number {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0B2647;
    display: block;
}

.stat-mini-card .label {
    font-size: 0.75rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Post Cards */
.post-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    margin-bottom: 1.2rem;
    transition: all 0.3s ease;
    overflow: hidden;
}

.post-card:hover {
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.10);
}

.post-card .post-header {
    padding: 1rem 1.2rem;
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    border-bottom: 1px solid #f0f0f0;
}

.post-card .post-header .avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2D7BDE, #6BA6FF);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.post-card .post-header .user-info .name {
    font-weight: 600;
    font-size: 0.95rem;
    color: #0B2647;
}

.post-card .post-header .user-info .time {
    font-size: 0.75rem;
    color: #6c757d;
}

.post-card .post-body {
    padding: 1rem 1.2rem;
}

.post-card .post-body .content {
    font-size: 0.95rem;
    line-height: 1.6;
    color: #1a1a1a;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.post-card .post-footer {
    padding: 0.6rem 1.2rem;
    border-top: 1px solid #f0f0f0;
    display: flex;
    gap: 1.2rem;
}

.post-card .post-footer .action-btn {
    background: none;
    border: none;
    color: #6c757d;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
}

.post-card .post-footer .action-btn:hover {
    background: #f0f4f8;
    color: #2D7BDE;
}

.post-card .post-footer .action-btn.liked {
    color: #e74c3c;
}

/* Comments */
.comments-section {
    background: #f8f9fa;
    padding: 1rem 1.2rem;
    border-top: 1px solid #e9ecef;
}

.comments-section .comment-item {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 0.8rem;
    padding: 0.6rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.comments-section .comment-item .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2D7BDE, #6BA6FF);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
    font-size: 0.7rem;
    flex-shrink: 0;
}

.comments-section .comment-item .comment-body .name {
    font-weight: 600;
    font-size: 0.8rem;
    color: #0B2647;
}

.comments-section .comment-item .comment-body .text {
    font-size: 0.85rem;
    color: #333;
}

.comments-section .comment-item .comment-body .time {
    font-size: 0.65rem;
    color: #6c757d;
}

.comment-input-group {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.8rem;
}

.comment-input-group input {
    flex: 1;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    padding: 0.6rem 1rem;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.comment-input-group input:focus {
    outline: none;
    border-color: #2D7BDE;
    box-shadow: 0 0 0 3px rgba(45, 123, 222, 0.1);
}

.comment-input-group button {
    background: #2D7BDE;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.comment-input-group button:hover {
    background: #1a5bbf;
    transform: scale(1.05);
}

/* Create Post Box */
.create-post-box {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    padding: 1.2rem;
    margin-bottom: 1.5rem;
}

.create-post-box textarea {
    width: 100%;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 0.8rem 1rem;
    font-size: 0.9rem;
    resize: none;
    min-height: 80px;
    transition: all 0.3s ease;
    font-family: inherit;
}

.create-post-box textarea:focus {
    outline: none;
    border-color: #2D7BDE;
    box-shadow: 0 0 0 3px rgba(45, 123, 222, 0.1);
}

.create-post-box .post-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.8rem;
}

.create-post-box .post-actions .btn-post {
    background: #2D7BDE;
    color: white;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.create-post-box .post-actions .btn-post:hover {
    background: #1a5bbf;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(45, 123, 222, 0.3);
}

/* Sidebar cards */
.sidebar-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    padding: 1.2rem;
    margin-bottom: 1.2rem;
}

.sidebar-card h6 {
    font-weight: 700;
    color: #0B2647;
    margin-bottom: 0.8rem;
    font-size: 0.9rem;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 0.6rem;
}

/* Member Avatars */
.member-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2D7BDE, #6BA6FF);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.member-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(45, 123, 222, 0.3);
}

/* Mobile responsive */
@media (max-width: 768px) {
    .community-page-wrapper {
        padding-top: 80px !important;
    }
    .community-header {
        padding: 1.2rem;
    }
    .community-header h1 {
        font-size: 1.4rem;
    }
    .stats-mini {
        grid-template-columns: repeat(2, 1fr);
    }
    .post-card .post-header {
        padding: 0.8rem 1rem;
    }
    .post-card .post-body {
        padding: 0.8rem 1rem;
    }
    .post-card .post-footer {
        padding: 0.5rem 1rem;
        flex-wrap: wrap;
    }
}

@media (max-width: 576px) {
    .community-header {
        padding: 1rem;
    }
    .community-header h1 {
        font-size: 1.2rem;
    }
    .stats-mini {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    .stat-mini-card {
        padding: 0.8rem;
    }
    .stat-mini-card .number {
        font-size: 1.4rem;
    }
    .post-card .post-header {
        flex-wrap: wrap;
    }
    .post-card .post-body .content {
        font-size: 0.85rem;
    }
    .comment-input-group {
        flex-wrap: wrap;
    }
    .comment-input-group input {
        min-width: 100px;
    }
}

/* Dark mode support (unchanged) */
body.dark-mode .modern-card {
    background: #252540;
    border-color: rgba(255, 255, 255, 0.05);
}
body.dark-mode .stat-mini-card {
    background: #252540;
    border-color: rgba(255, 255, 255, 0.05);
}
body.dark-mode .stat-mini-card .number {
    color: #e0e0e0;
}
body.dark-mode .post-card {
    background: #252540;
    border-color: rgba(255, 255, 255, 0.05);
}
body.dark-mode .post-card .post-header {
    border-bottom-color: rgba(255, 255, 255, 0.05);
}
body.dark-mode .post-card .post-body .content {
    color: #e0e0e0;
}
body.dark-mode .post-card .post-footer {
    border-top-color: rgba(255, 255, 255, 0.05);
}
body.dark-mode .comments-section {
    background: #1a1a2e;
}
body.dark-mode .comments-section .comment-item {
    background: #252540;
}
body.dark-mode .sidebar-card {
    background: #252540;
    border-color: rgba(255, 255, 255, 0.05);
}
body.dark-mode .sidebar-card h6 {
    border-bottom-color: rgba(255, 255, 255, 0.05);
    color: #e0e0e0;
}
body.dark-mode .create-post-box {
    background: #252540;
    border-color: rgba(255, 255, 255, 0.05);
}
body.dark-mode .create-post-box textarea {
    background: #2d2d4a;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e0e0e0;
}
body.dark-mode .back-to-tasks {
    background: #252540;
    border-color: rgba(255, 255, 255, 0.05);
    color: #a0a0b0;
}
body.dark-mode .back-to-tasks:hover {
    background: #2d2d4a;
    color: #e0e0e0;
}
</style>

<div class="container-fluid py-4 community-page-wrapper">
    <!-- Back to Tasks Button -->
    <div class="mb-3">
        <a href="<?php echo SITE_URL; ?>" class="back-to-tasks">
            <i class="bi bi-arrow-left"></i> Back to My Tasks
        </a>
    </div>

    <!-- Community Header -->
    <div class="community-header">
        <div class="content">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="member-avatar me-3" style="width:72px;height:72px;font-size:2rem;background:rgba(255,255,255,0.2) !important;border:2px solid rgba(255,255,255,0.3);">
                            <?php echo strtoupper(substr($community['name'], 0, 2)); ?>
                        </div>
                        <div>
                            <h1 class="h3 fw-bold mb-0"><?php echo htmlspecialchars($community['name']); ?></h1>
                            <div class="d-flex gap-3 flex-wrap mt-1">
                                <span class="opacity-75">
                                    <i class="bi bi-people me-1"></i>
                                    <?php echo number_format($memberCount); ?> members
                                </span>
                                <span class="opacity-75">
                                    <i class="bi bi-chat me-1"></i>
                                    <?php echo $community['post_count']; ?> posts
                                </span>
                                <?php if ($community['district']): ?>
                                    <span class="opacity-75">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?php echo htmlspecialchars($community['district']); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="badge">
                                    <?php echo ucfirst($community['type']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <?php if ($isMember): ?>
                        <button class="btn btn-outline-light btn-sm" onclick="leaveCommunity(<?php echo $communityId; ?>)">
                            <i class="bi bi-box-arrow-right me-1"></i> Leave
                        </button>
                        <?php if ($isAdmin): ?>
                            <a href="settings.php?id=<?php echo $communityId; ?>" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-gear me-1"></i> Settings
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-light" onclick="joinCommunity(<?php echo $communityId; ?>)">
                            <i class="bi bi-person-plus me-1"></i> Join Community
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3">
                <p class="mb-0 opacity-75" style="color: rgba(255,255,255,0.9) !important;"><?php echo nl2br(htmlspecialchars($community['description'] ?? '')); ?></p>
                <?php if ($community['tags']): ?>
                    <div class="mt-2">
                        <?php foreach (explode(',', $community['tags']) as $tag): ?>
                            <span class="badge bg-light text-dark me-1 opacity-75" style="background: rgba(255,255,255,0.2) !important;color: white !important;">#<?php echo trim(htmlspecialchars($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Community Stats -->
    <?php if ($isMember): ?>
    <div class="stats-mini">
        <div class="stat-mini-card">
            <span class="number"><?php echo $activity['posts_24h'] ?? 0; ?></span>
            <span class="label">Posts 24h</span>
        </div>
        <div class="stat-mini-card">
            <span class="number"><?php echo $activity['posts_7d'] ?? 0; ?></span>
            <span class="label">Posts This Week</span>
        </div>
        <div class="stat-mini-card">
            <span class="number"><?php echo $activity['new_members_7d'] ?? 0; ?></span>
            <span class="label">New Members</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Feed -->
        <div class="col-lg-8">
            <?php if ($isMember): ?>
                <!-- Create Post Box -->
                <div class="create-post-box">
                    <form id="postForm" onsubmit="return createPost(this)">
                        <textarea id="postContent" rows="3" placeholder="Share something with the community..." required></textarea>
                        <div class="post-actions">
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="togglePostType()">
                                    <i class="bi bi-type"></i> <span id="postTypeLabel">Text</span>
                                </button>
                                <input type="hidden" id="postType" value="text">
                            </div>
                            <button type="submit" class="btn-post">
                                <i class="bi bi-send me-1"></i> Post
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="modern-card text-center p-4 mb-4">
                    <i class="bi bi-lock display-4 text-secondary opacity-25"></i>
                    <h5 class="mt-3">Join to Participate</h5>
                    <p class="text-secondary">Join this community to see posts and contribute</p>
                    <button class="btn btn-primary" onclick="joinCommunity(<?php echo $communityId; ?>)">
                        <i class="bi bi-person-plus me-1"></i> Join Now
                    </button>
                </div>
            <?php endif; ?>

            <!-- Posts Feed -->
            <div id="postsFeed">
                <?php if (empty($posts)): ?>
                    <div class="modern-card text-center p-5">
                        <i class="bi bi-chat display-1 text-secondary opacity-25"></i>
                        <h5 class="mt-3">No posts yet</h5>
                        <p class="text-secondary">Be the first to post in this community!</p>
                        <?php if ($isMember): ?>
                            <button class="btn btn-primary" onclick="document.getElementById('postContent').focus()">
                                <i class="bi bi-plus-lg me-1"></i> Create Post
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card" id="post-<?php echo $post['id']; ?>">
                            <div class="post-header">
                                <div class="avatar">
                                    <?php echo strtoupper(substr($post['full_name'] ?? $post['username'], 0, 2)); ?>
                                </div>
                                <div class="user-info flex-grow-1">
                                    <div class="name"><?php echo htmlspecialchars($post['full_name'] ?? $post['username']); ?></div>
                                    <div class="time">
                                        <?php echo date('M d, Y • H:i', strtotime($post['created_at'])); ?>
                                        <?php if ($post['is_pinned']): ?>
                                            <span class="badge bg-warning text-dark ms-1">Pinned</span>
                                        <?php endif; ?>
                                        <?php if ($post['is_announcement']): ?>
                                            <span class="badge bg-danger ms-1">Announcement</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($isAdmin || $post['user_id'] == $userId): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-secondary p-0" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($isAdmin): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="togglePinPost(<?php echo $post['id']; ?>)">
                                                        <i class="bi bi-pin me-1"></i> <?php echo $post['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item" onclick="toggleAnnouncement(<?php echo $post['id']; ?>)">
                                                        <i class="bi bi-megaphone me-1"></i> <?php echo $post['is_announcement'] ? 'Remove Announcement' : 'Make Announcement'; ?>
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($post['user_id'] == $userId || $isAdmin): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button class="dropdown-item text-danger" onclick="deletePost(<?php echo $post['id']; ?>)">
                                                        <i class="bi bi-trash3 me-1"></i> Delete
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <button class="dropdown-item" onclick="reportPost(<?php echo $post['id']; ?>)">
                                                    <i class="bi bi-flag me-1"></i> Report
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="post-body">
                                <div class="content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                            </div>

                            <div class="post-footer">
                                <button class="action-btn <?php echo $post['user_reaction'] ? 'liked' : ''; ?>" onclick="toggleReaction(<?php echo $post['id']; ?>)">
                                    <i class="bi <?php echo $post['user_reaction'] ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                                    <span id="reaction-count-<?php echo $post['id']; ?>"><?php echo $post['reaction_count']; ?></span>
                                </button>
                                <button class="action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)">
                                    <i class="bi bi-chat"></i>
                                    <span><?php echo $post['comment_count']; ?></span>
                                </button>
                                <button class="action-btn" onclick="sharePost(<?php echo $post['id']; ?>)">
                                    <i class="bi bi-share"></i>
                                </button>
                            </div>

                            <!-- Comments Section -->
                            <div class="comments-section" id="comments-<?php echo $post['id']; ?>" style="display:none;">
                                <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                                    <!-- Comments loaded via AJAX -->
                                </div>
                                <form class="comment-input-group" onsubmit="return addComment(this, <?php echo $post['id']; ?>)">
                                    <input type="text" placeholder="Write a comment..." required>
                                    <button type="submit"><i class="bi bi-send"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Load More -->
                    <?php if (count($posts) >= $limit): ?>
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-primary btn-sm" onclick="loadMorePosts(<?php echo $communityId; ?>, <?php echo $page + 1; ?>)">
                                <i class="bi bi-arrow-down me-1"></i> Load More
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Community Stats -->
            <div class="sidebar-card">
                <h6><i class="bi bi-info-circle me-1"></i> Community Stats</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Members</span>
                    <span class="fw-bold"><?php echo number_format($memberCount); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Posts</span>
                    <span class="fw-bold"><?php echo $community['post_count']; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Created</span>
                    <span class="fw-bold"><?php echo date('M d, Y', strtotime($community['created_at'])); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-secondary">Type</span>
                    <span class="badge bg-<?php echo $community['type'] === 'public' ? 'success' : ($community['type'] === 'private' ? 'warning' : 'info'); ?>">
                        <?php echo ucfirst($community['type']); ?>
                    </span>
                </div>
            </div>

            <!-- Members -->
            <div class="sidebar-card">
                <h6><i class="bi bi-people me-1"></i> Recent Members</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($members as $member): ?>
                        <div class="text-center" title="<?php echo htmlspecialchars($member['full_name'] ?? $member['username']); ?>">
                            <div class="member-avatar" style="width:40px;height:40px;font-size:0.8rem;">
                                <?php echo strtoupper(substr($member['full_name'] ?? $member['username'], 0, 2)); ?>
                            </div>
                            <?php if ($member['role'] === 'admin'): ?>
                                <span class="badge bg-warning text-dark" style="font-size:0.5rem;">Admin</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($members) >= 9): ?>
                    <div class="text-center mt-2">
                        <a href="members.php?id=<?php echo $communityId; ?>" class="btn btn-sm btn-outline-primary">
                            View All Members
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Community Tasks -->
            <?php if ($isMember && !empty($tasks)): ?>
            <div class="sidebar-card">
                <h6><i class="bi bi-list-check me-1"></i> Community Tasks</h6>
                <?php foreach ($tasks as $task): ?>
                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                        <div>
                            <div class="small fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                            <div class="text-secondary small">
                                <?php echo ucfirst($task['status']); ?>
                                <?php if ($task['deadline']): ?>
                                    · Due <?php echo date('M d', strtotime($task['deadline'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge bg-<?php echo $task['status'] === 'completed' ? 'success' : ($task['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<input type="hidden" id="latestPostId" value="<?php echo count($posts) > 0 ? $posts[0]['id'] : 0; ?>">

<script>
// ============================================
// COMMUNITY FUNCTIONS
// ============================================

// Track latest post ID for polling
let latestPostId = parseInt(document.getElementById('latestPostId').value);

function joinCommunity(communityId) {
    if (!confirm('Join this community?')) return;

    fetch('ajax.php?action=join_community', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'community_id=' + communityId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to join community');
        }
    });
}

function leaveCommunity(communityId) {
    if (!confirm('Leave this community? You can rejoin later.')) return;

    fetch('ajax.php?action=leave_community', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'community_id=' + communityId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to leave community');
        }
    });
}

// Create post – no page reload
function createPost(form) {
    const content = document.getElementById('postContent').value.trim();
    if (!content) {
        alert('Please enter some content');
        return false;
    }

    const postType = document.getElementById('postType').value;
    const communityId = <?php echo $communityId; ?>;

    const btn = form.querySelector('.btn-post');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Posting...';

    fetch('ajax.php?action=create_post', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'community_id=' + communityId + '&content=' + encodeURIComponent(content) + '&post_type=' + postType
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear the textarea
            document.getElementById('postContent').value = '';
            // Immediately fetch new posts (we'll just reload the feed via poll, but we can also append immediately)
            // For simplicity, trigger a poll immediately
            pollNewPosts();
            // Reset button
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i> Post';
        } else {
            alert(data.error || 'Failed to create post');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i> Post';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Post';
    });

    return false;
}

function togglePostType() {
    const types = ['text', 'announcement'];
    const labels = ['Text', 'Announcement'];
    const current = document.getElementById('postType').value;
    const idx = types.indexOf(current);
    const next = (idx + 1) % types.length;
    document.getElementById('postType').value = types[next];
    document.getElementById('postTypeLabel').textContent = labels[next];
}

// Reactions
function toggleReaction(postId) {
    fetch('ajax.php?action=toggle_reaction', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const icon = document.querySelector(`#post-${postId} .bi-heart`);
            const count = document.getElementById(`reaction-count-${postId}`);
            if (data.reacted) {
                icon.className = 'bi bi-heart-fill';
            } else {
                icon.className = 'bi bi-heart';
            }
            count.textContent = data.count;
        }
    });
}

// Comments
function toggleComments(postId) {
    const container = document.getElementById(`comments-${postId}`);
    if (container.style.display === 'none') {
        container.style.display = 'block';
        loadComments(postId);
    } else {
        container.style.display = 'none';
    }
}

function loadComments(postId) {
    const list = document.getElementById(`comments-list-${postId}`);
    if (list.dataset.loaded) return;

    fetch(`ajax.php?action=get_comments&post_id=${postId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.html) {
                list.innerHTML = data.html;
                list.dataset.loaded = 'true';
            }
        });
}

function addComment(form, postId) {
    const input = form.querySelector('input');
    const comment = input.value.trim();
    if (!comment) return false;

    const btn = form.querySelector('button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('ajax.php?action=add_comment', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId + '&comment=' + encodeURIComponent(comment)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadComments(postId);
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i>';
    });

    return false;
}

// Admin functions
function togglePinPost(postId) {
    if (!confirm('Toggle pin status?')) return;

    fetch('ajax.php?action=toggle_pin', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function toggleAnnouncement(postId) {
    if (!confirm('Toggle announcement status?')) return;

    fetch('ajax.php?action=toggle_announcement', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function deletePost(postId) {
    if (!confirm('Delete this post? This cannot be undone.')) return;

    fetch('ajax.php?action=delete_post', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById(`post-${postId}`).remove();
        } else {
            alert('Failed to delete post');
        }
    });
}

function reportPost(postId) {
    const reason = prompt('Why are you reporting this post?');
    if (!reason) return;

    fetch('ajax.php?action=report_post', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId + '&reason=' + encodeURIComponent(reason)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Report submitted. Thank you for helping keep the community safe.');
        }
    });
}

function sharePost(postId) {
    const url = window.location.href + '?post=' + postId;
    if (navigator.share) {
        navigator.share({
            title: 'Check out this post',
            url: url
        });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}

// Load more posts
function loadMorePosts(communityId, page) {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch(`ajax.php?action=get_posts&community_id=${communityId}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.html) {
                document.getElementById('postsFeed').insertAdjacentHTML('beforeend', data.html);
                // Update latest post ID if new posts were loaded
                if (data.newestId > latestPostId) {
                    latestPostId = data.newestId;
                    document.getElementById('latestPostId').value = latestPostId;
                }
                btn.remove();
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-down me-1"></i> Load More';
                if (!data.hasMore) {
                    btn.textContent = 'No more posts';
                    btn.disabled = true;
                }
            }
        });
}

// ============================================
// POLLING FOR NEW POSTS (WhatsApp-style)
// ============================================

function pollNewPosts() {
    const communityId = <?php echo $communityId; ?>;
    fetch(`ajax.php?action=get_posts&community_id=${communityId}&since=${latestPostId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.html) {
                // Insert new posts at the top of the feed
                const feed = document.getElementById('postsFeed');
                feed.insertAdjacentHTML('afterbegin', data.html);
                // Update latest post ID
                if (data.newestId > latestPostId) {
                    latestPostId = data.newestId;
                    document.getElementById('latestPostId').value = latestPostId;
                }
            }
        });
}

// Start polling every 3 seconds
setInterval(pollNewPosts, 3000);
</script>

<?php include '../includes/footer.php'; ?>