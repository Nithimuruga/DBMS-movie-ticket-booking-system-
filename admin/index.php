<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] == ROLE_ADMIN) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];
$email = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    }

    // If no validation errors, proceed with login
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? AND role = ?");
            $stmt->execute([$email, ROLE_ADMIN]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Store user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                // Redirect to admin dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid email or password";
            }
        } catch (PDOException $e) {
            $errors[] = "Login error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card bg-dark text-light shadow">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h2 class="mb-0"><?= SITE_NAME ?> Admin</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-shield fa-4x text-primary mb-3"></i>
                            <h3>Admin Login</h3>
                            <p class="text-muted">Please enter your credentials to continue</p>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer bg-dark text-center py-3">
                        <a href="../frontend/index.php" class="text-light">
                            <i class="fas fa-arrow-left me-1"></i> Back to Website
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
