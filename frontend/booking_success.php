<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Check if user came from booking process
if (!isset($_SESSION['booking_success']) || !isset($_SESSION['booking_id'])) {
    header("Location: index.php");
    exit;
}

$booking_id = $_SESSION['booking_id'];

// Get booking details
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.booking_reference, b.total_amount, b.booking_date, b.status,
               s.date, s.time, s.price,
               m.id as movie_id, m.title as movie_title, m.poster, m.duration,
               t.name as theater_name, t.location
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        header("Location: index.php");
        exit;
    }

    // Get booked seats
    $stmt = $pdo->prepare("
        SELECT seat_row, seat_column
        FROM booking_seats
        WHERE booking_id = ?
        ORDER BY seat_row, seat_column
    ");
    $stmt->execute([$booking_id]);
    $seats = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching booking details: " . $e->getMessage();
}

// Get payment info if available
$payment_info = isset($_SESSION['payment_info']) ? $_SESSION['payment_info'] : null;

// Clear the session variables
unset($_SESSION['booking_success']);
unset($_SESSION['booking_id']);
unset($_SESSION['payment_info']);

$pageTitle = "Booking Confirmation - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card bg-dark text-light shadow">
                    <div class="card-header bg-success text-white">
                        <h3 class="card-title mb-0">
                            <i class="fa fa-check-circle me-2"></i> Booking Successful!
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="success-animation">
                                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                    <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                                    <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                                </svg>
                            </div>
                            <h4 class="mt-3">Your booking has been confirmed!</h4>
                            <p class="text-muted">Booking Reference: <strong><?= htmlspecialchars($booking['booking_reference']) ?></strong></p>
                            <?php if ($payment_info): ?>
                            <p class="mt-2 text-success">
                                <i class="fa fa-check-circle"></i> Payment Successful via <?= $payment_info['method'] === 'card' ? 'Credit/Debit Card' : 'UPI' ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Ticket Preview -->
                        <div class="ticket-preview mb-4">
                            <div class="ticket">
                                <div class="ticket-header d-flex">
                                    <div class="movie-poster">
                                        <?php
                                        $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $booking['poster'];
                                        if (!empty($booking['poster']) && file_exists($poster_path)): 
                                        ?>
                                            <img src="../uploads/movie_posters/<?= htmlspecialchars($booking['poster']) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($booking['movie_title']) ?> Poster">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/350x500?text=No+Poster" class="img-fluid rounded" alt="<?= htmlspecialchars($booking['movie_title']) ?> Poster">
                                        <?php endif; ?>
                                    </div>
                                    <div class="ms-3">
                                        <h5 class="movie-title"><?= htmlspecialchars($booking['movie_title']) ?></h5>
                                        <p class="mb-1">
                                            <i class="fa fa-calendar-alt me-1 text-primary"></i> 
                                            <?= date('l, F d, Y', strtotime($booking['date'])) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fa fa-clock me-1 text-primary"></i>
                                            <?= date('h:i A', strtotime($booking['time'])) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fa fa-building me-1 text-primary"></i>
                                            <?= htmlspecialchars($booking['theater_name']) ?>
                                        </p>
                                        <p class="mb-0">
                                            <i class="fa fa-map-marker-alt me-1 text-primary"></i>
                                            <?= htmlspecialchars($booking['location']) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="ticket-details mt-3">
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Seats:</strong></p>
                                            <p>
                                                <?php
                                                $formatted_seats = [];
                                                foreach ($seats as $seat) {
                                                    $formatted_seats[] = chr(64 + $seat['seat_row']) . $seat['seat_column'];
                                                }
                                                echo implode(', ', $formatted_seats);
                                                ?>
                                            </p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Amount Paid:</strong></p>
                                            <p>₹<?= number_format($booking['total_amount'], 2) ?></p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Booking ID:</strong></p>
                                            <p><?= htmlspecialchars($booking['booking_reference']) ?></p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Booking Date:</strong></p>
                                            <p><?= date('d M Y, h:i A', strtotime($booking['booking_date'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="ticket-footer mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        
                                        <div class="text-center">
                                            <p class="mb-0">Thank you for booking with <?= SITE_NAME ?>!</p>
                                            <small class="text-muted">Please show this ticket at the entrance.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-center mt-4">
                            <a href="view_ticket.php?id=<?= $booking_id ?>" class="btn btn-primary me-2">
                                <i class="fa fa-ticket-alt me-1"></i> View Ticket
                            </a>
                            <a href="index.php" class="btn btn-outline-light me-2">
                                <i class="fa fa-home me-1"></i> Home
                            </a>
                            <a href="bookings.php" class="btn btn-outline-light">
                                <i class="fa fa-list me-1"></i> My Bookings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
