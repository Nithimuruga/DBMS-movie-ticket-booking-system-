<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Handle theater deletion
if (isset($_POST['delete_theater']) && !empty($_POST['theater_id'])) {
    try {
        $theater_id = (int)$_POST['theater_id'];
        
        // Check if theater has showtimes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM showtimes WHERE theater_id = ?");
        $stmt->execute([$theater_id]);
        $showtime_count = $stmt->fetchColumn();
        
        if ($showtime_count > 0) {
            $delete_error = "Cannot delete theater. It has $showtime_count showtime(s) associated with it.";
        } else {
            // Delete theater
            $stmt = $pdo->prepare("DELETE FROM theaters WHERE id = ?");
            $stmt->execute([$theater_id]);
            
            $delete_success = true;
        }
    } catch (PDOException $e) {
        $delete_error = "Error deleting theater: " . $e->getMessage();
    }
}

// Get all theaters
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 10;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total records for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM theaters");
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get search/filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $city_filter = isset($_GET['city']) ? $_GET['city'] : '';
    
    // Base query
    $query = "SELECT t.*, 
                    (SELECT COUNT(*) FROM showtimes WHERE theater_id = t.id) AS showtime_count
              FROM theaters t
              WHERE 1=1";
    $params = [];
    
    // Add search condition if provided
    if (!empty($search)) {
        $query .= " AND (t.name LIKE ? OR t.location LIKE ? OR t.city LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add status filter if provided
    if (!empty($status_filter)) {
        $query .= " AND t.status = ?";
        $params[] = $status_filter;
    }
    
    // Add city filter if provided
    if (!empty($city_filter)) {
        $query .= " AND t.city = ?";
        $params[] = $city_filter;
    }
    
    // Add ordering and limit
    $query .= " ORDER BY t.name ASC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $records_per_page;
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $theaters = $stmt->fetchAll();
    
    // Get unique cities for filter dropdown
    $stmt = $pdo->prepare("SELECT DISTINCT city FROM theaters ORDER BY city");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Error fetching theaters: " . $e->getMessage();
}

$pageTitle = "Manage Theaters - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Manage Theaters</h4>
                    <a href="add_theater.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus me-1"></i> Add New Theater
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($delete_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Theater has been deleted successfully!
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
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="search" placeholder="Search by name, location or city" value="<?= htmlspecialchars($search) ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="status" class="form-select">
                                            <option value="">All Statuses</option>
                                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            <option value="under_maintenance" <?= $status_filter === 'under_maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="city" class="form-select">
                                            <option value="">All Cities</option>
                                            <?php foreach ($cities as $city): ?>
                                                <option value="<?= htmlspecialchars($city) ?>" <?= $city_filter === $city ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($city) ?>
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
                        
                        <?php if (empty($theaters)): ?>
                            <div class="alert alert-info">No theaters found matching your criteria.</div>
                        <?php else: ?>
                            <!-- Theaters Table -->
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Location</th>
                                            <th>City</th>
                                            <th>Capacity</th>
                                            <th>Showtimes</th>
                                            <th>Status</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($theaters as $theater): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($theater['name']) ?></td>
                                                <td><?= htmlspecialchars($theater['location']) ?></td>
                                                <td><?= htmlspecialchars($theater['city']) ?></td>
                                                <td>
                                                    <?= $theater['rows'] ?> rows × <?= $theater['columns'] ?> columns
                                                    <br>
                                                    <small class="text-muted">
                                                        (<?= $theater['rows'] * $theater['columns'] ?> seats)
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $theater['showtime_count'] ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($theater['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($theater['status'] === 'inactive'): ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php elseif ($theater['status'] === 'under_maintenance'): ?>
                                                        <span class="badge bg-warning text-dark">Under Maintenance</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit_theater.php?id=<?= $theater['id'] ?>" class="btn btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger delete-theater-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#deleteTheaterModal" 
                                                                data-theater-id="<?= $theater['id'] ?>" 
                                                                data-theater-name="<?= htmlspecialchars($theater['name']) ?>"
                                                                data-has-showtimes="<?= $theater['showtime_count'] > 0 ? '1' : '0' ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                        <a href="add_showtime.php?theater_id=<?= $theater['id'] ?>" class="btn btn-success" title="Add Showtime">
                                                            <i class="fas fa-clock"></i>
                                                        </a>
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
                                                <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($city_filter) ? '&city=' . urlencode($city_filter) : '' ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($city_filter) ? '&city=' . urlencode($city_filter) : '' ?>" aria-label="Previous">
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
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($city_filter) ? '&city=' . urlencode($city_filter) : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($city_filter) ? '&city=' . urlencode($city_filter) : '' ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($city_filter) ? '&city=' . urlencode($city_filter) : '' ?>" aria-label="Last">
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

<!-- Delete Theater Modal -->
<div class="modal fade" id="deleteTheaterModal" tabindex="-1" aria-labelledby="deleteTheaterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="deleteTheaterModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the theater "<span id="theaterNameToDelete"></span>"?</p>
                <div id="theaterHasShowtimesWarning" class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> This theater has scheduled showtimes. You cannot delete it until all showtimes are removed.
                </div>
                <p class="text-danger mb-0" id="theaterDeleteWarning">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <input type="hidden" name="theater_id" id="theaterIdToDelete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_theater" class="btn btn-danger" id="deleteTheaterButton">Delete Theater</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up delete theater modal
    const deleteTheaterModal = document.getElementById('deleteTheaterModal');
    if (deleteTheaterModal) {
        deleteTheaterModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const theaterId = button.getAttribute('data-theater-id');
            const theaterName = button.getAttribute('data-theater-name');
            const hasShowtimes = button.getAttribute('data-has-showtimes') === '1';
            
            document.getElementById('theaterIdToDelete').value = theaterId;
            document.getElementById('theaterNameToDelete').textContent = theaterName;
            
            const showtimesWarning = document.getElementById('theaterHasShowtimesWarning');
            const deleteButton = document.getElementById('deleteTheaterButton');
            
            if (hasShowtimes) {
                showtimesWarning.style.display = 'block';
                deleteButton.disabled = true;
            } else {
                showtimesWarning.style.display = 'none';
                deleteButton.disabled = false;
            }
        });
    }
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
