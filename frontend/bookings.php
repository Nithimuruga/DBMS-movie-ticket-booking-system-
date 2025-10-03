<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Set default filter to "upcoming"
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

// Get booking list based on filter
try {
    $query = "
        SELECT b.id, b.booking_reference, b.total_amount, b.booking_date, b.status, b.cancelled_at,
               s.date, s.time,
               m.title as movie_title, m.poster,
               t.name as theater_name, t.location,
               COUNT(bs.id) as seat_count
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        JOIN booking_seats bs ON b.id = bs.booking_id
        WHERE b.user_id = ?
    ";
    
    // Add filter conditions
    switch ($filter) {
        case 'upcoming':
            $query .= " AND b.status = 'confirmed' AND s.date >= CURDATE()";
            break;
        case 'past':
            $query .= " AND b.status = 'confirmed' AND s.date < CURDATE()";
            break;
        case 'cancelled':
            $query .= " AND b.status = 'cancelled'";
            break;
        case 'all':
            // No additional filter
            break;
        default:
            $query .= " AND b.status = 'confirmed' AND s.date >= CURDATE()";
            break;
    }
    
    $query .= " GROUP BY b.id ORDER BY s.date DESC, s.time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching bookings: " . $e->getMessage();
}

$pageTitle = "My Bookings - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">My Bookings</h3>
                        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Tabs -->
                    <ul class="nav nav-tabs nav-fill mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?= $filter === 'upcoming' ? 'active' : '' ?>" href="bookings.php?filter=upcoming">
                                <i class="fa fa-calendar-check me-1"></i> Upcoming
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $filter === 'past' ? 'active' : '' ?>" href="bookings.php?filter=past">
                                <i class="fa fa-history me-1"></i> Past
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $filter === 'cancelled' ? 'active' : '' ?>" href="bookings.php?filter=cancelled">
                                <i class="fa fa-times-circle me-1"></i> Cancelled
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="bookings.php?filter=all">
                                <i class="fa fa-list me-1"></i> All Bookings
                            </a>
                        </li>
                    </ul>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php elseif (empty($bookings)): ?>
                        <div class="text-center py-5">
                            <i class="fa fa-ticket-alt fa-3x text-muted mb-3"></i>
                            <h4>No bookings found</h4>
                            <?php if ($filter === 'upcoming'): ?>
                                <p class="text-muted">You don't have any upcoming bookings.</p>
                                <a href="index.php" class="btn btn-primary mt-2">Browse Movies</a>
                            <?php else: ?>
                                <p class="text-muted">No bookings found for the selected filter.</p>
                                <a href="bookings.php" class="btn btn-primary mt-2">View All Bookings</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Movie</th>
                                        <th>Date & Time</th>
                                        <th>Theater</th>
                                        <th>Seats</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <span class="booking-ref"><?= htmlspecialchars($booking['booking_reference']) ?></span><br>
                                                <small class="text-muted"><?= date('d/m/Y', strtotime($booking['booking_date'])) ?></small>
                                            </td>
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
                                                <?php if ($booking['status'] === 'confirmed'): ?>
                                                    <?php if (strtotime($booking['date'] . ' ' . $booking['time']) < time()): ?>
                                                        <span class="badge bg-secondary">Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Confirmed</span>
                                                    <?php endif; ?>
                                                <?php elseif ($booking['status'] === 'cancelled'): ?>
                                                    <span class="badge bg-danger">Cancelled</span><br>
                                                    <small class="text-muted"><?= date('d/m/Y', strtotime($booking['cancelled_at'])) ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><?= ucfirst($booking['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex">
                                                    <a href="view_ticket.php?id=<?= $booking['id'] ?>" class="btn btn-outline-primary btn-sm me-1" title="View Ticket">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($booking['status'] === 'confirmed' && strtotime($booking['date'] . ' ' . $booking['time']) > time()): ?>
                                                        <a href="cancel_ticket.php?id=<?= $booking['id'] ?>" class="btn btn-outline-danger btn-sm" title="Cancel Booking">
                                                            <i class="fa fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
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
