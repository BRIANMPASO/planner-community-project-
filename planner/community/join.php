<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/community-functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$code = $_GET['code'] ?? '';
$error = '';
$community = null;

if ($code) {
    $invite = validateInvite($code);
    if ($invite) {
        $community = getCommunity($invite['community_id']);
    } else {
        $error = 'Invalid or expired invite link';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $result = useInvite($code, $_SESSION['user_id']);

    if ($result['success']) {
        header('Location: view.php?id=' . $result['community_id'] . '&joined=1');
        exit;
    } else {
        $error = $result['error'] ?? 'Failed to join community';
    }
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-lg">
                <div class="card-body p-4 p-md-5 text-center">
                    <?php if ($community): ?>
                        <div class="mb-4">
                            <div class="community-avatar mx-auto" style="width:100px;height:100px;border-radius:50%;background:var(--blue-lighter);display:flex;align-items:center;justify-content:center;font-size:3rem;font-weight:700;color:var(--blue);margin-bottom:1rem;">
                                <?php echo strtoupper(substr($community['name'], 0, 2)); ?>
                            </div>
                            <h3 class="fw-bold"><?php echo htmlspecialchars($community['name']); ?></h3>
                            <p class="text-secondary"><?php echo htmlspecialchars(substr($community['description'] ?? '', 0, 150)); ?></p>
                            <div class="d-flex justify-content-center gap-3 text-secondary small">
                                <span><i class="bi bi-people me-1"></i> <?php echo number_format(getFakeMemberCount($community['id'])); ?> members</span>
                                <span><i class="bi bi-chat me-1"></i> <?php echo $community['post_count']; ?> posts</span>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if (isCommunityMember($community['id'], $_SESSION['user_id'])): ?>
                            <div class="alert alert-success">You're already a member!</div>
                            <a href="view.php?id=<?php echo $community['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-arrow-right me-1"></i> Go to Community
                            </a>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-person-plus me-2"></i> Accept Invitation
                                </button>
                            </form>
                            <p class="text-secondary small mt-3">
                                By joining, you agree to follow the community guidelines.
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mb-4">
                            <i class="bi bi-exclamation-circle display-1 text-warning"></i>
                            <h3 class="fw-bold mt-3">Invalid Invite</h3>
                            <p class="text-secondary">This invite link is invalid or has expired.</p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>community/" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-1"></i> Browse Communities
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
