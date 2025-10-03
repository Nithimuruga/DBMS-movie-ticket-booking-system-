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

// Initialize form values
$movie_id = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : '';
$theater_id = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : '';
$date = date('Y-m-d');
$time = '18:00:00';
$price = 250;

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
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Date cannot be in the past";
    }
    
    if (empty($time)) {
        $errors[] = "Time is required";
    } elseif (!validateTime($time)) {
        $errors[] = "Invalid time format";
    }
    
    if (empty($price) || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    
    // Check if showtime already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM showtimes 
                WHERE movie_id = ? AND theater_id = ? AND date = ? AND time = ?
            ");
            $stmt->execute([$movie_id, $theater_id, $date, $time]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "A showtime with this movie, theater, date, and time already exists";
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking showtime: " . $e->getMessage();
        }
    }
    
    // Insert showtime if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO showtimes (
                    movie_id, theater_id, date, time, price, created_at
                ) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $movie_id, $theater_id, $date, $time, $price
            ]);
            
            $success = true;
            
            // Reset form values
            $movie_id = '';
            $theater_id = '';
            $date = date('Y-m-d');
            $time = '18:00:00';
            $price = 250;
        } catch (PDOException $e) {
            $errors[] = "Error adding showtime: " . $e->getMessage();
        }
    }
}

$pageTitle = "Add New Showtime - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Add New Showtime</h4>
                    <a href="manage_showtimes.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-list me-1"></i> Manage Showtimes
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Showtime has been added successfully!
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
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
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
                                    <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date) ?>" min="<?= date('Y-m-d') ?>" required>
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
                                <i class="fas fa-save me-1"></i> Add Showtime
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Existing Showtimes Section -->
    <?php if (!empty($movie_id) || !empty($theater_id)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card bg-dark text-light">
                    <div class="card-header bg-secondary">
                        <h4 class="card-title mb-0">
                            <?php if (!empty($movie_id)): ?>
                                Existing Showtimes for Selected Movie
                            <?php elseif (!empty($theater_id)): ?>
                                Existing Showtimes for Selected Theater
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            if (!empty($movie_id)) {
                                $stmt = $pdo->prepare("
                                    SELECT s.id, s.date, s.time, s.price,
                                           m.title as movie_title,
                                           t.name as theater_name, t.location, t.city
                                    FROM showtimes s
                                    JOIN movies m ON s.movie_id = m.id
                                    JOIN theaters t ON s.theater_id = t.id
                                    WHERE s.movie_id = ? AND s.date >= CURDATE()
                                    ORDER BY s.date, s.time
                                ");
                                $stmt->execute([$movie_id]);
                            } elseif (!empty($theater_id)) {
                                $stmt = $pdo->prepare("
                                    SELECT s.id, s.date, s.time, s.price,
                                           m.title as movie_title,
                                           t.name as theater_name, t.location, t.city
                                    FROM showtimes s
                                    JOIN movies m ON s.movie_id = m.id
                                    JOIN theaters t ON s.theater_id = t.id
                                    WHERE s.theater_id = ? AND s.date >= CURDATE()
                                    ORDER BY s.date, s.time
                                ");
                                $stmt->execute([$theater_id]);
                            }
                            
                            $existing_showtimes = $stmt->fetchAll();
                            
                            if (empty($existing_showtimes)): 
                        ?>
                                <div class="alert alert-info">No existing showtimes found.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <?php if (empty($movie_id)): ?><th>Movie</th><?php endif; ?>
                                                <?php if (empty($theater_id)): ?><th>Theater</th><?php endif; ?>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($existing_showtimes as $showtime): ?>
                                                <tr>
                                                    <?php if (empty($movie_id)): ?>
                                                        <td><?= htmlspecialchars($showtime['movie_title']) ?></td>
                                                    <?php endif; ?>
                                                    <?php if (empty($theater_id)): ?>
                                                        <td>
                                                            <?= htmlspecialchars($showtime['theater_name']) ?>
                                                            <small class="text-muted d-block">
                                                                <?= htmlspecialchars($showtime['location']) ?>, <?= htmlspecialchars($showtime['city']) ?>
                                                            </small>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td><?= date('d M Y', strtotime($showtime['date'])) ?></td>
                                                    <td><?= date('h:i A', strtotime($showtime['time'])) ?></td>
                                                    <td>₹<?= number_format($showtime['price'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php } catch (PDOException $e) { ?>
                            <div class="alert alert-danger">Error fetching existing showtimes: <?= $e->getMessage() ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date for date input
    const dateInput = document.getElementById('date');
    const today = new Date().toISOString().split('T')[0];
    dateInput.setAttribute('min', today);
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
