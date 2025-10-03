<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Verify current password
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect";
            }
        } catch (PDOException $e) {
            $errors[] = "Error verifying password: " . $e->getMessage();
        }
    }
    
    // Update password if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Error updating password: " . $e->getMessage();
        }
    }
}

$pageTitle = "Change Password - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Change Password</h3>
                        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa fa-check-circle me-2"></i> Your password has been updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="passwordForm">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                            <small class="form-text text-muted">Password must be at least 6 characters long</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="password-strength">
                                <label class="form-label">Password Strength:</label>
                                <div class="progress">
                                    <div id="password-strength-meter" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div id="password-strength-text" class="mt-1 small"></div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-key me-1"></i> Change Password
                            </button>
                            <a href="profile.php" class="btn btn-outline-light">
                                <i class="fa fa-user me-1"></i> Back to Profile
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('new_password');
    const strengthMeter = document.getElementById('password-strength-meter');
    const strengthText = document.getElementById('password-strength-text');
    
    passwordInput.addEventListener('input', function() {
        const password = passwordInput.value;
        let strength = 0;
        
        // Calculate strength
        if (password.length >= 8) strength += 20;
        if (password.match(/[a-z]+/)) strength += 20;
        if (password.match(/[A-Z]+/)) strength += 20;
        if (password.match(/[0-9]+/)) strength += 20;
        if (password.match(/[^a-zA-Z0-9]+/)) strength += 20;
        
        // Update the meter
        strengthMeter.style.width = strength + '%';
        
        // Update colors and text
        if (strength <= 20) {
            strengthMeter.className = 'progress-bar bg-danger';
            strengthText.innerHTML = 'Very Weak';
        } else if (strength <= 40) {
            strengthMeter.className = 'progress-bar bg-warning';
            strengthText.innerHTML = 'Weak';
        } else if (strength <= 60) {
            strengthMeter.className = 'progress-bar bg-info';
            strengthText.innerHTML = 'Medium';
        } else if (strength <= 80) {
            strengthMeter.className = 'progress-bar bg-primary';
            strengthText.innerHTML = 'Strong';
        } else {
            strengthMeter.className = 'progress-bar bg-success';
            strengthText.innerHTML = 'Very Strong';
        }
    });
    
    // Form validation
    const confirmInput = document.getElementById('confirm_password');
    const form = document.getElementById('passwordForm');
    
    form.addEventListener('submit', function(e) {
        if (passwordInput.value !== confirmInput.value) {
            e.preventDefault();
            alert('Passwords do not match!');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
