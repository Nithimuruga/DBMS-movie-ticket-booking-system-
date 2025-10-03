<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Get showtime ID from URL
if (!isset($_GET['showtime_id']) || !is_numeric($_GET['showtime_id'])) {
    header("Location: index.php");
    exit;
}

$showtime_id = (int)$_GET['showtime_id'];

// Get showtime details with movie and theater info
try {
    $stmt = $pdo->prepare("
        SELECT s.id as showtime_id, s.date, s.time, s.price,
               m.id as movie_id, m.title as movie_title, m.duration, m.poster,
               t.id as theater_id, t.name as theater_name, t.location, t.rows, t.columns
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        WHERE s.id = ? AND s.date >= CURDATE() AND m.status = 'active' AND t.status = 'active'
    ");
    $stmt->execute([$showtime_id]);
    $showtime = $stmt->fetch();

    if (!$showtime) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Error fetching showtime details: " . $e->getMessage();
}

// Get already booked seats for this showtime
try {
    $stmt = $pdo->prepare("
        SELECT seat_row, seat_column
        FROM booking_seats
        JOIN bookings ON booking_seats.booking_id = bookings.id
        WHERE bookings.showtime_id = ? AND bookings.status != 'cancelled'
    ");
    $stmt->execute([$showtime_id]);
    $booked_seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to a more usable format
    $booked_seats_map = [];
    foreach ($booked_seats as $seat) {
        $booked_seats_map[$seat['seat_row']][$seat['seat_column']] = true;
    }
} catch (PDOException $e) {
    $error = "Error fetching booked seats: " . $e->getMessage();
}

$pageTitle = "Select Seats - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-primary">Back to Movies</a>
        </div>
    <?php else: ?>
        <!-- Movie & Showtime Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-dark text-light shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 mb-3 mb-md-0">
                                <?php
                                $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $showtime['poster'];
                                if (!empty($showtime['poster']) && file_exists($poster_path)): 
                                ?>
                                    <img src="../uploads/movie_posters/<?= htmlspecialchars($showtime['poster']) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($showtime['movie_title']) ?> Poster">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/350x500?text=No+Poster" class="img-fluid rounded" alt="<?= htmlspecialchars($showtime['movie_title']) ?> Poster">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-10">
                                <h3 class="card-title mb-2"><?= htmlspecialchars($showtime['movie_title']) ?></h3>
                                <div class="d-flex flex-wrap mb-2">
                                    <div class="me-4">
                                        <i class="fa fa-building text-primary me-1"></i> 
                                        <?= htmlspecialchars($showtime['theater_name']) ?>
                                    </div>
                                    <div class="me-4">
                                        <i class="fa fa-calendar text-primary me-1"></i> 
                                        <?= date('D, M d, Y', strtotime($showtime['date'])) ?>
                                    </div>
                                    <div class="me-4">
                                        <i class="fa fa-clock text-primary me-1"></i> 
                                        <?= date('h:i A', strtotime($showtime['time'])) ?>
                                    </div>
                                    <div>
                                        <i class="fa fa-tag text-primary me-1"></i> 
                                        ₹<?= number_format($showtime['price'], 2) ?> per seat
                                    </div>
                                </div>
                                <div class="text-muted">
                                    <?= htmlspecialchars($showtime['location']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seat Selection Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-dark text-light shadow">
                    <div class="card-header bg-primary">
                        <h3 class="mb-0">Select Your Seats</h3>
                    </div>
                    <div class="card-body">
                        <div class="seat-selection-container">
                            <!-- Seat Map Legend -->
                            <div class="seat-legend d-flex justify-content-center mb-4">
                                <div class="d-flex align-items-center me-4">
                                    <div class="seat-example available me-2"></div>
                                    <span>Available</span>
                                </div>
                                <div class="d-flex align-items-center me-4">
                                    <div class="seat-example selected me-2"></div>
                                    <span>Selected</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="seat-example booked me-2"></div>
                                    <span>Booked</span>
                                </div>
                            </div>

                            <!-- Screen Area -->
                            <div class="screen-container text-center mb-5 mt-5">
                                <div class="screen mx-auto">
                                    <div class="screen-text">SCREEN</div>
                                </div>
                                <div class="screen-shadow"></div>
                            </div>

                            <!-- Seat Map -->
                            <div class="seat-map-container">
                                <form id="seatSelectionForm" action="payment.php" method="POST">
                                    <input type="hidden" name="showtime_id" value="<?= $showtime_id ?>">
                                    <input type="hidden" name="price" value="<?= $showtime['price'] ?>">
                                    <input type="hidden" name="form_submitted" value="1">
                                    <div id="seatValidationError" class="alert alert-danger d-none">
                                        Please select at least one seat.
                                    </div>
                                    
                                    <div class="seat-map">
                                        <?php for ($row = 1; $row <= $showtime['rows']; $row++): ?>
                                            <div class="seat-row">
                                                <div class="row-label"><?= chr(64 + $row) ?></div>
                                                <?php for ($col = 1; $col <= $showtime['columns']; $col++): ?>
                                                    <?php
                                                    $is_booked = isset($booked_seats_map[$row][$col]);
                                                    $seat_class = $is_booked ? 'booked' : 'available';
                                                    $disabled = $is_booked ? 'disabled' : '';
                                                    $tooltip = $is_booked ? 'data-bs-toggle="tooltip" data-bs-placement="top" title="This seat is already booked"' : '';
                                                    ?>
                                                    <div class="seat <?= $seat_class ?>" data-row="<?= $row ?>" data-col="<?= $col ?>" <?= $tooltip ?>>
                                                        <input type="checkbox" name="selected_seats[]" value="<?= $row ?>-<?= $col ?>" class="seat-checkbox" <?= $disabled ?>>
                                                        <span class="seat-number"><?= $col ?></span>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>

                                    <!-- Booking Summary -->
                                    <div class="booking-summary mt-5">
                                        <h4 class="mb-3">Booking Summary</h4>
                                        <div class="card bg-secondary text-light">
                                            <div class="card-body">
                                                <div class="selected-seats-info mb-3">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Selected Seats:</span>
                                                        <span id="selectedSeatsText">None</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Number of Seats:</span>
                                                        <span id="seatCount">0</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span>Price per Seat:</span>
                                                        <span>₹<?= number_format($showtime['price'], 2) ?></span>
                                                    </div>
                                                    <hr>
                                                    <div class="d-flex justify-content-between fw-bold">
                                                        <span>Total Amount:</span>
                                                        <span id="totalAmount">₹0.00</span>
                                                    </div>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="button" id="proceedBtn" class="btn btn-primary btn-lg" disabled onclick="submitBookingForm()">
                                                        Proceed to Payment
                                                    </button>
                                                </div>
                                                
                                                <script>
                                                    function submitBookingForm() {
                                                        const form = document.getElementById('seatSelectionForm');
                                                        if (form) {
                                                            const selectedSeats = document.querySelectorAll('.seat input[type="checkbox"]:checked');
                                                            if (selectedSeats.length > 0) {
                                                                form.submit();
                                                            } else {
                                                                const errorElement = document.getElementById('seatValidationError');
                                                                errorElement.classList.remove('d-none');
                                                                errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                                                setTimeout(() => {
                                                                    errorElement.classList.add('d-none');
                                                                }, 3000);
                                                            }
                                                        }
                                                    }
                                                </script>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
