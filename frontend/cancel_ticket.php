<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = false;
$error = null;

// Validate booking exists and belongs to user
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.booking_reference, b.status, b.total_amount, b.booking_date,
               s.date as showtime_date, s.time as showtime_time,
               m.title as movie_title
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        JOIN movies m ON s.movie_id = m.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $error = "Booking not found or you don't have permission to cancel it.";
    } elseif ($booking['status'] === 'cancelled') {
        $error = "This booking has already been cancelled.";
    } else {
        // Check if the showtime has already passed
        $showtime_datetime = date('Y-m-d H:i:s', strtotime($booking['showtime_date'] . ' ' . $booking['showtime_time']));
        if (strtotime($showtime_datetime) < time()) {
            $error = "Cannot cancel a booking for a showtime that has already passed.";
        }
        
        // Check if it's within 2 hours of the showtime
        $hours_diff = (strtotime($showtime_datetime) - time()) / 3600;
        if ($hours_diff < 2) {
            $error = "Bookings can only be cancelled at least 2 hours before the showtime.";
        }
    }
} catch (PDOException $e) {
    $error = "Error retrieving booking: " . $e->getMessage();
}

// Process cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update booking status
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'cancelled', cancelled_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        $success = true;
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        $error = "Error cancelling booking: " . $e->getMessage();
    }
}

$pageTitle = "Cancel Booking - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary">
                    <h3 class="card-title mb-0">Cancel Booking</h3>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h4 class="alert-heading"><i class="fa fa-check-circle me-2"></i> Booking Cancelled!</h4>
                            <p>Your booking (Reference: <?= htmlspecialchars($booking['booking_reference']) ?>) has been successfully cancelled.</p>
                            <hr>
                            <p class="mb-0">If applicable, a refund will be processed according to our refund policy.</p>
                        </div>
                        <div class="text-center mt-4">
                            <a href="bookings.php" class="btn btn-primary me-2">
                                <i class="fa fa-list me-1"></i> My Bookings
                            </a>
                            <a href="index.php" class="btn btn-outline-light">
                                <i class="fa fa-home me-1"></i> Return to Home
                            </a>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <h4 class="alert-heading"><i class="fa fa-exclamation-circle me-2"></i> Cannot Cancel Booking</h4>
                            <p><?= $error ?></p>
                        </div>
                        <div class="text-center mt-4">
                            <a href="bookings.php" class="btn btn-primary me-2">
                                <i class="fa fa-list me-1"></i> My Bookings
                            </a>
                            <a href="index.php" class="btn btn-outline-light">
                                <i class="fa fa-home me-1"></i> Return to Home
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="booking-details mb-4">
                            <h4 class="mb-3">Booking Details</h4>
                            <table class="table table-dark">
                                <tr>
                                    <th>Booking Reference:</th>
                                    <td><?= htmlspecialchars($booking['booking_reference']) ?></td>
                                </tr>
                                <tr>
                                    <th>Movie:</th>
                                    <td><?= htmlspecialchars($booking['movie_title']) ?></td>
                                </tr>
                                <tr>
                                    <th>Showtime:</th>
                                    <td>
                                        <?= date('l, F d, Y', strtotime($booking['showtime_date'])) ?> at 
                                        <?= date('h:i A', strtotime($booking['showtime_time'])) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Amount Paid:</th>
                                    <td>₹<?= number_format($booking['total_amount'], 2) ?></td>
                                </tr>
                                <tr>
                                    <th>Booking Date:</th>
                                    <td><?= date('d M Y, h:i A', strtotime($booking['booking_date'])) ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="cancellation-warning alert alert-warning">
                            <h5 class="alert-heading"><i class="fa fa-exclamation-triangle me-2"></i> Cancellation Policy</h5>
                            <ul>
                                <li>Bookings can only be cancelled at least 2 hours before the showtime.</li>
                                <li>Once cancelled, tickets cannot be reinstated.</li>
                                <li>Refunds will be processed within 5-7 business days.</li>
                                <li>A cancellation fee of ₹20 may apply.</li>
                            </ul>
                        </div>
                        
                        <div class="confirm-cancellation mt-4">
                            <form action="cancel_ticket.php?id=<?= $booking_id ?>" method="POST">
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="confirmCancel" required>
                                    <label class="form-check-label" for="confirmCancel">
                                        I understand and agree to the cancellation policy.
                                    </label>
                                </div>
                                <div class="d-flex justify-content-center">
                                    <button type="submit" class="btn btn-danger me-2">
                                        <i class="fa fa-times-circle me-1"></i> Confirm Cancellation
                                    </button>
                                    <a href="bookings.php" class="btn btn-outline-light">
                                        <i class="fa fa-arrow-left me-1"></i> Go Back
                                    </a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
