<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get booking details
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.booking_reference, b.total_amount, b.booking_date, b.status,
               s.date, s.time, s.price,
               m.id as movie_id, m.title as movie_title, m.poster, m.duration, m.genre, m.language,
               t.name as theater_name, t.location, t.city
        FROM bookings b
        JOIN showtimes s ON b.showtime_id = s.id
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        header("Location: bookings.php");
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
    
    // Format seats for display
    $formatted_seats = [];
    foreach ($seats as $seat) {
        $formatted_seats[] = chr(64 + $seat['seat_row']) . $seat['seat_column'];
    }
    $seats_display = implode(', ', $formatted_seats);

} catch (PDOException $e) {
    $error = "Error fetching booking details: " . $e->getMessage();
}

$pageTitle = "View Ticket - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card bg-dark text-light shadow">
                    <div class="card-header bg-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">Ticket Details</h3>
                            <div>
                                <button id="printBtn" class="btn btn-light btn-sm me-2">
                                    <i class="fa fa-print me-1"></i> Print
                                </button>
                                <a href="bookings.php" class="btn btn-outline-light btn-sm">
                                    <i class="fa fa-arrow-left me-1"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="printableTicket">
                        <div class="ticket-container">
                            <!-- Ticket Header -->
                            <div class="ticket-header d-flex align-items-center mb-4">
                                <div class="site-logo">
                                    <h2 class="mb-0"><?= SITE_NAME ?></h2>
                                    <div class="small text-muted">Movie Tickets</div>
                                </div>
                                <div class="ms-auto">
                                    <div class="booking-status 
                                        <?= $booking['status'] === 'confirmed' ? 'text-success' : 'text-danger' ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ticket Body -->
                            <div class="row">
                                <!-- Movie Poster and Details -->
                                <div class="col-md-4 mb-4 mb-md-0">
                                    <div class="movie-poster-container mb-3">
                                        <?php 
                                        $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $booking['poster'];
                                        if (!empty($booking['poster']) && file_exists($poster_path)): 
                                        ?>
                                            <img src="../uploads/movie_posters/<?= htmlspecialchars($booking['poster']) ?>" 
                                                 class="img-fluid rounded shadow" 
                                                 alt="<?= htmlspecialchars($booking['movie_title']) ?>">
                                        <?php else: ?>
                                            <img src="https://pixabay.com/get/g9b1bfb92eb5909a18295c806fd46359e6b805aff4c2a4832a233a336b3ea007ac0b0f0846275ea1f8fcc97879c1ab5c0b7e8f8e0b1f558489dd0b5573a1db525_1280.jpg" 
                                                 class="img-fluid rounded shadow" 
                                                 alt="<?= htmlspecialchars($booking['movie_title']) ?>">
                                        <?php endif; ?>
                                    </div>
                                    
                                    
                                </div>
                                
                                <!-- Ticket Details -->
                                <div class="col-md-8">
                                    <div class="ticket-details">
                                        <h3 class="movie-title mb-2"><?= htmlspecialchars($booking['movie_title']) ?></h3>
                                        
                                        <div class="movie-meta mb-3">
                                            <span class="badge bg-secondary me-2"><?= htmlspecialchars($booking['genre']) ?></span>
                                            <span class="badge bg-secondary me-2"><?= htmlspecialchars($booking['language']) ?></span>
                                            <span class="badge bg-secondary"><?= formatDuration($booking['duration']) ?></span>
                                        </div>
                                        
                                        <div class="ticket-info">
                                            <table class="table table-dark ticket-info-table">
                                                <tr>
                                                    <th><i class="fa fa-ticket-alt text-primary me-2"></i> Booking ID:</th>
                                                    <td><?= htmlspecialchars($booking['booking_reference']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th><i class="fa fa-calendar-alt text-primary me-2"></i> Date:</th>
                                                    <td><?= date('l, F d, Y', strtotime($booking['date'])) ?></td>
                                                </tr>
                                                <tr>
                                                    <th><i class="fa fa-clock text-primary me-2"></i> Time:</th>
                                                    <td><?= date('h:i A', strtotime($booking['time'])) ?></td>
                                                </tr>
                                                <tr>
                                                    <th><i class="fa fa-building text-primary me-2"></i> Theater:</th>
                                                    <td><?= htmlspecialchars($booking['theater_name']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th><i class="fa fa-map-marker-alt text-primary me-2"></i> Location:</th>
                                                    <td><?= htmlspecialchars($booking['location']) ?>, <?= htmlspecialchars($booking['city']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th><i class="fa fa-couch text-primary me-2"></i> Seats:</th>
                                                    <td><?= $seats_display ?></td>
                                                </tr>
                                                <tr>
                                                    <th><i class="fa fa-tag text-primary me-2"></i> Amount Paid:</th>
                                                    <td>₹<?= number_format($booking['total_amount'], 2) ?></td>
                                                </tr>
                                                <tr>
                                                    <th><i class="fa fa-calendar-check text-primary me-2"></i> Booking Date:</th>
                                                    <td><?= date('d M Y, h:i A', strtotime($booking['booking_date'])) ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ticket Footer -->
                            <div class="ticket-footer mt-4 pt-3 border-top border-secondary">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5>Terms & Conditions:</h5>
                                        <ul class="small text-muted">
                                            <li>Please arrive at least 15 minutes before the show time.</li>
                                            <li>Present this ticket (printed or digital) at the entrance.</li>
                                            <li>Outside food and beverages are not allowed in the theater.</li>
                                            <li>Recording the movie is strictly prohibited.</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="contact-info small text-muted">
                                            <div><?= SITE_NAME ?> Customer Support</div>
                                            <div>Email: <?= ADMIN_EMAIL ?></div>
                                            <div>Phone: +91-9876543210</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-dark">
                        <div class="d-flex justify-content-between">
                            <?php if ($booking['status'] === 'confirmed' && 
                                     strtotime($booking['date'] . ' ' . $booking['time']) > time()): ?>
                                <a href="cancel_ticket.php?id=<?= $booking_id ?>" class="btn btn-danger">
                                    <i class="fa fa-times-circle me-1"></i> Cancel Booking
                                </a>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                            
                            <a href="bookings.php" class="btn btn-outline-light">
                                <i class="fa fa-list me-1"></i> All Bookings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('printBtn').addEventListener('click', function() {
    // Create a new window
    const printWindow = window.open('', '_blank');
    
    // Get the ticket content
    const ticketContent = document.getElementById('printableTicket').innerHTML;
    
    // Generate print-friendly content
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Movie Ticket - ${<?= json_encode(htmlspecialchars($booking['movie_title'])) ?>}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
            <style>
                body {
                    padding: 20px;
                    font-family: Arial, sans-serif;
                }
                .ticket-container {
                    border: 1px solid #ddd;
                    padding: 20px;
                    border-radius: 10px;
                }
                .qr-code {
                    max-width: 150px;
                }
                @media print {
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="mb-4 no-print text-end">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fa fa-print"></i> Print
                    </button>
                    <button onclick="window.close()" class="btn btn-secondary ms-2">
                        Close
                    </button>
                </div>
                <div class="ticket-container">
                    ${ticketContent}
                </div>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
});
</script>

<?php require_once '../includes/footer.php'; ?>
