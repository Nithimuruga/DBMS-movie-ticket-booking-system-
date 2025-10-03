<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

$errors = [];
$name = '';
$email = '';
$phone = '';
$role = 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = isset($_POST['role']) ? (int)$_POST['role'] : 0;

    // Validate input
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email is already registered";
        }
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // If no validation errors, proceed with user creation
    if (empty($errors)) {
        try {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert the new user into the database
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $phone, $hashed_password, $role]);
            
            // Redirect to manage users page with success message
            $_SESSION['user_added'] = true;
            header("Location: manage_users.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error creating user: " . $e->getMessage();
        }
    }
}

$pageTitle = "Add New User - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Add New User</h4>
                    <a href="manage_users.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Users
                    </a>
                </div>
                <div class="card-body">
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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                                <small class="form-text text-muted">Enter a valid 10-15 digit phone number</small>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">User Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="0" <?= $role === 0 ? 'selected' : '' ?>>Regular User</option>
                                    <option value="1" <?= $role === 1 ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">Must be at least 6 characters long</small>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/admin_footer.php'; ?>
