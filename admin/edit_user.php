<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit;
}

$user_id = (int)$_GET['id'];
$errors = [];

// Get user details
try {
    $stmt = $pdo->prepare("SELECT id, name, email, phone, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: manage_users.php");
        exit;
    }
} catch (PDOException $e) {
    $errors[] = "Error fetching user details: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = isset($_POST['role']) ? (int)$_POST['role'] : 0;
    $change_password = isset($_POST['change_password']) && $_POST['change_password'] == 1;
    
    // Validate input
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists (except for current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email is already registered by another user";
        }
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    // Password validation (only if changing password)
    $password = '';
    if ($change_password) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
    
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // If current user is the only admin and trying to change role to non-admin
    if ($user_id == $_SESSION['user_id'] && $user['role'] == 1 && $role != 1) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 1");
        $stmt->execute();
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count <= 1) {
            $errors[] = "Cannot change role - you are the only admin user";
            $role = 1; // Reset the role
        }
    }

    // If no validation errors, update the user
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($change_password) {
                // Update user with new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $hashed_password, $user_id]);
            } else {
                // Update user without changing password
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $role, $user_id]);
            }
            
            $pdo->commit();
            
            // Update session info if editing current user
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;
            }
            
            $_SESSION['user_updated'] = true;
            header("Location: view_user.php?id=" . $user_id);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Error updating user: " . $e->getMessage();
        }
    }
} else {
    // Pre-fill form fields
    $name = $user['name'];
    $email = $user['email'];
    $phone = $user['phone'];
    $role = $user['role'];
}

$pageTitle = "Edit User - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Edit User</h4>
                    <div>
                        <a href="view_user.php?id=<?= $user_id ?>" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-eye me-1"></i> View User
                        </a>
                        <a href="manage_users.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Users
                        </a>
                    </div>
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

                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $user_id ?>">
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
                        
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="change_password" name="change_password" value="1">
                            <label class="form-check-label" for="change_password">Change Password</label>
                        </div>
                        
                        <div id="password_fields" class="row mb-3" style="display: none;">
                            <div class="col-md-6">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <small class="form-text text-muted">Must be at least 6 characters long</small>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password fields visibility
    const changePasswordCheckbox = document.getElementById('change_password');
    const passwordFields = document.getElementById('password_fields');
    
    changePasswordCheckbox.addEventListener('change', function() {
        passwordFields.style.display = this.checked ? 'flex' : 'none';
        
        // Remove required attribute when fields are hidden
        const passwordInputs = passwordFields.querySelectorAll('input');
        passwordInputs.forEach(input => {
            input.required = this.checked;
        });
    });
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
