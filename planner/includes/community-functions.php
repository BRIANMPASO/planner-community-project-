<?php
/**
 * Community Functions
 * All community-related database operations
 */

require_once 'db.php';
require_once 'functions.php';

// ============================================
// COMMUNITY CRUD
// ============================================

function createCommunity($data) {
    $db = db();

    // Generate slug
    $slug = createSlug($data['name']);

    // Generate invite code
    $inviteCode = generateInviteCode();

    // Calculate fake member offset (random 50-500)
    $fakeOffset = rand(50, 500);

    $stmt = $db->prepare("
        INSERT INTO communities (
            name, slug, description, category, district, tags,
            type, created_by, invite_code, invite_expiry, fake_member_offset
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
    ");

    $result = $stmt->execute([
        $data['name'],
        $slug,
        $data['description'] ?? '',
        $data['category'] ?? 'General',
        $data['district'] ?? null,
        $data['tags'] ?? null,
        $data['type'] ?? 'public',
        $_SESSION['user_id'],
        $inviteCode,
        $fakeOffset
    ]);

    if ($result) {
        $communityId = $db->lastInsertId();

        // Add creator as admin
        addCommunityMember($communityId, $_SESSION['user_id'], 'admin');

        // Log activity
        logActivity($_SESSION['user_id'], 'community_created', "Created community: {$data['name']}");

        return ['success' => true, 'community_id' => $communityId, 'invite_code' => $inviteCode];
    }

    return ['error' => 'Failed to create community'];
}

function getCommunity($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM communities WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getCommunityBySlug($slug) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM communities WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getCommunities($limit = 20, $offset = 0, $type = null, $search = null) {
    $db = db();
    $params = [];
    $sql = "SELECT c.*, u.username as creator_name
            FROM communities c
            JOIN users u ON c.created_by = u.id
            WHERE c.is_active = 1";

    if ($type) {
        $sql .= " AND c.type = ?";
        $params[] = $type;
    }

    if ($search) {
        $sql .= " AND (c.name LIKE ? OR c.description LIKE ? OR c.tags LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY c.member_count DESC, c.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTrendingCommunities($limit = 6) {
    $db = db();
    $stmt = $db->prepare("
        SELECT c.*, u.username as creator_name,
               (c.member_count + c.post_count + (SELECT COUNT(*) FROM community_posts WHERE community_id = c.id AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY))) as activity_score
        FROM communities c
        JOIN users u ON c.created_by = u.id
        WHERE c.is_active = 1
        ORDER BY activity_score DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function updateCommunity($id, $data) {
    $db = db();
    $sets = [];
    $params = [];

    $allowed = ['name', 'description', 'category', 'district', 'tags', 'type', 'image', 'cover_image'];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = $id;
    $sql = "UPDATE communities SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

function deleteCommunity($id) {
    $db = db();
    $stmt = $db->prepare("UPDATE communities SET is_active = 0 WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// COMMUNITY MEMBERS
// ============================================

function addCommunityMember($communityId, $userId, $role = 'member') {
    $db = db();

    // Check if already a member - PREVENT DUPLICATE
    $check = $db->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
    $check->execute([$communityId, $userId]);
    if ($check->fetch()) {
        return false; // Already a member
    }

    $stmt = $db->prepare("
        INSERT INTO community_members (community_id, user_id, role)
        VALUES (?, ?, ?)
    ");
    $result = $stmt->execute([$communityId, $userId, $role]);

    if ($result) {
        // Update member count
        updateCommunityMemberCount($communityId);

        // Add notification
        $community = getCommunity($communityId);
        addNotification($userId, 'join', "You joined {$community['name']}", $communityId);

        return true;
    }
    return false;
}

function removeCommunityMember($communityId, $userId) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
    $result = $stmt->execute([$communityId, $userId]);

    if ($result) {
        updateCommunityMemberCount($communityId);
        return true;
    }
    return false;
}

function isCommunityMember($communityId, $userId) {
    $db = db();
    $stmt = $db->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ? AND is_banned = 0");
    $stmt->execute([$communityId, $userId]);
    return $stmt->fetch() !== false;
}

function isCommunityAdmin($communityId, $userId) {
    $db = db();
    $stmt = $db->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ? AND role IN ('admin', 'moderator') AND is_banned = 0");
    $stmt->execute([$communityId, $userId]);
    return $stmt->fetch() !== false;
}

function getCommunityMembers($communityId, $limit = 20, $offset = 0) {
    $db = db();
    $stmt = $db->prepare("
        SELECT cm.*, u.username, u.full_name, u.email
        FROM community_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.community_id = ? AND cm.is_banned = 0
        ORDER BY cm.joined_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$communityId, $limit, $offset]);
    return $stmt->fetchAll();
}

function updateCommunityMemberCount($communityId) {
    $db = db();

    // Get real count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM community_members WHERE community_id = ? AND is_banned = 0");
    $stmt->execute([$communityId]);
    $realCount = $stmt->fetch()['count'];

    // Get community to get fake offset
    $stmt = $db->prepare("SELECT fake_member_offset FROM communities WHERE id = ?");
    $stmt->execute([$communityId]);
    $community = $stmt->fetch();

    $fakeCount = $realCount + ($community['fake_member_offset'] ?? 0);

    // Update both counts
    $stmt = $db->prepare("
        UPDATE communities
        SET real_member_count = ?, member_count = ?
        WHERE id = ?
    ");
    return $stmt->execute([$realCount, $fakeCount, $communityId]);
}

function getMemberCount($communityId, $showReal = false) {
    $db = db();
    $stmt = $db->prepare("SELECT member_count, real_member_count FROM communities WHERE id = ?");
    $stmt->execute([$communityId]);
    $data = $stmt->fetch();

    if ($showReal) {
        return $data['real_member_count'] ?? 0;
    }
    return $data['member_count'] ?? 0;
}

// ============================================
// COMMUNITY POSTS
// ============================================

function createCommunityPost($data) {
    $db = db();

    $stmt = $db->prepare("
        INSERT INTO community_posts (
            community_id, user_id, content, post_type, task_id, image, is_announcement
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        $data['community_id'],
        $_SESSION['user_id'],
        $data['content'],
        $data['post_type'] ?? 'text',
        $data['task_id'] ?? null,
        $data['image'] ?? null,
        $data['is_announcement'] ?? 0
    ]);

    if ($result) {
        $postId = $db->lastInsertId();

        // Update post count
        $stmt = $db->prepare("UPDATE communities SET post_count = post_count + 1 WHERE id = ?");
        $stmt->execute([$data['community_id']]);

        // Notify members
        notifyCommunityMembers($data['community_id'], 'post', "New post in community", $postId);

        return ['success' => true, 'post_id' => $postId];
    }

    return ['error' => 'Failed to create post'];
}

function getCommunityPosts($communityId, $limit = 20, $offset = 0) {
    $db = db();
    $stmt = $db->prepare("
        SELECT p.*, u.username, u.full_name,
               (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reaction_count,
               (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
               (SELECT reaction_type FROM post_reactions WHERE post_id = p.id AND user_id = ?) as user_reaction
        FROM community_posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.community_id = ?
        ORDER BY p.is_pinned DESC, p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$_SESSION['user_id'], $communityId, $limit, $offset]);
    return $stmt->fetchAll();
}

function getPost($postId) {
    $db = db();
    $stmt = $db->prepare("
        SELECT p.*, u.username, u.full_name,
               (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) as reaction_count,
               (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
               (SELECT reaction_type FROM post_reactions WHERE post_id = p.id AND user_id = ?) as user_reaction
        FROM community_posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $postId]);
    return $stmt->fetch();
}

function deletePost($postId) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM community_posts WHERE id = ?");
    return $stmt->execute([$postId]);
}

function pinPost($postId) {
    $db = db();
    $stmt = $db->prepare("UPDATE community_posts SET is_pinned = 1 WHERE id = ?");
    return $stmt->execute([$postId]);
}

function unpinPost($postId) {
    $db = db();
    $stmt = $db->prepare("UPDATE community_posts SET is_pinned = 0 WHERE id = ?");
    return $stmt->execute([$postId]);
}

// ============================================
// REACTIONS
// ============================================

function addReaction($postId, $type = 'like') {
    $db = db();
    $userId = $_SESSION['user_id'];

    // Check if already reacted
    $stmt = $db->prepare("SELECT id FROM post_reactions WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update reaction
        $stmt = $db->prepare("UPDATE post_reactions SET reaction_type = ? WHERE post_id = ? AND user_id = ?");
        return $stmt->execute([$type, $postId, $userId]);
    } else {
        // Add reaction
        $stmt = $db->prepare("INSERT INTO post_reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)");
        $result = $stmt->execute([$postId, $userId, $type]);

        if ($result) {
            // Update likes count
            $stmt = $db->prepare("UPDATE community_posts SET likes = likes + 1 WHERE id = ?");
            $stmt->execute([$postId]);
        }
        return $result;
    }
}

function removeReaction($postId) {
    $db = db();
    $userId = $_SESSION['user_id'];

    $stmt = $db->prepare("DELETE FROM post_reactions WHERE post_id = ? AND user_id = ?");
    $result = $stmt->execute([$postId, $userId]);

    if ($result) {
        $stmt = $db->prepare("UPDATE community_posts SET likes = likes - 1 WHERE id = ?");
        $stmt->execute([$postId]);
    }

    return $result;
}

// ============================================
// COMMENTS
// ============================================

function addComment($postId, $comment, $parentId = null) {
    $db = db();
    $userId = $_SESSION['user_id'];

    $stmt = $db->prepare("
        INSERT INTO post_comments (post_id, user_id, comment, parent_id)
        VALUES (?, ?, ?, ?)
    ");
    $result = $stmt->execute([$postId, $userId, $comment, $parentId]);

    if ($result) {
        // Update comment count
        $stmt = $db->prepare("UPDATE community_posts SET comments_count = comments_count + 1 WHERE id = ?");
        $stmt->execute([$postId]);

        // Get post for notification
        $post = getPost($postId);
        if ($post && $post['user_id'] != $userId) {
            addNotification($post['user_id'], 'comment', "Someone commented on your post", $post['community_id'], $postId);
        }

        return ['success' => true, 'comment_id' => $db->lastInsertId()];
    }

    return ['error' => 'Failed to add comment'];
}

function getComments($postId, $limit = 20, $offset = 0) {
    $db = db();
    $stmt = $db->prepare("
        SELECT c.*, u.username, u.full_name
        FROM post_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$postId, $limit, $offset]);
    return $stmt->fetchAll();
}

function deleteComment($commentId) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM post_comments WHERE id = ?");
    return $stmt->execute([$commentId]);
}

// ============================================
// INVITES
// ============================================

function generateInviteCode() {
    return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}

function createInvite($communityId, $email = null, $maxUses = 5) {
    $db = db();
    $code = generateInviteCode();

    $stmt = $db->prepare("
        INSERT INTO community_invites (community_id, invited_by, invite_code, email, max_uses, expires_at)
        VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    $result = $stmt->execute([
        $communityId,
        $_SESSION['user_id'],
        $code,
        $email,
        $maxUses
    ]);

    if ($result) {
        // Update community invite code
        $stmt = $db->prepare("UPDATE communities SET invite_code = ?, invite_expiry = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?");
        $stmt->execute([$code, $communityId]);

        return ['success' => true, 'invite_code' => $code];
    }

    return ['error' => 'Failed to create invite'];
}

function validateInvite($code) {
    $db = db();
    $stmt = $db->prepare("
        SELECT * FROM community_invites
        WHERE invite_code = ?
        AND (expires_at IS NULL OR expires_at > NOW())
        AND used_count < max_uses
    ");
    $stmt->execute([$code]);
    return $stmt->fetch();
}

function useInvite($code, $userId) {
    $db = db();

    $invite = validateInvite($code);
    if (!$invite) {
        return ['error' => 'Invalid or expired invite'];
    }

    // Check if already member
    if (isCommunityMember($invite['community_id'], $userId)) {
        return ['error' => 'Already a member of this community'];
    }

    // Add member
    addCommunityMember($invite['community_id'], $userId);

    // Update invite usage
    $stmt = $db->prepare("UPDATE community_invites SET used_count = used_count + 1 WHERE id = ?");
    $stmt->execute([$invite['id']]);

    return ['success' => true, 'community_id' => $invite['community_id']];
}

function bulkInvite($communityId, $emails) {
    $db = db();
    $results = [];

    foreach ($emails as $email) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $result = createInvite($communityId, $email, 1);
        if ($result['success']) {
            $results[] = ['email' => $email, 'status' => 'sent'];

            // Send email notification (placeholder)
            // mail($email, "Invitation to join community", "You've been invited...");
        }
    }

    return $results;
}

// ============================================
// NOTIFICATIONS
// ============================================

function addNotification($userId, $type, $message, $communityId = null, $postId = null) {
    $db = db();

    $stmt = $db->prepare("
        INSERT INTO community_notifications (user_id, community_id, post_id, type, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$userId, $communityId, $postId, $type, $message]);
}

function getNotifications($userId, $limit = 20, $offset = 0) {
    $db = db();
    $stmt = $db->prepare("
        SELECT * FROM community_notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

function getUnreadNotifications($userId) {
    $db = db();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM community_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetch()['count'];
}

function markNotificationRead($notificationId) {
    $db = db();
    $stmt = $db->prepare("UPDATE community_notifications SET is_read = 1 WHERE id = ?");
    return $stmt->execute([$notificationId]);
}

function markAllNotificationsRead($userId) {
    $db = db();
    $stmt = $db->prepare("UPDATE community_notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

function notifyCommunityMembers($communityId, $type, $message, $postId = null) {
    $db = db();

    $stmt = $db->prepare("SELECT user_id FROM community_members WHERE community_id = ? AND is_banned = 0");
    $stmt->execute([$communityId]);
    $members = $stmt->fetchAll();

    foreach ($members as $member) {
        addNotification($member['user_id'], $type, $message, $communityId, $postId);
    }
}

// ============================================
// COMMUNITY TASKS
// ============================================

function createCommunityTask($data) {
    $db = db();

    $stmt = $db->prepare("
        INSERT INTO community_tasks (community_id, created_by, assigned_to, title, description, deadline)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        $data['community_id'],
        $_SESSION['user_id'],
        $data['assigned_to'] ?? null,
        $data['title'],
        $data['description'] ?? null,
        $data['deadline'] ?? null
    ]);

    if ($result) {
        $taskId = $db->lastInsertId();

        // Notify assigned user
        if ($data['assigned_to'] ?? null) {
            addNotification($data['assigned_to'], 'task', "New task assigned to you: {$data['title']}", $data['community_id']);
        }

        return ['success' => true, 'task_id' => $taskId];
    }

    return ['error' => 'Failed to create task'];
}

function getCommunityTasks($communityId, $status = null) {
    $db = db();
    $sql = "SELECT * FROM community_tasks WHERE community_id = ?";
    $params = [$communityId];

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY deadline ASC, created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateCommunityTaskStatus($taskId, $status) {
    $db = db();
    $stmt = $db->prepare("UPDATE community_tasks SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $taskId]);
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

function getDistricts() {
    $districts = getSetting('community_districts', 'Lilongwe,Blantyre,Mzuzu,Zomba');
    return array_map('trim', explode(',', $districts));
}

function getCategories() {
    $categories = getSetting('community_categories', 'Education,Technology,Health,Business,Arts,Entertainment,Religious,Sports,General');
    return array_map('trim', explode(',', $categories));
}

function getSpamWords() {
    $words = getSetting('community_spam_words', 'viagra,casino,bitcoin,xxx,adult,porn,sex');
    return array_map('trim', explode(',', $words));
}

function containsSpam($text) {
    $words = getSpamWords();
    $text = strtolower($text);
    foreach ($words as $word) {
        if (strpos($text, strtolower($word)) !== false) {
            return true;
        }
    }
    return false;
}

function getFakeMemberCount($communityId) {
    $db = db();
    $stmt = $db->prepare("SELECT member_count, real_member_count FROM communities WHERE id = ?");
    $stmt->execute([$communityId]);
    $data = $stmt->fetch();

    if (!$data) {
        return 0;
    }

    // For non-members, show inflated count
    if (!isCommunityMember($communityId, $_SESSION['user_id'] ?? 0)) {
        return $data['member_count'];
    }

    // For members, show real count
    return $data['real_member_count'];
}

function getCommunityActivity($communityId) {
    $db = db();
    $stmt = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM community_posts WHERE community_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as posts_24h,
            (SELECT COUNT(*) FROM community_posts WHERE community_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as posts_7d,
            (SELECT COUNT(*) FROM community_members WHERE community_id = ? AND joined_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_members_7d
    ");
    $stmt->execute([$communityId, $communityId, $communityId]);
    return $stmt->fetch();
}

// ============================================
// COMMUNITY SEARCH
// ============================================

function searchCommunities($query, $limit = 20, $offset = 0) {
    $db = db();
    $stmt = $db->prepare("
        SELECT c.*, u.username as creator_name,
               CASE
                   WHEN c.name LIKE ? THEN 3
                   WHEN c.tags LIKE ? THEN 2
                   WHEN c.description LIKE ? THEN 1
                   ELSE 0
               END as relevance
        FROM communities c
        JOIN users u ON c.created_by = u.id
        WHERE c.is_active = 1
        AND (c.name LIKE ? OR c.description LIKE ? OR c.tags LIKE ? OR c.district LIKE ?)
        ORDER BY relevance DESC, c.member_count DESC
        LIMIT ? OFFSET ?
    ");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
    return $stmt->fetchAll();
}
