<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$errors = [];

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Get form data
$showtime_id = isset($_POST['showtime_id']) ? (int)$_POST['showtime_id'] : 0;
$selected_seats = isset($_POST['selected_seats']) ? $_POST['selected_seats'] : [];
$price_per_seat = isset($_POST['price']) ? (float)$_POST['price'] : 0;

// Validate input
if (empty($showtime_id)) {
    $errors[] = "Invalid showtime selected";
}

if (empty($selected_seats)) {
    $errors[] = "No seats selected";
}

if ($price_per_seat <= 0) {
    $errors[] = "Invalid ticket price";
}

// Calculate total amount
$total_amount = count($selected_seats) * $price_per_seat;

// Get showtime details
try {
    $stmt = $pdo->prepare("
        SELECT s.date, s.time, m.title AS movie_title, t.name AS theater_name, t.location
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        WHERE s.id = ?
    ");
    $stmt->execute([$showtime_id]);
    $showtime = $stmt->fetch();

    if (!$showtime) {
        $errors[] = "Showtime not found";
    }
} catch (PDOException $e) {
    $errors[] = "Error fetching showtime details: " . $e->getMessage();
}

// Check if seats are still available
if (empty($errors)) {
    try {
        // Prepare seat data for checking
        $seat_data = [];
        foreach ($selected_seats as $seat) {
            list($row, $column) = explode('-', $seat);
            $seat_data[] = [
                'row' => (int)$row,
                'column' => (int)$column
            ];
        }

        // Check if any of the selected seats are already booked
        $placeholders = implode(',', array_fill(0, count($seat_data), '(? , ?)'));
        $values = [];
        
        foreach ($seat_data as $seat) {
            $values[] = $seat['row'];
            $values[] = $seat['column'];
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS booked_count
            FROM booking_seats bs
            JOIN bookings b ON bs.booking_id = b.id
            WHERE b.showtime_id = ? AND b.status != 'cancelled'
            AND (seat_row, seat_column) IN ($placeholders)
        ");
        
        $params = array_merge([$showtime_id], $values);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result['booked_count'] > 0) {
            $errors[] = "Some of the selected seats are already booked. Please go back and choose different seats.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error checking seat availability: " . $e->getMessage();
    }
}

// Process booking if no errors
if (empty($errors)) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Generate booking reference
        $booking_reference = generateBookingReference();
        
        // Create booking record
        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, showtime_id, booking_reference, total_amount, booking_date, status)
            VALUES (?, ?, ?, ?, NOW(), 'confirmed')
        ");
        $stmt->execute([$_SESSION['user_id'], $showtime_id, $booking_reference, $total_amount]);
        $booking_id = $pdo->lastInsertId();
        
        // Add seat records
        $stmt = $pdo->prepare("
            INSERT INTO booking_seats (booking_id, seat_row, seat_column)
            VALUES (?, ?, ?)
        ");
        
        foreach ($seat_data as $seat) {
            $stmt->execute([$booking_id, $seat['row'], $seat['column']]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to success page
        $_SESSION['booking_success'] = true;
        $_SESSION['booking_id'] = $booking_id;
        header("Location: booking_success.php");
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $errors[] = "Booking error: " . $e->getMessage();
    }
}

// If there are errors, display them
$pageTitle = "Booking Error - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-danger">
                    <h3 class="card-title mb-0">Booking Error</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Unable to complete your booking!</h4>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="text-center mt-4">
                        <a href="javascript:history.back()" class="btn btn-primary me-2">
                            <i class="fa fa-arrow-left me-1"></i> Go Back
                        </a>
                        <a href="index.php" class="btn btn-outline-light">
                            <i class="fa fa-home me-1"></i> Return to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
