<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$errors = [];
$success = false;

// Get user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: ../auth/logout.php");
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
    
    // Validate input
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif ($email !== $user['email']) {
        // Check if new email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email is already registered";
        }
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    // Update user if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $phone, $_SESSION['user_id']]);
            
            // Update session variables
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            $success = true;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }
}

$pageTitle = "Edit Profile - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Edit Profile</h3>
                        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa fa-check-circle me-2"></i> Your profile has been updated successfully!
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
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="created_at" class="form-label">Member Since</label>
                            <input type="text" class="form-control" id="created_at" value="<?= date('F d, Y', strtotime($user['created_at'])) ?>" readonly>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save me-1"></i> Update Profile
                            </button>
                            <a href="change_password.php" class="btn btn-outline-light">
                                <i class="fa fa-key me-1"></i> Change Password
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
