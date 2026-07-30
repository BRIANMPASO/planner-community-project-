<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $db = db();

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                $passwordHash = hashPassword($password);
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");

                if ($stmt->execute([$username, $email, $passwordHash, $fullName])) {
                    $success = 'Registration successful! You can now login.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Pamodzi Planner</title>
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
            max-width: 460px;
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
                <p>Join the community for free</p>
                <span class="community-badge"><i class="bi bi-people me-1"></i> Community Planner</span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <a href="<?php echo SITE_URL; ?>login.php" class="fw-bold" style="color: var(--blue); text-decoration: none;">Sign in now</a>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="Choose a username" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name (Optional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                        <input type="text" class="form-control" name="full_name" placeholder="Enter your full name">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password" placeholder="Min 6 characters" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm password" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </form>

            <div class="divider">or</div>

            <p class="text-center mb-0" style="font-size: 0.9rem; color: var(--text-muted);">
                Already have an account?
                <a href="<?php echo SITE_URL; ?>login.php" style="color: var(--blue); font-weight: 600; text-decoration: none;">
                    Sign in
                </a>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
