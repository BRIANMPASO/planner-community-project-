<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/community-functions.php';

header('Content-Type: application/json');

// Check if logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$response = ['success' => false];

switch ($action) {
    case 'get_communities':
        $page = $_GET['page'] ?? 1;
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $limit = 12;
        $offset = ($page - 1) * $limit;

        if ($search) {
            $communities = searchCommunities($search, $limit, $offset);
        } else {
            $communities = getCommunities($limit, $offset, $type);
        }

        $html = '';
        foreach ($communities as $community) {
            $isMember = isCommunityMember($community['id'], $userId);
            $html .= '
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 community-card">
                        <div class="card-img-top bg-gradient-primary text-white d-flex align-items-center justify-content-center"
                             style="height:160px;font-size:3rem;font-weight:700;">
                            ' . strtoupper(substr($community['name'], 0, 2)) . '
                        </div>
                        <div class="card-body">
                            <h5 class="card-title fw-bold">' . htmlspecialchars($community['name']) . '</h5>
                            <p class="card-text text-secondary small">' . htmlspecialchars(substr($community['description'] ?? '', 0, 100)) . '</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-secondary small"><i class="bi bi-people me-1"></i>' . number_format(getFakeMemberCount($community['id'])) . ' members</span>
                                <a href="view.php?slug=' . $community['slug'] . '" class="btn btn-sm btn-primary">' . ($isMember ? 'Enter' : 'Join') . '</a>
                            </div>
                        </div>
                    </div>
                </div>
            ';
        }

        $response = [
            'success' => true,
            'html' => $html,
            'hasMore' => count($communities) >= $limit
        ];
        break;

    case 'join_community':
        $communityId = $_POST['community_id'] ?? 0;
        if ($communityId) {
            if (isCommunityMember($communityId, $userId)) {
                $response = ['error' => 'You are already a member of this community'];
            } else {
                if (addCommunityMember($communityId, $userId)) {
                    $response = ['success' => true];
                } else {
                    $response = ['error' => 'Failed to join community'];
                }
            }
        }
        break;

    case 'leave_community':
        $communityId = $_POST['community_id'] ?? 0;
        if ($communityId) {
            if (removeCommunityMember($communityId, $userId)) {
                $response = ['success' => true];
            } else {
                $response = ['error' => 'Failed to leave community'];
            }
        }
        break;

    case 'create_post':
        $communityId = $_POST['community_id'] ?? 0;
        $content = $_POST['content'] ?? '';
        $postType = $_POST['post_type'] ?? 'text';

        if ($communityId && $content) {
            if (containsSpam($content)) {
                $response = ['error' => 'Your post contains inappropriate content'];
                break;
            }

            $data = [
                'community_id' => $communityId,
                'content' => $content,
                'post_type' => $postType,
                'is_announcement' => $postType === 'announcement' ? 1 : 0
            ];

            $result = createCommunityPost($data);
            $response = $result;
        } else {
            $response = ['error' => 'Community ID and content required'];
        }
        break;

    case 'get_posts':
        $communityId = $_GET['community_id'] ?? 0;
        $page = $_GET['page'] ?? 1;
        $since = $_GET['since'] ?? 0; // New: only fetch posts with ID > since
        $limit = 20;
        $offset = ($page - 1) * $limit;

        if ($communityId) {
            // Modify query to filter by ID if since provided
            $db = db();
            $sql = "
                SELECT p.*, u.username, u.full_name,
                       (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reaction_count,
                       (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
                       (SELECT reaction_type FROM post_reactions WHERE post_id = p.id AND user_id = ?) as user_reaction
                FROM community_posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.community_id = ?
            ";
            $params = [$userId, $communityId];

            if ($since > 0) {
                $sql .= " AND p.id > ?";
                $params[] = $since;
            }

            $sql .= " ORDER BY p.is_pinned DESC, p.created_at DESC";
            if ($since == 0) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $posts = $stmt->fetchAll();

            $html = '';
            foreach ($posts as $post) {
                $isAdmin = isCommunityAdmin($communityId, $userId);
                $html .= '
                    <div class="post-card" id="post-' . $post['id'] . '">
                        <div class="post-header">
                            <div class="avatar">
                                ' . strtoupper(substr($post['full_name'] ?? $post['username'], 0, 2)) . '
                            </div>
                            <div class="user-info flex-grow-1">
                                <div class="name">' . htmlspecialchars($post['full_name'] ?? $post['username']) . '</div>
                                <div class="time">
                                    ' . date('M d, Y • H:i', strtotime($post['created_at'])) . '
                                    ' . ($post['is_pinned'] ? '<span class="badge bg-warning text-dark ms-1">Pinned</span>' : '') . '
                                    ' . ($post['is_announcement'] ? '<span class="badge bg-danger ms-1">Announcement</span>' : '') . '
                                </div>
                            </div>
                            ' . (($isAdmin || $post['user_id'] == $userId) ? '
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-secondary p-0" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    ' . ($isAdmin ? '
                                    <li><button class="dropdown-item" onclick="togglePinPost(' . $post['id'] . ')"><i class="bi bi-pin me-1"></i> ' . ($post['is_pinned'] ? 'Unpin' : 'Pin') . '</button></li>
                                    <li><button class="dropdown-item" onclick="toggleAnnouncement(' . $post['id'] . ')"><i class="bi bi-megaphone me-1"></i> ' . ($post['is_announcement'] ? 'Remove Announcement' : 'Make Announcement') . '</button></li>
                                    ' : '') . '
                                    ' . (($post['user_id'] == $userId || $isAdmin) ? '
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button class="dropdown-item text-danger" onclick="deletePost(' . $post['id'] . ')"><i class="bi bi-trash3 me-1"></i> Delete</button></li>
                                    ' : '') . '
                                    <li><button class="dropdown-item" onclick="reportPost(' . $post['id'] . ')"><i class="bi bi-flag me-1"></i> Report</button></li>
                                </ul>
                            </div>
                            ' : '') . '
                        </div>
                        <div class="post-body">
                            <div class="content">' . nl2br(htmlspecialchars($post['content'])) . '</div>
                        </div>
                        <div class="post-footer">
                            <button class="action-btn ' . ($post['user_reaction'] ? 'liked' : '') . '" onclick="toggleReaction(' . $post['id'] . ')">
                                <i class="bi ' . ($post['user_reaction'] ? 'bi-heart-fill' : 'bi-heart') . '"></i>
                                <span id="reaction-count-' . $post['id'] . '">' . $post['reaction_count'] . '</span>
                            </button>
                            <button class="action-btn" onclick="toggleComments(' . $post['id'] . ')">
                                <i class="bi bi-chat"></i>
                                <span>' . $post['comment_count'] . '</span>
                            </button>
                            <button class="action-btn" onclick="sharePost(' . $post['id'] . ')">
                                <i class="bi bi-share"></i>
                            </button>
                        </div>
                        <div class="comments-section" id="comments-' . $post['id'] . '" style="display:none;">
                            <div class="comments-list" id="comments-list-' . $post['id'] . '"></div>
                            <form class="comment-input-group" onsubmit="return addComment(this, ' . $post['id'] . ')">
                                <input type="text" placeholder="Write a comment..." required>
                                <button type="submit"><i class="bi bi-send"></i></button>
                            </form>
                        </div>
                    </div>
                ';
            }

            $response = [
                'success' => true,
                'html' => $html,
                'hasMore' => count($posts) >= $limit,
                'newestId' => count($posts) > 0 ? $posts[0]['id'] : 0 // return highest ID for polling
            ];
        }
        break;

    case 'toggle_reaction':
        $postId = $_POST['post_id'] ?? 0;
        if ($postId) {
            $db = db();
            $stmt = $db->prepare("SELECT id FROM post_reactions WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $response['success'] = removeReaction($postId);
                $response['reacted'] = false;
            } else {
                $response['success'] = addReaction($postId, 'like');
                $response['reacted'] = true;
            }

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM post_reactions WHERE post_id = ?");
            $stmt->execute([$postId]);
            $response['count'] = $stmt->fetch()['count'];
        }
        break;

    case 'add_comment':
        $postId = $_POST['post_id'] ?? 0;
        $comment = $_POST['comment'] ?? '';

        if ($postId && $comment) {
            if (containsSpam($comment)) {
                $response = ['error' => 'Your comment contains inappropriate content'];
                break;
            }
            $result = addComment($postId, $comment);
            $response = $result;
        }
        break;

    case 'get_comments':
        $postId = $_GET['post_id'] ?? 0;
        if ($postId) {
            $comments = getComments($postId);
            $html = '';
            foreach ($comments as $comment) {
                $html .= '
                    <div class="comment-item">
                        <div class="avatar" style="width:32px;height:32px;font-size:0.7rem;">
                            ' . strtoupper(substr($comment['full_name'] ?? $comment['username'], 0, 2)) . '
                        </div>
                        <div class="comment-body flex-grow-1">
                            <div class="name">' . htmlspecialchars($comment['full_name'] ?? $comment['username']) . '</div>
                            <div class="text">' . nl2br(htmlspecialchars($comment['comment'])) . '</div>
                            <div class="time">' . date('M d, H:i', strtotime($comment['created_at'])) . '</div>
                        </div>
                    </div>
                ';
            }
            $response = ['success' => true, 'html' => $html];
        }
        break;

    case 'toggle_pin':
        $postId = $_POST['post_id'] ?? 0;
        if ($postId) {
            $db = db();
            $stmt = $db->prepare("SELECT is_pinned FROM community_posts WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();

            if ($post['is_pinned']) {
                $response['success'] = unpinPost($postId);
            } else {
                $response['success'] = pinPost($postId);
            }
        }
        break;

    case 'toggle_announcement':
        $postId = $_POST['post_id'] ?? 0;
        if ($postId) {
            $db = db();
            $stmt = $db->prepare("UPDATE community_posts SET is_announcement = NOT is_announcement WHERE id = ?");
            $response['success'] = $stmt->execute([$postId]);
        }
        break;

    case 'delete_post':
        $postId = $_POST['post_id'] ?? 0;
        if ($postId) {
            $response['success'] = deletePost($postId);
        }
        break;

    case 'report_post':
        $postId = $_POST['post_id'] ?? 0;
        $reason = $_POST['reason'] ?? 'other';
        if ($postId) {
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO community_reports (post_id, reported_by, reason, details)
                VALUES (?, ?, ?, ?)
            ");
            $response['success'] = $stmt->execute([$postId, $userId, $reason, $_POST['details'] ?? '']);
        }
        break;

    case 'generate_invite':
        $communityId = $_POST['community_id'] ?? 0;
        if ($communityId) {
            $result = createInvite($communityId, null, 10);
            if ($result['success']) {
                $response = [
                    'success' => true,
                    'link' => SITE_URL . 'community/join.php?code=' . $result['invite_code']
                ];
            } else {
                $response = ['error' => 'Failed to generate invite'];
            }
        }
        break;

    case 'bulk_invite':
        $communityId = $_POST['community_id'] ?? 0;
        $emailsText = $_POST['emails'] ?? '';
        $emails = array_filter(array_map('trim', explode("\n", $emailsText)));

        if ($communityId && !empty($emails)) {
            $results = bulkInvite($communityId, $emails);
            $response = ['success' => true, 'sent' => count($results)];
        } else {
            $response = ['error' => 'Community ID and emails required'];
        }
        break;

    case 'get_notification_count':
        $response = ['count' => getUnreadNotifications($userId)];
        break;

    default:
        $response = ['error' => 'Invalid action'];
}

echo json_encode($response);
?>