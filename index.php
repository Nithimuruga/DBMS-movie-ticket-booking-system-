<?php
require_once './config/config.php';
require_once './includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ./frontend/index.php");
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
            $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Store user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                // Redirect based on role
                if ($user['role'] == ROLE_ADMIN) {
                    header("Location: ./admin/dashboard.php");
                } else {
                    header("Location: ./frontend/index.php");
                }
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

<?php require_once './includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title text-center mb-0">Login to <?= SITE_NAME ?></h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['registration_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Registration successful! Please login with your credentials.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['registration_success']); ?>
                    <?php endif; ?>
                    
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
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Don't have an account? <a href="auth/register.php" class="text-primary">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once './includes/footer.php'; ?>
