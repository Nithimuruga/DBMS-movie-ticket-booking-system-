<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Get dashboard statistics
try {
    // Total users count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
    $stmt->execute([ROLE_USER]);
    $total_users = $stmt->fetchColumn();
    
    // Total movies count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM movies");
    $stmt->execute();
    $total_movies = $stmt->fetchColumn();
    
    // Total theaters count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM theaters");
    $stmt->execute();
    $total_theaters = $stmt->fetchColumn();
    
    // Total bookings count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings");
    $stmt->execute();
    $total_bookings = $stmt->fetchColumn();
    
    // Total revenue
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bookings WHERE status = 'confirmed'");
    $stmt->execute();
    $total_revenue = $stmt->fetchColumn() ?: 0;
    
    // Today's bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE DATE(booking_date) = CURDATE()");
    $stmt->execute();
    $today_bookings = $stmt->fetchColumn();
    
    // Today's revenue
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bookings WHERE DATE(booking_date) = CURDATE() AND status = 'confirmed'");
    $stmt->execute();
    $today_revenue = $stmt->fetchColumn() ?: 0;
    
    // Recent bookings
    $stmt = $pdo->prepare("
        SELECT b.id, b.booking_reference, b.total_amount, b.booking_date, b.status,
               u.name as user_name,
               m.title as movie_title,
               t.name as theater_name,
               s.date as show_date, s.time as show_time
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN showtimes s ON b.showtime_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        ORDER BY b.booking_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_bookings = $stmt->fetchAll();
    
    // Upcoming shows
    $stmt = $pdo->prepare("
        SELECT s.id, s.date, s.time, s.price,
               m.title as movie_title,
               t.name as theater_name,
               (SELECT COUNT(*) FROM bookings WHERE showtime_id = s.id AND status = 'confirmed') as booking_count
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        WHERE s.date >= CURDATE()
        ORDER BY s.date, s.time
        LIMIT 5
    ");
    $stmt->execute();
    $upcoming_shows = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error fetching dashboard data: " . $e->getMessage();
}

$pageTitle = "Admin Dashboard - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card bg-dark text-light">
                <div class="card-body">
                    <h1 class="card-title">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
                    <p class="card-text">This is your admin dashboard for <?= SITE_NAME ?>. Here you can manage all aspects of the movie booking system.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php else: ?>
        <!-- Stats Cards Row -->
        <div class="row mb-4">
            <!-- Users Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Users</h5>
                                <h2 class="mb-0"><?= number_format($total_users) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="manage_users.php" class="text-white">View Details</a>
                        <i class="fas fa-angle-right text-white"></i>
                    </div>
                </div>
            </div>
            
            <!-- Movies Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Movies</h5>
                                <h2 class="mb-0"><?= number_format($total_movies) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-film fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="manage_movies.php" class="text-white">View Details</a>
                        <i class="fas fa-angle-right text-white"></i>
                    </div>
                </div>
            </div>
            
            <!-- Theaters Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Theaters</h5>
                                <h2 class="mb-0"><?= number_format($total_theaters) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-building fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="manage_theaters.php" class="text-white">View Details</a>
                        <i class="fas fa-angle-right text-white"></i>
                    </div>
                </div>
            </div>
            
            <!-- Bookings Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Bookings</h5>
                                <h2 class="mb-0"><?= number_format($total_bookings) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-ticket-alt fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="manage_bookings.php" class="text-white">View Details</a>
                        <i class="fas fa-angle-right text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Revenue Cards Row -->
        <div class="row mb-4">
            <!-- Total Revenue Card -->
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card bg-dark text-light h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Revenue</h5>
                                <h2 class="mb-0">₹<?= number_format($total_revenue, 2) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-rupee-sign fa-3x text-success opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Today's Bookings Card -->
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card bg-dark text-light h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Today's Bookings</h5>
                                <h2 class="mb-0"><?= number_format($today_bookings) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-calendar-day fa-3x text-primary opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Today's Revenue Card -->
            <div class="col-xl-4 col-md-12 mb-4">
                <div class="card bg-dark text-light h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Today's Revenue</h5>
                                <h2 class="mb-0">₹<?= number_format($today_revenue, 2) ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-hand-holding-usd fa-3x text-warning opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Row -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-dark text-light">
                    <div class="card-header bg-primary">
                        <h4 class="card-title mb-0">Quick Actions</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="add_movie.php" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-film me-2"></i> Add New Movie
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="add_theater.php" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-building me-2"></i> Add New Theater
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="add_showtime.php" class="btn btn-warning btn-lg w-100 text-dark">
                                    <i class="fas fa-clock me-2"></i> Add New Showtime
                                </a>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <a href="manage_bookings.php" class="btn btn-danger btn-lg w-100">
                                    <i class="fas fa-ticket-alt me-2"></i> Manage Bookings
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6 col-md-6 mb-3">
                                <a href="theater_reports.php" class="btn btn-secondary btn-lg w-100">
                                    <i class="fas fa-chart-bar me-2"></i> Theater Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Data Row -->
        <div class="row">
            <!-- Recent Bookings -->
            <div class="col-lg-6 mb-4">
                <div class="card bg-dark text-light h-100">
                    <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Recent Bookings</h4>
                        <a href="manage_bookings.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_bookings)): ?>
                            <div class="text-center py-4">
                                <p class="mb-0">No recent bookings found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Ref #</th>
                                            <th>User</th>
                                            <th>Movie</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($booking['booking_reference']) ?></td>
                                                <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                                <td><?= htmlspecialchars($booking['movie_title']) ?></td>
                                                <td>₹<?= number_format($booking['total_amount'], 2) ?></td>
                                                <td>
                                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                                        <span class="badge bg-success">Confirmed</span>
                                                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                                                        <span class="badge bg-danger">Cancelled</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark"><?= ucfirst($booking['status']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Shows -->
            <div class="col-lg-6 mb-4">
                <div class="card bg-dark text-light h-100">
                    <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Upcoming Shows</h4>
                        <a href="manage_showtimes.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($upcoming_shows)): ?>
                            <div class="text-center py-4">
                                <p class="mb-0">No upcoming shows found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Movie</th>
                                            <th>Theater</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Bookings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_shows as $show): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($show['movie_title']) ?></td>
                                                <td><?= htmlspecialchars($show['theater_name']) ?></td>
                                                <td><?= date('d M Y', strtotime($show['date'])) ?></td>
                                                <td><?= date('h:i A', strtotime($show['time'])) ?></td>
                                                <td><span class="badge bg-info"><?= $show['booking_count'] ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/admin_footer.php'; ?>
