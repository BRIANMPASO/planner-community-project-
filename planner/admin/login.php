<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in as admin
if (isLoggedIn() && isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $secret = $_POST['secret'] ?? '';

    // Check secret key for admin access
    if ($secret !== 'PAMODZI_ADMIN_2024') {
        $error = 'Invalid access key';
    } elseif (login($username, $password)) {
        if (isAdmin()) {
            header('Location: index.php');
            exit;
        } else {
            // If user is not admin, logout
            logout();
            $error = 'Access denied. Admin privileges required.';
        }
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0B2647 0%, #1a3a5c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Inter', -apple-system, sans-serif;
        }
        .admin-login-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .brand-badge {
            background: #0B2647;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="admin-login-card p-5">
                    <div class="text-center mb-4">
                        <span class="brand-badge">
                            <i class="bi bi-shield-lock me-2"></i>PAMODZI ADMIN
                        </span>
                        <h3 class="fw-bold mt-3">Admin Access</h3>
                        <p class="text-secondary">Enter your credentials to access the admin panel</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="secret" class="form-label">Admin Secret Key</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-key"></i>
                                </span>
                                <input type="password" class="form-control" id="secret" name="secret"
                                       placeholder="Enter admin secret key" required>
                            </div>
                            <small class="text-secondary">Contact system administrator for the secret key</small>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login to Admin
                        </button>
                    </form>

                    <hr class="my-4">

                    <p class="text-center mb-0">
                        <a href="<?php echo SITE_URL; ?>index.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>Return to Dashboard
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
