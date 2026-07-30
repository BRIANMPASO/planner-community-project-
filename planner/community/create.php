<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/community-functions.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$error = '';
$success = '';
$districts = getDistricts();
$categories = getCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'General';
    $district = $_POST['district'] ?? '';
    $type = $_POST['type'] ?? 'public';
    $tags = trim($_POST['tags'] ?? '');

    // Validate
    if (empty($name)) {
        $error = 'Community name is required';
    } elseif (strlen($name) < 3) {
        $error = 'Community name must be at least 3 characters';
    } elseif (strlen($name) > 100) {
        $error = 'Community name cannot exceed 100 characters';
    } else {
        // Check if slug exists
        $slug = createSlug($name);
        $db = db();
        $stmt = $db->prepare("SELECT id FROM communities WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $error = 'A community with this name already exists';
        } else {
            // Create community
            $data = [
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'district' => $district,
                'tags' => $tags,
                'type' => $type
            ];

            $result = createCommunity($data);

            if ($result['success']) {
                $success = 'Community created successfully!';
                $communityId = $result['community_id'];
                $inviteCode = $result['invite_code'];

                // Redirect to community view
                header('Location: view.php?id=' . $communityId . '&created=1');
                exit;
            } else {
                $error = $result['error'] ?? 'Failed to create community';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-4" style="max-width: 700px;">
    <div class="card border-0 shadow-lg">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-people display-1 text-primary"></i>
                <h2 class="fw-bold mt-2">Create a Community</h2>
                <p class="text-secondary">Bring people together around shared interests</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Community Name -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Community Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-tag"></i></span>
                        <input type="text" class="form-control" name="name"
                               placeholder="e.g., StudyHub Malawi"
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <small class="text-secondary">Choose a unique name that represents your community</small>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <textarea class="form-control" name="description" rows="4"
                              placeholder="Tell people what your community is about..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <!-- Category -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Category</label>
                    <select class="form-select" name="category">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo ($_POST['category'] ?? '') === $cat ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- District -->
                <div class="mb-3">
                    <label class="form-label fw-bold">District / Region</label>
                    <select class="form-select" name="district">
                        <option value="">Select a district...</option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?php echo $district; ?>" <?php echo ($_POST['district'] ?? '') === $district ? 'selected' : ''; ?>>
                                <?php echo $district; ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other" <?php echo ($_POST['district'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <!-- Tags -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Tags</label>
                    <input type="text" class="form-control" name="tags"
                           placeholder="e.g., study, students, motivation"
                           value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                    <small class="text-secondary">Separate tags with commas</small>
                </div>

                <!-- Community Type -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Community Type</label>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" value="public" id="typePublic" <?php echo ($_POST['type'] ?? 'public') === 'public' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="typePublic">
                                    <i class="bi bi-globe text-success"></i> Public
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" value="private" id="typePrivate" <?php echo ($_POST['type'] ?? '') === 'private' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="typePrivate">
                                    <i class="bi bi-lock text-warning"></i> Private
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" value="district" id="typeDistrict" <?php echo ($_POST['type'] ?? '') === 'district' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="typeDistrict">
                                    <i class="bi bi-geo-alt text-info"></i> District
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-plus-lg me-1"></i> Create Community
                </button>
            </form>

            <hr class="my-4">

            <p class="text-center mb-0">
                <a href="<?php echo SITE_URL; ?>community/" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Back to Communities
                </a>
            </p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
