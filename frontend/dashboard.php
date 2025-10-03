<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Get user info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error fetching user information: " . $e->getMessage();
}

// Get upcoming bookings
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.booking_reference, b.total_amount, b.booking_date, b.status,
               s.date, s.time,
               m.title as movie_title, m.poster,
               t.name as theater_name, t.location,
               COUNT(bs.id) as seat_count
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        JOIN booking_seats bs ON b.id = bs.booking_id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND s.date >= CURDATE()
        GROUP BY b.id
        ORDER BY s.date, s.time
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $upcoming_bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching upcoming bookings: " . $e->getMessage();
}

// Get booking statistics
try {
    // Total bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_bookings = $stmt->fetchColumn();
    
    // Active bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND s.date >= CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $active_bookings = $stmt->fetchColumn();
    
    // Past bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND s.date < CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $past_bookings = $stmt->fetchColumn();
    
    // Cancelled bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'cancelled'");
    $stmt->execute([$_SESSION['user_id']]);
    $cancelled_bookings = $stmt->fetchColumn();
    
    // Total amount spent
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bookings WHERE user_id = ? AND status = 'confirmed'");
    $stmt->execute([$_SESSION['user_id']]);
    $total_spent = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $error = "Error fetching booking statistics: " . $e->getMessage();
}

$pageTitle = "Dashboard - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- User Profile Summary -->
        <div class="col-lg-4 mb-4">
            <div class="card bg-dark text-light shadow h-100">
                <div class="card-header bg-primary">
                    <h4 class="mb-0">My Profile</h4>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mx-auto">
                            <span class="avatar-initials">
                                <?= substr($user['name'], 0, 1) ?>
                            </span>
                        </div>
                        <h4 class="mt-3"><?= htmlspecialchars($user['name']) ?></h4>
                        <p class="text-muted mb-0"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                    
                    <div class="user-info">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phone:</span>
                            <span><?= htmlspecialchars($user['phone']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Member Since:</span>
                            <span><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="profile.php" class="btn btn-outline-light">
                            <i class="fa fa-user-edit me-1"></i> Edit Profile
                        </a>
                        <a href="change_password.php" class="btn btn-outline-light">
                            <i class="fa fa-key me-1"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Statistics -->
        <div class="col-lg-8 mb-4">
            <div class="card bg-dark text-light shadow h-100">
                <div class="card-header bg-primary">
                    <h4 class="mb-0">Booking Statistics</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="stat-card bg-primary-subtle text-white p-3 rounded shadow">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?= $total_bookings ?></h2>
                                        <p class="mb-0">Total Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fa fa-ticket-alt fa-2x text-primary-light"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="stat-card bg-success-subtle text-white p-3 rounded shadow">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?= $active_bookings ?></h2>
                                        <p class="mb-0">Upcoming Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fa fa-calendar-check fa-2x text-success-light"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="stat-card bg-secondary-subtle text-white p-3 rounded shadow">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?= $past_bookings ?></h2>
                                        <p class="mb-0">Past Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fa fa-history fa-2x text-secondary-light"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="stat-card bg-danger-subtle text-white p-3 rounded shadow">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?= $cancelled_bookings ?></h2>
                                        <p class="mb-0">Cancelled Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fa fa-times-circle fa-2x text-danger-light"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="total-spent mt-2 p-3 bg-secondary rounded shadow">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Total Amount Spent</h5>
                                <p class="mb-0 text-muted">On confirmed bookings</p>
                            </div>
                            <div>
                                <h3 class="mb-0">₹<?= number_format($total_spent, 2) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Bookings -->
    <div class="row">
        <div class="col-12">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Upcoming Bookings</h4>
                    <a href="bookings.php" class="btn btn-outline-light btn-sm">
                        View All <i class="fa fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_bookings)): ?>
                        <div class="text-center py-4">
                            <i class="fa fa-ticket-alt fa-3x text-muted mb-3"></i>
                            <h5>No upcoming bookings found</h5>
                            <p class="text-muted">Looks like you don't have any upcoming movie plans.</p>
                            <a href="index.php" class="btn btn-primary mt-2">Book a Movie</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Movie</th>
                                        <th>Date & Time</th>
                                        <th>Theater</th>
                                        <th>Seats</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="movie-thumb me-2">
                                                        <?php if (!empty($booking['poster']) && file_exists('../uploads/movie_posters/' . $booking['poster'])): ?>
                                                            <img src="../uploads/movie_posters/<?= htmlspecialchars($booking['poster']) ?>" alt="<?= htmlspecialchars($booking['movie_title']) ?>" class="img-fluid rounded">
                                                        <?php else: ?>
                                                            <img src="https://pixabay.com/get/g9b1bfb92eb5909a18295c806fd46359e6b805aff4c2a4832a233a336b3ea007ac0b0f0846275ea1f8fcc97879c1ab5c0b7e8f8e0b1f558489dd0b5573a1db525_1280.jpg" alt="<?= htmlspecialchars($booking['movie_title']) ?>" class="img-fluid rounded">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?= htmlspecialchars($booking['movie_title']) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?= date('d M Y', strtotime($booking['date'])) ?><br>
                                                <span class="text-muted"><?= date('h:i A', strtotime($booking['time'])) ?></span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($booking['theater_name']) ?><br>
                                                <span class="text-muted"><?= htmlspecialchars($booking['location']) ?></span>
                                            </td>
                                            <td><?= $booking['seat_count'] ?> seat(s)</td>
                                            <td>₹<?= number_format($booking['total_amount'], 2) ?></td>
                                            <td>
                                                <div class="d-flex">
                                                    <a href="view_ticket.php?id=<?= $booking['id'] ?>" class="btn btn-outline-primary btn-sm me-1" title="View Ticket">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                    <a href="cancel_ticket.php?id=<?= $booking['id'] ?>" class="btn btn-outline-danger btn-sm" title="Cancel Booking">
                                                        <i class="fa fa-times"></i>
                                                    </a>
                                                </div>
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
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
