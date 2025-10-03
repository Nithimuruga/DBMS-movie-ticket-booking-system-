<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'Admin Dashboard - ' . SITE_NAME ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body class="admin-body bg-dark text-light">
    <!-- Admin Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <span class="text-primary fw-bold"><?= SITE_NAME ?></span>
                <span class="ms-2 badge bg-primary">Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], 'movie') !== false ? 'active' : '' ?>" href="#" id="moviesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-film me-1"></i> Movies
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="moviesDropdown">
                            <li><a class="dropdown-item" href="add_movie.php">Add New Movie</a></li>
                            <li><a class="dropdown-item" href="manage_movies.php">Manage Movies</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], 'theater') !== false ? 'active' : '' ?>" href="#" id="theatersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-building me-1"></i> Theaters
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="theatersDropdown">
                            <li><a class="dropdown-item" href="add_theater.php">Add New Theater</a></li>
                            <li><a class="dropdown-item" href="manage_theaters.php">Manage Theaters</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], 'showtime') !== false ? 'active' : '' ?>" href="#" id="showtimesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-clock me-1"></i> Showtimes
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="showtimesDropdown">
                            <li><a class="dropdown-item" href="add_showtime.php">Add New Showtime</a></li>
                            <li><a class="dropdown-item" href="manage_showtimes.php">Manage Showtimes</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'bookings.php') !== false ? 'active' : '' ?>" href="manage_bookings.php">
                            <i class="fas fa-ticket-alt me-1"></i> Bookings
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], 'users.php') !== false || strpos($_SERVER['PHP_SELF'], 'user_') !== false ? 'active' : '' ?>" href="#" id="usersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-users me-1"></i> Users
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="usersDropdown">
                            <li><a class="dropdown-item" href="manage_users.php">Manage Users</a></li>
                            <li><a class="dropdown-item" href="add_user.php">Add New User</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-key me-2"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= SITE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="admin-main">
