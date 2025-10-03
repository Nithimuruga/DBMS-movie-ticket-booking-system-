<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../frontend/index.php");
    exit;
}

$errors = [];
$name = '';
$email = '';
$phone = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

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

    // If no validation errors, proceed with registration
    if (empty($errors)) {
        try {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert the new user into the database
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $phone, $hashed_password, ROLE_USER]);
            
            // Redirect to login page with success message
            $_SESSION['registration_success'] = true;
            header("Location: ../index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Registration error: " . $e->getMessage();
        }
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title text-center mb-0">Create an Account</h3>
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
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="form-text text-muted">Must be at least 6 characters long</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Register</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Already have an account? <a href="../index.php" class="text-primary">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
