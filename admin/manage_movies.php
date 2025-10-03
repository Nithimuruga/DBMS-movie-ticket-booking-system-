<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Handle movie deletion
if (isset($_POST['delete_movie']) && !empty($_POST['movie_id'])) {
    try {
        $movie_id = (int)$_POST['movie_id'];
        
        // Get movie poster to delete the file
        $stmt = $pdo->prepare("SELECT poster FROM movies WHERE id = ?");
        $stmt->execute([$movie_id]);
        $movie = $stmt->fetch();
        
        // Begin transaction to ensure data integrity
        $pdo->beginTransaction();
        
        // Delete related bookings first to handle foreign key constraints
        // First, get all showtime IDs for the movie
        $stmt = $pdo->prepare("SELECT id FROM showtimes WHERE movie_id = ?");
        $stmt->execute([$movie_id]);
        $showtimes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($showtimes)) {
            // Delete booking_seats records for all bookings related to these showtimes
            $stmt = $pdo->prepare("DELETE bs FROM booking_seats bs 
                                  JOIN bookings b ON bs.booking_id = b.id 
                                  WHERE b.showtime_id IN (" . implode(',', array_fill(0, count($showtimes), '?')) . ")");
            $stmt->execute($showtimes);
            
            // Delete bookings related to these showtimes
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE showtime_id IN (" . implode(',', array_fill(0, count($showtimes), '?')) . ")");
            $stmt->execute($showtimes);
            
            // Delete showtimes for this movie
            $stmt = $pdo->prepare("DELETE FROM showtimes WHERE movie_id = ?");
            $stmt->execute([$movie_id]);
        }
        
        // Delete ratings for this movie
        $stmt = $pdo->prepare("DELETE FROM ratings WHERE movie_id = ?");
        $stmt->execute([$movie_id]);
        
        // Finally delete the movie
        $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->execute([$movie_id]);
        
        // Delete poster file if exists
        if ($movie && !empty($movie['poster']) && file_exists(UPLOAD_PATH . $movie['poster'])) {
            unlink(UPLOAD_PATH . $movie['poster']);
        }
        
        // Commit the transaction
        $pdo->commit();
        
        $delete_success = true;
    } catch (PDOException $e) {
        // Roll back the transaction on error
        $pdo->rollBack();
        $delete_error = "Error deleting movie: " . $e->getMessage();
    }
}

// Get all movies
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 10;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total records for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM movies");
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get search/filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $genre_filter = isset($_GET['genre']) ? $_GET['genre'] : '';
    
    // Base query
    $query = "SELECT * FROM movies WHERE 1=1";
    $params = [];
    
    // Add search condition if provided
    if (!empty($search)) {
        $query .= " AND (title LIKE ? OR description LIKE ? OR genre LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add status filter if provided
    if (!empty($status_filter)) {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    // Add genre filter if provided
    if (!empty($genre_filter)) {
        $query .= " AND genre = ?";
        $params[] = $genre_filter;
    }
    
    // Add ordering and limit
    $query .= " ORDER BY release_date DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $records_per_page;
    
    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $movies = $stmt->fetchAll();
    
    // Get unique genres for filter dropdown
    $stmt = $pdo->prepare("SELECT DISTINCT genre FROM movies ORDER BY genre");
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Error fetching movies: " . $e->getMessage();
}

$pageTitle = "Manage Movies - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Manage Movies</h4>
                    <a href="add_movie.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus me-1"></i> Add New Movie
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($delete_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Movie has been deleted successfully!
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
                                            <input type="text" class="form-control" name="search" placeholder="Search by title, description or genre" value="<?= htmlspecialchars($search) ?>">
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
                                            <option value="coming_soon" <?= $status_filter === 'coming_soon' ? 'selected' : '' ?>>Coming Soon</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="genre" class="form-select">
                                            <option value="">All Genres</option>
                                            <?php foreach ($genres as $genre): ?>
                                                <option value="<?= htmlspecialchars($genre) ?>" <?= $genre_filter === $genre ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($genre) ?>
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
                        
                        <?php if (empty($movies)): ?>
                            <div class="alert alert-info">No movies found matching your criteria.</div>
                        <?php else: ?>
                            <!-- Movies Table -->
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="80">Poster</th>
                                            <th>Title</th>
                                            <th>Genre</th>
                                            <th>Language</th>
                                            <th>Duration</th>
                                            <th>Release Date</th>
                                            <th>Status</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($movies as $movie): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($movie['poster']) && file_exists(UPLOAD_PATH . $movie['poster'])): ?>
                                                        <img src="<?= UPLOAD_URL . htmlspecialchars($movie['poster']) ?>" 
                                                             class="img-thumbnail" style="width: 60px; height: 80px; object-fit: cover;" 
                                                             alt="<?= htmlspecialchars($movie['title']) ?>">
                                                    <?php else: ?>
                                                        <div class="no-poster bg-secondary d-flex align-items-center justify-content-center" 
                                                             style="width: 60px; height: 80px;">
                                                            <i class="fas fa-film"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($movie['title']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($movie['genre']) ?></td>
                                                <td><?= htmlspecialchars($movie['language']) ?></td>
                                                <td><?= formatDuration($movie['duration']) ?></td>
                                                <td><?= date('d M Y', strtotime($movie['release_date'])) ?></td>
                                                <td>
                                                    <?php if ($movie['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($movie['status'] === 'inactive'): ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php elseif ($movie['status'] === 'coming_soon'): ?>
                                                        <span class="badge bg-warning text-dark">Coming Soon</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit_movie.php?id=<?= $movie['id'] ?>" class="btn btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-danger delete-movie-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#deleteMovieModal" 
                                                                data-movie-id="<?= $movie['id'] ?>" 
                                                                data-movie-title="<?= htmlspecialchars($movie['title']) ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                        <a href="add_showtime.php?movie_id=<?= $movie['id'] ?>" class="btn btn-success" title="Add Showtime">
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
                                                <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($genre_filter) ? '&genre=' . urlencode($genre_filter) : '' ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($genre_filter) ? '&genre=' . urlencode($genre_filter) : '' ?>" aria-label="Previous">
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
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($genre_filter) ? '&genre=' . urlencode($genre_filter) : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($genre_filter) ? '&genre=' . urlencode($genre_filter) : '' ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($genre_filter) ? '&genre=' . urlencode($genre_filter) : '' ?>" aria-label="Last">
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

<!-- Delete Movie Modal -->
<div class="modal fade" id="deleteMovieModal" tabindex="-1" aria-labelledby="deleteMovieModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="deleteMovieModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the movie "<span id="movieTitleToDelete"></span>"?</p>
                <p class="text-danger mb-0">This action cannot be undone and will also delete all related showtimes and bookings.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <input type="hidden" name="movie_id" id="movieIdToDelete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_movie" class="btn btn-danger">Delete Movie</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up delete movie modal
    const deleteMovieModal = document.getElementById('deleteMovieModal');
    if (deleteMovieModal) {
        deleteMovieModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const movieId = button.getAttribute('data-movie-id');
            const movieTitle = button.getAttribute('data-movie-title');
            
            document.getElementById('movieIdToDelete').value = movieId;
            document.getElementById('movieTitleToDelete').textContent = movieTitle;
        });
    }
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
