<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit;
}

$user_id = (int)$_GET['id'];

// Get user details
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at, u.updated_at, u.last_login
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "User not found";
    } else {
        // Get user's booking stats
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_bookings, SUM(total_amount) as total_spent
            FROM bookings 
            WHERE user_id = ? AND status = 'confirmed'
        ");
        $stmt->execute([$user_id]);
        $booking_stats = $stmt->fetch();
        
        // Get user's recent bookings
        $stmt = $pdo->prepare("
            SELECT b.id, b.booking_reference, b.total_amount, b.booking_date, b.status,
                   m.title as movie_title, m.poster as movie_poster,
                   s.date as showtime_date, s.time as showtime_time,
                   t.name as theater_name
            FROM bookings b
            JOIN showtimes s ON b.showtime_id = s.id
            JOIN movies m ON s.movie_id = m.id
            JOIN theaters t ON s.theater_id = t.id
            WHERE b.user_id = ?
            ORDER BY b.booking_date DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_bookings = $stmt->fetchAll();
        
        // Get user's reviews
        $stmt = $pdo->prepare("
            SELECT r.id, r.rating, r.comment, r.created_at,
                   m.id as movie_id, m.title as movie_title, m.poster as movie_poster
            FROM ratings r
            JOIN movies m ON r.movie_id = m.id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $reviews = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = "Error fetching user details: " . $e->getMessage();
}

$pageTitle = "User Details - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">User Details</h4>
                    <div>
                        <a href="manage_users.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Users
                        </a>
                        <?php if (!isset($error) && $user['id'] != $_SESSION['user_id']): ?>
                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-warning btn-sm ms-2">
                                <i class="fas fa-edit me-1"></i> Edit User
                            </a>
                        <?php endif; ?>
                    </div>
                </div>                <div class="card-body">
                    <?php if (isset($_SESSION['user_updated'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> User information has been updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['user_updated']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php else: ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-dark border border-secondary mb-4">
                                    <div class="card-header bg-secondary">
                                        <h5 class="card-title mb-0">Personal Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3 d-flex align-items-center">
                                            <div class="avatar-placeholder bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px;">
                                                <i class="fas fa-user fa-2x"></i>
                                            </div>
                                            <div>
                                                <h4 class="mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                                                <span class="badge <?= $user['role'] == 1 ? 'bg-primary' : 'bg-secondary' ?>">
                                                    <?= $user['role'] == 1 ? 'Admin' : 'User' ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-12">
                                                <table class="table table-dark table-bordered">
                                                    <tr>
                                                        <th width="35%">Email</th>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Phone</th>
                                                        <td><?= htmlspecialchars($user['phone']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Registered On</th>
                                                        <td><?= date('F d, Y H:i', strtotime($user['created_at'])) ?></td>
                                                    </tr>
                                                    <?php if ($user['updated_at']): ?>
                                                    <tr>
                                                        <th>Last Updated</th>
                                                        <td><?= date('F d, Y H:i', strtotime($user['updated_at'])) ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <th>Last Login</th>
                                                        <td><?= $user['last_login'] ? date('F d, Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card bg-dark border border-secondary">
                                    <div class="card-header bg-secondary">
                                        <h5 class="card-title mb-0">Booking Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="stat-card bg-primary p-3 rounded">
                                                    <h6 class="text-white-50">Total Bookings</h6>
                                                    <h3 class="mb-0"><?= number_format($booking_stats['total_bookings'] ?? 0) ?></h3>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="stat-card bg-success p-3 rounded">
                                                    <h6 class="text-white-50">Total Amount Spent</h6>
                                                    <h3 class="mb-0">₹<?= number_format($booking_stats['total_spent'] ?? 0, 2) ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card bg-dark border border-secondary mb-4">
                                    <div class="card-header bg-secondary d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Recent Bookings</h5>
                                        <?php if (!empty($recent_bookings)): ?>
                                        <a href="manage_bookings.php?user_id=<?= $user['id'] ?>" class="btn btn-outline-light btn-sm">
                                            View All
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($recent_bookings)): ?>
                                            <div class="alert alert-info mb-0">This user has no bookings yet.</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-dark table-hover table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Movie</th>
                                                            <th>Date & Time</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($recent_bookings as $booking): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <?php 
                                                                        $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $booking['movie_poster'];
                                                                        $has_poster = !empty($booking['movie_poster']) && file_exists($poster_path);
                                                                        ?>
                                                                        <div class="me-2" style="width: 40px; height: 40px; overflow: hidden;">
                                                                            <?php if ($has_poster): ?>
                                                                                <img src="<?= UPLOAD_URL . htmlspecialchars($booking['movie_poster']) ?>" 
                                                                                    style="width: 100%; height: 100%; object-fit: cover;" 
                                                                                    alt="<?= htmlspecialchars($booking['movie_title']) ?>">
                                                                            <?php else: ?>
                                                                                <div class="bg-secondary d-flex align-items-center justify-content-center" 
                                                                                    style="width: 100%; height: 100%;">
                                                                                    <i class="fas fa-film"></i>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <?= htmlspecialchars($booking['movie_title']) ?>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?= date('M d, Y', strtotime($booking['showtime_date'])) ?>
                                                                    <br>
                                                                    <?= date('h:i A', strtotime($booking['showtime_time'])) ?>
                                                                </td>
                                                                <td>₹<?= number_format($booking['total_amount'], 2) ?></td>
                                                                <td>
                                                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                                                        <span class="badge bg-success">Confirmed</span>
                                                                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                                                                        <span class="badge bg-danger">Cancelled</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card bg-dark border border-secondary">
                                    <div class="card-header bg-secondary">
                                        <h5 class="card-title mb-0">Recent Reviews</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($reviews)): ?>
                                            <div class="alert alert-info mb-0">This user has not written any reviews yet.</div>
                                        <?php else: ?>
                                            <?php foreach ($reviews as $review): ?>
                                                <div class="review-card mb-3 p-3 border border-secondary rounded">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <div class="d-flex align-items-center">
                                                                <?php 
                                                                $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $review['movie_poster'];
                                                                $has_poster = !empty($review['movie_poster']) && file_exists($poster_path);
                                                                ?>
                                                                <div class="me-2" style="width: 40px; height: 40px; overflow: hidden;">
                                                                    <?php if ($has_poster): ?>
                                                                        <img src="<?= UPLOAD_URL . htmlspecialchars($review['movie_poster']) ?>" 
                                                                            style="width: 100%; height: 100%; object-fit: cover;" 
                                                                            alt="<?= htmlspecialchars($review['movie_title']) ?>">
                                                                    <?php else: ?>
                                                                        <div class="bg-secondary d-flex align-items-center justify-content-center" 
                                                                            style="width: 100%; height: 100%;">
                                                                            <i class="fas fa-film"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <span class="fw-bold"><?= htmlspecialchars($review['movie_title']) ?></span>
                                                            </div>
                                                            <div class="text-warning mt-1">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <?php if ($i <= $review['rating']): ?>
                                                                        <i class="fas fa-star"></i>
                                                                    <?php else: ?>
                                                                        <i class="far fa-star"></i>
                                                                    <?php endif; ?>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                                                    </div>
                                                    <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/admin_footer.php'; ?>
