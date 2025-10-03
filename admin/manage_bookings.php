<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Status change functionality removed

// Get all bookings
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 15;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total records for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings");
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get search/filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $date_filter = isset($_GET['date']) ? $_GET['date'] : '';
    
    // Base query
    $query = "SELECT b.id, b.booking_reference, b.total_amount, b.booking_date, b.status, b.cancelled_at,
                     u.name as user_name, u.email as user_email,
                     m.title as movie_title,
                     t.name as theater_name,
                     s.date as show_date, s.time as show_time,
                     COUNT(bs.id) as seat_count
              FROM bookings b
              JOIN users u ON b.user_id = u.id
              JOIN showtimes s ON b.showtime_id = s.id
              JOIN movies m ON s.movie_id = m.id
              JOIN theaters t ON s.theater_id = t.id
              JOIN booking_seats bs ON b.id = bs.booking_id
              WHERE 1=1";
    $params = [];
    
    // Add search condition if provided
    if (!empty($search)) {
        $query .= " AND (b.booking_reference LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR m.title LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add status filter if provided
    if (!empty($status_filter)) {
        $query .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    // Add date filter if provided
    if (!empty($date_filter)) {
        $query .= " AND DATE(b.booking_date) = ?";
        $params[] = $date_filter;
    }
    
    // Add group by, ordering, and limit
    $query .= " GROUP BY b.id ORDER BY b.booking_date DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $records_per_page;
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error fetching bookings: " . $e->getMessage();
}

$pageTitle = "Manage Bookings - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Manage Bookings</h4>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php else: ?>
                        <!-- Search and Filter Form -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3">
                                    <div class="col-md-5">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="search" placeholder="Search by reference, user, email, or movie" value="<?= htmlspecialchars($search) ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="status" class="form-select">
                                            <option value="">All Statuses</option>
                                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date_filter) ?>" placeholder="Booking Date">
                                    </div>
                                    <div class="col-md-2">
                                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-sync-alt"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <?php if (empty($bookings)): ?>
                            <div class="alert alert-info">No bookings found matching your criteria.</div>
                        <?php else: ?>
                            <!-- Bookings Table -->
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>User</th>
                                            <th>Movie & Showtime</th>
                                            <th>Theater</th>
                                            <th>Seats</th>
                                            <th>Amount</th>
                                            <th>Booking Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($booking['booking_reference']) ?></td>
                                                <td>
                                                    <div><?= htmlspecialchars($booking['user_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($booking['user_email']) ?></small>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($booking['movie_title']) ?></div>
                                                    <small class="text-muted">
                                                        <?= date('d M Y', strtotime($booking['show_date'])) ?> at 
                                                        <?= date('h:i A', strtotime($booking['show_time'])) ?>
                                                    </small>
                                                </td>
                                                <td><?= htmlspecialchars($booking['theater_name']) ?></td>
                                                <td><?= $booking['seat_count'] ?> seat(s)</td>
                                                <td>₹<?= number_format($booking['total_amount'], 2) ?></td>
                                                <td>
                                                    <?= date('d M Y', strtotime($booking['booking_date'])) ?><br>
                                                    <small class="text-muted"><?= date('h:i A', strtotime($booking['booking_date'])) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                                        <span class="badge bg-success">Confirmed</span>
                                                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                                                        <span class="badge bg-danger">Cancelled</span>
                                                        <?php if ($booking['cancelled_at']): ?>
                                                            <small class="d-block text-muted">
                                                                <?= date('d M Y, h:i A', strtotime($booking['cancelled_at'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?>" aria-label="Last">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include_once '../includes/admin_footer.php'; ?>
