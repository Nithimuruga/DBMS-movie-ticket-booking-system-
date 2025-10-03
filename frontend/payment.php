<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$errors = [];
$payment_method = '';

// Check if form is submitted from select_seats.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['form_submitted'])) {
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
        SELECT s.date, s.time, m.title AS movie_title, m.poster, t.name AS theater_name, t.location
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

// Process payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    
    if ($payment_method === 'card') {
        $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
        $card_name = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
        $expiry_date = isset($_POST['expiry_date']) ? trim($_POST['expiry_date']) : '';
        $cvv = isset($_POST['cvv']) ? trim($_POST['cvv']) : '';
        
        // Basic validation
        if (empty($card_number) || strlen($card_number) !== 16 || !is_numeric($card_number)) {
            $errors[] = "Please enter a valid 16-digit card number";
        }
        
        if (empty($card_name)) {
            $errors[] = "Please enter the name on the card";
        }
        
        if (empty($expiry_date) || !preg_match('/^\d{2}\/\d{2}$/', $expiry_date)) {
            $errors[] = "Please enter a valid expiry date (MM/YY)";
        }
        
        if (empty($cvv) || strlen($cvv) !== 3 || !is_numeric($cvv)) {
            $errors[] = "Please enter a valid 3-digit CVV";
        }
    } elseif ($payment_method === 'upi') {
        $upi_id = isset($_POST['upi_id']) ? trim($_POST['upi_id']) : '';
        
        // Basic UPI validation
        if (empty($upi_id) || !preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+$/', $upi_id)) {
            $errors[] = "Please enter a valid UPI ID (example: username@upi)";
        }
    } else {
        $errors[] = "Please select a payment method";
    }
    
    // If no errors, proceed with booking
    if (empty($errors)) {
        // Store payment info in session for receipt page
        $_SESSION['payment_info'] = [
            'method' => $payment_method,
            'showtime_id' => $showtime_id,
            'selected_seats' => $selected_seats,
            'price' => $price_per_seat,
            'total_amount' => $total_amount,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Forward to book_ticket.php for database processing
        echo '<form id="redirectForm" action="book_ticket.php" method="POST">';
        echo '<input type="hidden" name="showtime_id" value="' . htmlspecialchars($showtime_id) . '">';
        echo '<input type="hidden" name="price" value="' . htmlspecialchars($price_per_seat) . '">';
        
        foreach ($selected_seats as $seat) {
            echo '<input type="hidden" name="selected_seats[]" value="' . htmlspecialchars($seat) . '">';
        }
        
        echo '</form>';
        echo '<script>document.getElementById("redirectForm").submit();</script>';
        exit;
    }
}

$pageTitle = "Payment - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container py-4">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h5>Payment Error:</h5>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Order Summary Card -->
        <div class="col-md-4 order-md-2 mb-4">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary">
                    <h4 class="mb-0">Order Summary</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start mb-3">
                        <?php
                        $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $showtime['poster'];
                        if (!empty($showtime['poster']) && file_exists($poster_path)): 
                        ?>
                            <img src="../uploads/movie_posters/<?= htmlspecialchars($showtime['poster']) ?>" class="img-fluid rounded me-3" 
                                style="max-width: 70px;" alt="<?= htmlspecialchars($showtime['movie_title']) ?> Poster">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/70x100?text=No+Poster" class="img-fluid rounded me-3" 
                                alt="<?= htmlspecialchars($showtime['movie_title']) ?> Poster">
                        <?php endif; ?>
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($showtime['movie_title']) ?></h5>
                            <p class="text-muted mb-1"><?= htmlspecialchars($showtime['theater_name']) ?></p>
                            <small><?= date('D, M d, Y', strtotime($showtime['date'])) ?> at <?= date('h:i A', strtotime($showtime['time'])) ?></small>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="mb-3">
                        <h6>Selected Seats:</h6>
                        <p>
                            <?php
                            $seat_labels = [];
                            foreach ($selected_seats as $seat) {
                                list($row, $col) = explode('-', $seat);
                                $seat_labels[] = chr(64 + (int)$row) . $col;
                            }
                            echo implode(', ', $seat_labels);
                            ?>
                        </p>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Ticket Price:</span>
                        <span>₹<?= number_format($price_per_seat, 2) ?> × <?= count($selected_seats) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Convenience Fee:</span>
                        <span>₹0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-0 fw-bold">
                        <span>Total Amount:</span>
                        <span>₹<?= number_format($total_amount, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Form Card -->
        <div class="col-md-8 order-md-1">
            <div class="card bg-dark text-light shadow">
                <div class="card-header bg-primary">
                    <h4 class="mb-0">Payment Details</h4>
                </div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <!-- Hidden form fields to preserve data -->
                        <input type="hidden" name="showtime_id" value="<?= htmlspecialchars($showtime_id) ?>">
                        <input type="hidden" name="price" value="<?= htmlspecialchars($price_per_seat) ?>">
                        <input type="hidden" name="form_submitted" value="1">
                        <?php foreach ($selected_seats as $seat): ?>
                            <input type="hidden" name="selected_seats[]" value="<?= htmlspecialchars($seat) ?>">
                        <?php endforeach; ?>
                        
                        <!-- Payment Method Selection -->
                        <div class="mb-4">
                            <h5>Select Payment Method</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="payment_method" id="cardPayment"
                                            value="card" <?= $payment_method === 'card' ? 'checked' : '' ?> required>
                                        <label class="form-check-label d-flex align-items-center" for="cardPayment">
                                            <i class="fa fa-credit-card me-2 text-primary"></i>
                                            Credit/Debit Card
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="payment_method" id="upiPayment" 
                                            value="upi" <?= $payment_method === 'upi' ? 'checked' : '' ?> required>
                                        <label class="form-check-label d-flex align-items-center" for="upiPayment">
                                            <i class="fa fa-mobile-alt me-2 text-primary"></i>
                                            UPI Payment
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Payment Form -->
                        <div id="cardPaymentForm" class="payment-form <?= ($payment_method !== 'card' && $payment_method !== '') ? 'd-none' : '' ?>">
                            <h5 class="mb-3">Card Details</h5>
                            <div class="mb-3">
                                <label for="cardNumber" class="form-label">Card Number</label>
                                <input type="text" class="form-control" id="cardNumber" name="card_number" placeholder="1234 5678 9012 3456" maxlength="16">
                                <div class="form-text text-muted">Enter a 16-digit card number</div>
                            </div>
                            <div class="mb-3">
                                <label for="cardName" class="form-label">Name on Card</label>
                                <input type="text" class="form-control" id="cardName" name="card_name" placeholder="John Doe">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="expiryDate" class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" id="expiryDate" name="expiry_date" placeholder="MM/YY" maxlength="5">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <input type="password" class="form-control" id="cvv" name="cvv" placeholder="123" maxlength="3">
                                    <div class="form-text text-muted">3-digit number on back of card</div>
                                </div>
                            </div>
                        </div>

                        <!-- UPI Payment Form -->
                        <div id="upiPaymentForm" class="payment-form <?= $payment_method !== 'upi' ? 'd-none' : '' ?>">
                            <h5 class="mb-3">UPI Details</h5>
                            <div class="mb-3">
                                <label for="upiId" class="form-label">UPI ID</label>
                                <input type="text" class="form-control" id="upiId" name="upi_id" placeholder="username@upi">
                                <div class="form-text text-muted">Enter your UPI ID (e.g., username@upi)</div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="pay_now" class="btn btn-success btn-lg">
                                <i class="fa fa-lock me-2"></i> Pay Now ₹<?= number_format($total_amount, 2) ?>
                            </button>
                            <a href="javascript:history.back()" class="btn btn-outline-light">
                                <i class="fa fa-arrow-left me-2"></i> Back to Seat Selection
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle payment forms based on selection
    const cardPaymentRadio = document.getElementById('cardPayment');
    const upiPaymentRadio = document.getElementById('upiPayment');
    const cardPaymentForm = document.getElementById('cardPaymentForm');
    const upiPaymentForm = document.getElementById('upiPaymentForm');
    
    cardPaymentRadio.addEventListener('change', function() {
        if (this.checked) {
            cardPaymentForm.classList.remove('d-none');
            upiPaymentForm.classList.add('d-none');
        }
    });
    
    upiPaymentRadio.addEventListener('change', function() {
        if (this.checked) {
            upiPaymentForm.classList.remove('d-none');
            cardPaymentForm.classList.add('d-none');
        }
    });
    
    // Format card number to add spaces
    const cardNumberInput = document.getElementById('cardNumber');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            // Remove non-digits
            let value = e.target.value.replace(/\D/g, '');
            // Limit to 16 digits
            value = value.substring(0, 16);
            e.target.value = value;
        });
    }
    
    // Format expiry date to add '/'
    const expiryDateInput = document.getElementById('expiryDate');
    if (expiryDateInput) {
        expiryDateInput.addEventListener('input', function(e) {
            // Remove non-digits
            let value = e.target.value.replace(/\D/g, '');
            
            // Add slash after month
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            
            e.target.value = value;
        });
    }
    
    // Only allow digits for CVV
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            // Remove non-digits
            let value = e.target.value.replace(/\D/g, '');
            // Limit to 3 digits
            value = value.substring(0, 3);
            e.target.value = value;
        });
    }
});
</script>

<style>
.payment-option {
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #2c2c2c;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-option:hover {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.form-check-input:checked ~ .form-check-label {
    font-weight: bold;
}

.payment-form {
    background-color: #1e1e1e;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>
