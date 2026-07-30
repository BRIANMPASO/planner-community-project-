<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        header('Location: index.php');
        exit;
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
    <title>Sign In — Pamodzi Planner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(160deg, #F5F9FF 0%, #E8F0FE 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        :root {
            --navy: #0B2647;
            --blue: #2D7BDE;
            --blue-light: #6BA6FF;
            --text-muted: #5A6B7E;
            --border-light: #E6EDF6;
            --gradient-blue: linear-gradient(135deg, #2D7BDE 0%, #6BA6FF 100%);
        }
        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            border: 1px solid var(--border-light);
            box-shadow: 0 20px 60px rgba(11, 38, 71, 0.08);
        }
        .login-card .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-card .logo svg {
            height: 64px;
            width: 64px;
        }
        .login-card .logo h3 {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--navy);
            margin-top: 0.5rem;
        }
        .login-card .logo h3 span {
            color: var(--blue);
        }
        .login-card .logo p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .login-card .form-control {
            border: 2px solid var(--border-light);
            border-radius: 10px;
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .login-card .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(45, 123, 222, 0.1);
        }
        .login-card .input-group-text {
            background: #f8fafc;
            border: 2px solid var(--border-light);
            border-right: none;
            color: var(--text-muted);
            border-radius: 10px 0 0 10px;
        }
        .login-card .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }
        .btn-login {
            background: var(--gradient-blue);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.8rem;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(45, 123, 222, 0.3);
        }
        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-light);
        }
        .alert {
            border-radius: 10px;
        }
        .community-badge {
            display: inline-block;
            background: rgba(45, 123, 222, 0.1);
            color: var(--blue);
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        @media (max-width: 576px) {
            .login-card {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="logo">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <rect x="24" y="22" width="16" height="56" rx="6" fill="#0B2647"/>
                    <path d="M 40 22 H 58 C 74 22, 78 46, 69 56 C 60 66, 40 64, 40 64 Z" fill="#0B2647"/>
                    <circle cx="80" cy="28" r="6" fill="#2D7BDE"/>
                    <circle cx="80" cy="28" r="3" fill="#ffffff"/>
                    <line x1="72" y1="32" x2="62" y2="42" stroke="#2D7BDE" stroke-width="1.5" opacity="0.3"/>
                    <circle cx="48" cy="44" r="2" fill="#2D7BDE" opacity="0.4"/>
                </svg>
                <h3>Pam<span>odzi</span></h3>
                <p>Sign in to your planner</p>
                <span class="community-badge"><i class="bi bi-people me-1"></i> Community Planner</span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="Enter username or email" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <div class="divider">or</div>

            <p class="text-center mb-0" style="font-size: 0.9rem; color: var(--text-muted);">
                Don't have an account?
                <a href="<?php echo SITE_URL; ?>register.php" style="color: var(--blue); font-weight: 600; text-decoration: none;">
                    Create one free
                </a>
            </p>
           <!-- NEW: Back to Home button added here -->
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>../" class="btn btn-outline-secondary btn-sm" style="width: 100%;">
                    <i class="bi bi-house me-1"></i> Back to Home
                </a>
            </div>

            <p class="text-center mt-3" style="font-size: 0.75rem; color: var(--text-muted);">
                <i class="bi bi-shield-check me-1"></i> Secure &amp; free forever
            </p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
