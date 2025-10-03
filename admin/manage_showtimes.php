<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Handle showtime deletion
if (isset($_POST['delete_showtime']) && !empty($_POST['showtime_id'])) {
    try {
        $showtime_id = (int)$_POST['showtime_id'];
        
        // Check if showtime has bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE showtime_id = ?");
        $stmt->execute([$showtime_id]);
        $booking_count = $stmt->fetchColumn();
        
        if ($booking_count > 0) {
            $delete_error = "Cannot delete showtime. It has $booking_count booking(s) associated with it.";
        } else {
            // Delete showtime
            $stmt = $pdo->prepare("DELETE FROM showtimes WHERE id = ?");
            $stmt->execute([$showtime_id]);
            
            $delete_success = true;
        }
    } catch (PDOException $e) {
        $delete_error = "Error deleting showtime: " . $e->getMessage();
    }
}

// Get all showtimes
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 15;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total records for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM showtimes");
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get search/filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $date_filter = isset($_GET['date']) ? $_GET['date'] : '';
    $movie_filter = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : '';
    $theater_filter = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : '';
    
    // Base query
    $query = "SELECT s.id, s.date, s.time, s.price,
                     m.id as movie_id, m.title as movie_title,
                     t.id as theater_id, t.name as theater_name, t.location, t.city,
                     (SELECT COUNT(*) FROM bookings WHERE showtime_id = s.id) as booking_count
              FROM showtimes s
              JOIN movies m ON s.movie_id = m.id
              JOIN theaters t ON s.theater_id = t.id
              WHERE 1=1";
    $params = [];
    
    // Add search condition if provided
    if (!empty($search)) {
        $query .= " AND (m.title LIKE ? OR t.name LIKE ? OR t.location LIKE ? OR t.city LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add date filter if provided
    if (!empty($date_filter)) {
        $query .= " AND s.date = ?";
        $params[] = $date_filter;
    }
    
    // Add movie filter if provided
    if (!empty($movie_filter)) {
        $query .= " AND s.movie_id = ?";
        $params[] = $movie_filter;
    }
    
    // Add theater filter if provided
    if (!empty($theater_filter)) {
        $query .= " AND s.theater_id = ?";
        $params[] = $theater_filter;
    }
    
    // Add ordering and limit
    $query .= " ORDER BY s.date DESC, s.time ASC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $records_per_page;
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $showtimes = $stmt->fetchAll();
    
    // Get movies for filter dropdown
    $stmt = $pdo->prepare("SELECT id, title FROM movies WHERE status = 'active' ORDER BY title");
    $stmt->execute();
    $movies = $stmt->fetchAll();
    
    // Get theaters for filter dropdown
    $stmt = $pdo->prepare("SELECT id, name, location, city FROM theaters WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $theaters = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error fetching showtimes: " . $e->getMessage();
}

$pageTitle = "Manage Showtimes - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Manage Showtimes</h4>
                    <a href="add_showtime.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus me-1"></i> Add New Showtime
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($delete_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Showtime has been deleted successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($delete_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($delete_error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php else: ?>
                        <!-- Search and Filter Form -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3">
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date_filter) ?>" placeholder="Select Date">
                                    </div>
                                    <div class="col-md-2">
                                        <select name="movie_id" class="form-select">
                                            <option value="">All Movies</option>
                                            <?php foreach ($movies as $movie): ?>
                                                <option value="<?= $movie['id'] ?>" <?= $movie_filter === $movie['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($movie['title']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="theater_id" class="form-select">
                                            <option value="">All Theaters</option>
                                            <?php foreach ($theaters as $theater): ?>
                                                <option value="<?= $theater['id'] ?>" <?= $theater_filter === $theater['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($theater['name']) ?> (<?= htmlspecialchars($theater['city']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-sync-alt"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <?php if (empty($showtimes)): ?>
                            <div class="alert alert-info">No showtimes found matching your criteria.</div>
                        <?php else: ?>
                            <!-- Showtimes Table -->
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Movie</th>
                                            <th>Theater</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Price</th>
                                            <th>Bookings</th>
                                            <th width="120">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($showtimes as $showtime): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($showtime['movie_title']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($showtime['theater_name']) ?>
                                                    <small class="text-muted d-block">
                                                        <?= htmlspecialchars($showtime['location']) ?>, <?= htmlspecialchars($showtime['city']) ?>
                                                    </small>
                                                </td>
                                                <td><?= date('d M Y', strtotime($showtime['date'])) ?></td>
                                                <td><?= date('h:i A', strtotime($showtime['time'])) ?></td>
                                                <td>₹<?= number_format($showtime['price'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= $showtime['booking_count'] ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit_showtime.php?id=<?= $showtime['id'] ?>" class="btn btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger delete-showtime-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#deleteShowtimeModal" 
                                                                data-showtime-id="<?= $showtime['id'] ?>" 
                                                                data-movie-title="<?= htmlspecialchars($showtime['movie_title']) ?>"
                                                                data-showtime-date="<?= date('d M Y', strtotime($showtime['date'])) ?>"
                                                                data-showtime-time="<?= date('h:i A', strtotime($showtime['time'])) ?>"
                                                                data-has-bookings="<?= $showtime['booking_count'] > 0 ? '1' : '0' ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
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
                                                <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?><?= !empty($movie_filter) ? '&movie_id=' . $movie_filter : '' ?><?= !empty($theater_filter) ? '&theater_id=' . $theater_filter : '' ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?><?= !empty($movie_filter) ? '&movie_id=' . $movie_filter : '' ?><?= !empty($theater_filter) ? '&theater_id=' . $theater_filter : '' ?>" aria-label="Previous">
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
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?><?= !empty($movie_filter) ? '&movie_id=' . $movie_filter : '' ?><?= !empty($theater_filter) ? '&theater_id=' . $theater_filter : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?><?= !empty($movie_filter) ? '&movie_id=' . $movie_filter : '' ?><?= !empty($theater_filter) ? '&theater_id=' . $theater_filter : '' ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . urlencode($date_filter) : '' ?><?= !empty($movie_filter) ? '&movie_id=' . $movie_filter : '' ?><?= !empty($theater_filter) ? '&theater_id=' . $theater_filter : '' ?>" aria-label="Last">
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

<!-- Delete Showtime Modal -->
<div class="modal fade" id="deleteShowtimeModal" tabindex="-1" aria-labelledby="deleteShowtimeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="deleteShowtimeModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the showtime for "<span id="movieTitleToDelete"></span>" on <span id="showtimeDateToDelete"></span> at <span id="showtimeTimeToDelete"></span>?</p>
                <div id="showtimeHasBookingsWarning" class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> This showtime has existing bookings. You cannot delete it.
                </div>
                <p class="text-danger mb-0" id="showtimeDeleteWarning">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <input type="hidden" name="showtime_id" id="showtimeIdToDelete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_showtime" class="btn btn-danger" id="deleteShowtimeButton">Delete Showtime</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up delete showtime modal
    const deleteShowtimeModal = document.getElementById('deleteShowtimeModal');
    if (deleteShowtimeModal) {
        deleteShowtimeModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const showtimeId = button.getAttribute('data-showtime-id');
            const movieTitle = button.getAttribute('data-movie-title');
            const showtimeDate = button.getAttribute('data-showtime-date');
            const showtimeTime = button.getAttribute('data-showtime-time');
            const hasBookings = button.getAttribute('data-has-bookings') === '1';
            
            document.getElementById('showtimeIdToDelete').value = showtimeId;
            document.getElementById('movieTitleToDelete').textContent = movieTitle;
            document.getElementById('showtimeDateToDelete').textContent = showtimeDate;
            document.getElementById('showtimeTimeToDelete').textContent = showtimeTime;
            
            const bookingsWarning = document.getElementById('showtimeHasBookingsWarning');
            const deleteButton = document.getElementById('deleteShowtimeButton');
            
            if (hasBookings) {
                bookingsWarning.style.display = 'block';
                deleteButton.disabled = true;
            } else {
                bookingsWarning.style.display = 'none';
                deleteButton.disabled = false;
            }
        });
    }
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
