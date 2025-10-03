<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top border-bottom border-secondary">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>/frontend/index.php">
            <span class="text-primary fw-bold"><?= SITE_NAME ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'index.php') !== false ? 'active' : '' ?>" href="<?= SITE_URL ?>/frontend/index.php">Home</a>
                </li>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] == ROLE_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/admin/dashboard.php">Admin Dashboard</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/frontend/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/frontend/bookings.php"><i class="fas fa-ticket-alt me-2"></i> My Bookings</a></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/frontend/profile.php"><i class="fas fa-user-edit me-2"></i> Edit Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex">
                        <a href="<?= SITE_URL ?>./index.php" class="btn btn-outline-light me-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                        <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
