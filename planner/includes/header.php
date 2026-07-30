<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> — Plan Your Day, Achieve Your Goals</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">

    <?php
    // Load community functions if user is logged in
    if (isLoggedIn()) {
        require_once __DIR__ . '/../includes/community-functions.php';
    }
    ?>

    <style>
        /* Community Panel Styles */
        .community-panel-toggle {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1040;
            background: #0B2647 !important; /* solid navy – visible on white */
            color: white !important;
            border: none;
            border-radius: 0 12px 12px 0;
            padding: 12px 10px 12px 8px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11, 38, 71, 0.3);
            transition: all 0.3s;
            writing-mode: vertical-rl;
            letter-spacing: 2px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .community-panel-toggle:hover {
            padding-left: 14px;
            background: #2D7BDE !important;
        }

        .community-panel-toggle .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
        }

        .community-panel {
            position: fixed;
            left: -380px;
            top: 0;
            width: 380px;
            height: 100vh;
            background: white;
            z-index: 1050;
            box-shadow: var(--shadow-lg);
            transition: left 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow-y: auto;
            padding: 0;
            border-right: 1px solid var(--border-light);
        }

        .community-panel.open {
            left: 0;
        }

        .community-panel .panel-header {
            background: var(--gradient-primary);
            color: white;
            padding: 1.5rem 1.2rem;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .community-panel .panel-header .close-btn {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .community-panel .panel-header .close-btn:hover {
            background: rgba(255,255,255,0.25);
        }

        .community-panel .panel-body {
            padding: 1rem 1.2rem;
        }

        .community-panel .panel-section {
            margin-bottom: 1.5rem;
        }

        .community-panel .panel-section h6 {
            font-weight: 700;
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.8rem;
        }

        .community-panel .community-item {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.8rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: var(--text-dark);
        }

        .community-panel .community-item:hover {
            background: var(--blue-bg);
        }

        .community-panel .community-item .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--blue-lighter);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.7rem;
            color: var(--blue);
            flex-shrink: 0;
            margin-right: 0.8rem;
        }

        .community-panel .community-item .info {
            flex: 1;
        }

        .community-panel .community-item .info .name {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .community-panel .community-item .info .meta {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .community-panel .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .community-panel .activity-item:last-child {
            border-bottom: none;
        }

        .community-panel .activity-item .icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            flex-shrink: 0;
            margin-right: 0.8rem;
            margin-top: 0.1rem;
        }

        .community-panel .activity-item .icon.green { background: #e6f7e6; color: #22c55e; }
        .community-panel .activity-item .icon.blue { background: var(--blue-lighter); color: var(--blue); }
        .community-panel .activity-item .icon.orange { background: #fef3e2; color: #f59e0b; }
        .community-panel .activity-item .icon.red { background: #fde8ea; color: #dc3545; }

        .community-panel .activity-item .content {
            flex: 1;
        }

        .community-panel .activity-item .content .text {
            font-size: 0.8rem;
        }

        .community-panel .activity-item .content .time {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .community-panel-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 1045;
            display: none;
        }

        .community-panel-overlay.show {
            display: block;
        }

        /* Dark Mode Toggle */
        .dark-mode-toggle {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            transition: var(--transition);
        }

        .dark-mode-toggle:hover {
            background: var(--blue-bg);
            color: var(--blue);
        }

        /* Responsive */
        @media (max-width: 576px) {
            .community-panel {
                width: 100%;
                left: -100%;
            }

            .community-panel-toggle {
                writing-mode: horizontal-tb;
                padding: 6px 12px;
                top: auto;
                bottom: 20px;
                left: 20px;
                transform: none;
                border-radius: 50px;
                font-size: 0.7rem;
            }

            .community-panel-toggle span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Community Panel Toggle Button -->
    <button class="community-panel-toggle" id="panelToggle" title="Open Community Panel">
        <i class="bi bi-people"></i>
        <span class="ms-1">Community</span>
        <?php
        $unreadCount = 0;
        if (isLoggedIn() && function_exists('getUnreadNotifications')) {
            $unreadCount = getUnreadNotifications($_SESSION['user_id']);
        }
        ?>
        <?php if ($unreadCount > 0): ?>
            <span class="badge bg-danger rounded-pill notification-badge"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
    </button>

    <!-- Community Panel Overlay -->
    <div class="community-panel-overlay" id="panelOverlay"></div>

    <!-- Community Panel -->
    <div class="community-panel" id="communityPanel">
        <div class="panel-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0"><i class="bi bi-people me-2"></i> Community</h5>
                    <small class="opacity-75">Connect with others</small>
                </div>
                <button class="close-btn" id="panelClose">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <div class="panel-body">
            <?php if (isLoggedIn() && function_exists('getUnreadNotifications')): ?>
                <?php
                // Get user's communities
                $db = db();
                $stmt = $db->prepare("
                    SELECT c.*, cm.role
                    FROM communities c
                    JOIN community_members cm ON c.id = cm.community_id
                    WHERE cm.user_id = ? AND c.is_active = 1
                    ORDER BY cm.joined_at DESC
                    LIMIT 10
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $myCommunities = $stmt->fetchAll();
                ?>

                <?php if (!empty($myCommunities)): ?>
                    <div class="panel-section">
                        <h6><i class="bi bi-house me-1"></i> Your Communities</h6>
                        <?php foreach ($myCommunities as $comm): ?>
                            <a href="<?php echo SITE_URL; ?>community/view.php?slug=<?php echo $comm['slug']; ?>" class="community-item">
                                <div class="avatar">
                                    <?php echo strtoupper(substr($comm['name'], 0, 2)); ?>
                                </div>
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($comm['name']); ?></div>
                                    <div class="meta">
                                        <i class="bi bi-people me-1"></i>
                                        <?php echo number_format(getFakeMemberCount($comm['id'])); ?> members
                                        <?php if ($comm['role'] === 'admin'): ?>
                                            <span class="badge bg-warning text-dark ms-1">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="panel-section">
                    <h6><i class="bi bi-clock-history me-1"></i> Recent Activity</h6>
                    <?php
                    $stmt = $db->prepare("
                        SELECT cn.*, c.name as community_name, c.slug as community_slug
                        FROM community_notifications cn
                        LEFT JOIN communities c ON cn.community_id = c.id
                        WHERE cn.user_id = ?
                        ORDER BY cn.created_at DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $notifications = $stmt->fetchAll();
                    ?>

                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notif): 
                            // Build link
                            $link = SITE_URL . 'community/view.php?slug=' . $notif['community_slug'];
                            if ($notif['post_id']) {
                                $link .= '&post=' . $notif['post_id'];
                            }
                        ?>
                            <a href="<?php echo $link; ?>" class="activity-item text-decoration-none">
                                <div class="icon <?php
                                    echo $notif['type'] === 'join' ? 'green' :
                                         ($notif['type'] === 'post' ? 'blue' :
                                         ($notif['type'] === 'comment' ? 'orange' : 'red'));
                                ?>">
                                    <i class="bi bi-<?php
                                        echo $notif['type'] === 'join' ? 'person-plus' :
                                             ($notif['type'] === 'post' ? 'chat' :
                                             ($notif['type'] === 'comment' ? 'reply' : 'bell'));
                                    ?>"></i>
                                </div>
                                <div class="content">
                                    <div class="text">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                        <?php if ($notif['community_name']): ?>
                                            <span class="fw-bold">in <?php echo htmlspecialchars($notif['community_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="time"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-secondary small">No recent activity</p>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="panel-section">
                    <h6><i class="bi bi-plus-circle me-1"></i> Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="<?php echo SITE_URL; ?>community/create.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> Create Community
                        </a>
                        <a href="<?php echo SITE_URL; ?>community/" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-compass me-1"></i> Discover Communities
                        </a>
                    </div>
                </div>

                <!-- Dark Mode Toggle -->
                <div class="panel-section">
                    <button class="btn btn-outline-secondary btn-sm w-100" onclick="toggleDarkMode()">
                        <i class="bi bi-moon me-1"></i> Toggle Dark Mode
                    </button>
                </div>

            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-people display-1 text-secondary opacity-25"></i>
                    <h5 class="mt-3">Join the Community</h5>
                    <p class="text-secondary small">Sign in to connect with others</p>
                    <a href="<?php echo SITE_URL; ?>login.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Navigation -->
    <header>
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container">
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="height:32px; width:32px;">
                        <rect x="24" y="22" width="16" height="56" rx="6" fill="#0B2647"/>
                        <path d="M 40 22 H 58 C 74 22, 78 46, 69 56 C 60 66, 40 64, 40 64 Z" fill="#0B2647"/>
                        <circle cx="80" cy="28" r="6" fill="#2D7BDE"/>
                        <circle cx="80" cy="28" r="3" fill="#ffffff"/>
                        <line x1="72" y1="32" x2="62" y2="42" stroke="#2D7BDE" stroke-width="1.5" opacity="0.3"/>
                        <circle cx="48" cy="44" r="2" fill="#2D7BDE" opacity="0.4"/>
                    </svg>
                    Pam<span>odzi</span>
                </a>
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <?php if (isLoggedIn()): ?>
                          <li class="nav-item position-relative">
                              <a class="nav-link" href="<?php echo SITE_URL; ?>community/" title="Community">
                                  <i class="bi bi-people"></i>
                                  <?php
                                  $unreadCount = 0;
                                  if (isLoggedIn() && function_exists('getUnreadNotifications')) {
                                      $unreadCount = getUnreadNotifications($_SESSION['user_id']);
                                  }
                                  ?>
                                  <?php if ($unreadCount > 0): ?>
                                      <span class="badge bg-danger rounded-pill notification-badge" style="font-size:0.6rem;position:absolute;top:-4px;right:-4px;cursor:pointer;" onclick="document.getElementById('panelToggle').click();">
                                          <?php echo $unreadCount; ?>
                                      </span>
                                  <?php endif; ?>
                              </a>
                          </li>
                            <li class="nav-item">
                                <span class="nav-link">
                                    <i class="bi bi-person-circle me-1"></i>
                                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                                </span>
                            </li>
                            <?php if (isAdmin()): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo SITE_URL; ?>admin/" style="color: var(--blue) !important;">
                                        <i class="bi bi-shield-lock me-1"></i>Admin
                                        <span class="badge bg-warning text-dark">Secure</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link btn-logout" href="<?php echo SITE_URL; ?>logout.php">
                                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo SITE_URL; ?>login.php">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link btn-nav" href="<?php echo SITE_URL; ?>register.php" style="background: var(--gradient-blue); color: white !important; border-radius: 50px; padding: 0.5rem 1.8rem !important;">
                                    <i class="bi bi-person-plus me-1"></i>Get Started
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main>

<script>
// ============================================
// COMMUNITY PANEL
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const panel = document.getElementById('communityPanel');
    const overlay = document.getElementById('panelOverlay');
    const toggleBtn = document.getElementById('panelToggle');
    const closeBtn = document.getElementById('panelClose');

    // Check if user prefers dark mode
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
    }

    function openPanel() {
        panel.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closePanel() {
        panel.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (panel.classList.contains('open')) {
            closePanel();
        } else {
            openPanel();
        }
    });

    closeBtn.addEventListener('click', closePanel);
    overlay.addEventListener('click', closePanel);

    // Close with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            closePanel();
        }
    });
});

// Dark Mode Toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// Notification count refresh
function refreshNotificationCount() {
    <?php if (isLoggedIn() && function_exists('getUnreadNotifications')): ?>
    fetch('<?php echo SITE_URL; ?>community/ajax.php?action=get_notification_count')
        .then(response => response.json())
        .then(data => {
            const badges = document.querySelectorAll('.notification-badge');
            badges.forEach(badge => {
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'inline';
                } else {
                    badge.style.display = 'none';
                }
            });
        });
    <?php endif; ?>
}

// Refresh every 30 seconds
setInterval(refreshNotificationCount, 30000);
</script>