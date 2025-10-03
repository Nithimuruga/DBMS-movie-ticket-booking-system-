<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

$errors = [];
$success = false;

// Check if showtime ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_showtimes.php");
    exit;
}

$showtime_id = (int)$_GET['id'];

// Get showtime details
try {
    $stmt = $pdo->prepare("
        SELECT s.*, m.title as movie_title, t.name as theater_name, t.location, t.city
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        JOIN theaters t ON s.theater_id = t.id
        WHERE s.id = ?
    ");
    $stmt->execute([$showtime_id]);
    $showtime = $stmt->fetch();
    
    if (!$showtime) {
        header("Location: manage_showtimes.php");
        exit;
    }
    
    // Initialize form values with showtime data
    $movie_id = $showtime['movie_id'];
    $theater_id = $showtime['theater_id'];
    $date = $showtime['date'];
    $time = $showtime['time'];
    $price = $showtime['price'];
    
    // Check if showtime has bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE showtime_id = ?");
    $stmt->execute([$showtime_id]);
    $booking_count = $stmt->fetchColumn();
    $has_bookings = ($booking_count > 0);
    
} catch (PDOException $e) {
    $errors[] = "Error fetching showtime details: " . $e->getMessage();
}

// Get movies list
try {
    $stmt = $pdo->prepare("SELECT id, title FROM movies WHERE status = 'active' ORDER BY title");
    $stmt->execute();
    $movies = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error fetching movies: " . $e->getMessage();
}

// Get theaters list
try {
    $stmt = $pdo->prepare("SELECT id, name, location, city FROM theaters WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $theaters = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error fetching theaters: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $movie_id = (int)$_POST['movie_id'];
    $theater_id = (int)$_POST['theater_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $price = (float)$_POST['price'];
    
    // Validate input
    if (empty($movie_id)) {
        $errors[] = "Movie is required";
    }
    
    if (empty($theater_id)) {
        $errors[] = "Theater is required";
    }
    
    if (empty($date)) {
        $errors[] = "Date is required";
    } elseif (!validateDate($date)) {
        $errors[] = "Invalid date format";
    }
    
    if (empty($time)) {
        $errors[] = "Time is required";
    } elseif (!validateTime($time)) {
        $errors[] = "Invalid time format";
    }
    
    if (empty($price) || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    
    // Check if the showtime already exists (except this one)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM showtimes 
                WHERE movie_id = ? AND theater_id = ? AND date = ? AND time = ? AND id != ?
            ");
            $stmt->execute([$movie_id, $theater_id, $date, $time, $showtime_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "A showtime with this movie, theater, date, and time already exists";
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking showtime: " . $e->getMessage();
        }
    }
    
    // Update showtime if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE showtimes 
                SET movie_id = ?, theater_id = ?, date = ?, time = ?, price = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$movie_id, $theater_id, $date, $time, $price, $showtime_id]);
            
            $success = true;
            
            // Refresh showtime data
            $stmt = $pdo->prepare("
                SELECT s.*, m.title as movie_title, t.name as theater_name, t.location, t.city
                FROM showtimes s
                JOIN movies m ON s.movie_id = m.id
                JOIN theaters t ON s.theater_id = t.id
                WHERE s.id = ?
            ");
            $stmt->execute([$showtime_id]);
            $showtime = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = "Error updating showtime: " . $e->getMessage();
        }
    }
}

$pageTitle = "Edit Showtime - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Edit Showtime</h4>
                    <div>
                        <a href="manage_showtimes.php" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-list me-1"></i> Manage Showtimes
                        </a>
                        <a href="add_showtime.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-1"></i> Add New Showtime
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($has_bookings): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> This showtime has <?= $booking_count ?> booking(s). 
                            Changing the movie, date, or time might affect these bookings.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Showtime has been updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $showtime_id) ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="movie_id" class="form-label">Select Movie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="movie_id" name="movie_id" required>
                                        <option value="">-- Select Movie --</option>
                                        <?php foreach ($movies as $movie): ?>
                                            <option value="<?= $movie['id'] ?>" <?= $movie_id == $movie['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($movie['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="theater_id" class="form-label">Select Theater <span class="text-danger">*</span></label>
                                    <select class="form-select" id="theater_id" name="theater_id" required>
                                        <option value="">-- Select Theater --</option>
                                        <?php foreach ($theaters as $theater): ?>
                                            <option value="<?= $theater['id'] ?>" <?= $theater_id == $theater['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($theater['name']) ?> 
                                                (<?= htmlspecialchars($theater['location']) ?>, <?= htmlspecialchars($theater['city']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date" class="form-label">Show Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="time" class="form-label">Show Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="time" name="time" value="<?= htmlspecialchars($time) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="price" class="form-label">Ticket Price (₹) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="price" name="price" value="<?= htmlspecialchars($price) ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <a href="manage_showtimes.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Showtime
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($showtime)): ?>
    <!-- Related Showtimes Section -->
    <div class="row">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-secondary">
                    <h4 class="card-title mb-0">Other Showtimes</h4>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->prepare("
                            SELECT s.id, s.date, s.time, s.price,
                                   m.title as movie_title,
                                   t.name as theater_name, t.location, t.city
                            FROM showtimes s
                            JOIN movies m ON s.movie_id = m.id
                            JOIN theaters t ON s.theater_id = t.id
                            WHERE (s.movie_id = ? OR s.theater_id = ?) AND s.id != ?
                            ORDER BY s.date, s.time
                            LIMIT 10
                        ");
                        $stmt->execute([$movie_id, $theater_id, $showtime_id]);
                        $related_showtimes = $stmt->fetchAll();
                        
                        if (empty($related_showtimes)): 
                    ?>
                            <div class="alert alert-info">No other related showtimes found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Movie</th>
                                            <th>Theater</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Price</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($related_showtimes as $s): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($s['movie_title']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($s['theater_name']) ?>
                                                    <small class="text-muted d-block">
                                                        <?= htmlspecialchars($s['location']) ?>, <?= htmlspecialchars($s['city']) ?>
                                                    </small>
                                                </td>
                                                <td><?= date('d M Y', strtotime($s['date'])) ?></td>
                                                <td><?= date('h:i A', strtotime($s['time'])) ?></td>
                                                <td>₹<?= number_format($s['price'], 2) ?></td>
                                                <td>
                                                    <a href="edit_showtime.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php } catch (PDOException $e) { ?>
                        <div class="alert alert-danger">Error fetching related showtimes: <?= $e->getMessage() ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Additional client-side validations could be added here if needed
    const dateInput = document.getElementById('date');
    
    // If the showtime has bookings, show a confirmation dialog before making changes
    <?php if ($has_bookings): ?>
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        // Only show confirmation if date or movie was changed
        const originalDate = '<?= $showtime['date'] ?>';
        const originalMovie = '<?= $showtime['movie_id'] ?>';
        const originalTime = '<?= $showtime['time'] ?>';
        
        if (dateInput.value !== originalDate || 
            document.getElementById('movie_id').value !== originalMovie ||
            document.getElementById('time').value !== originalTime) {
            
            if (!confirm('This showtime has existing bookings. Changing the date, time, or movie may affect these bookings. Are you sure you want to proceed?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
