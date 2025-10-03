<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Get movie ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$movie_id = (int)$_GET['id'];

// Get movie details
try {
    $stmt = $pdo->prepare("
        SELECT m.*, AVG(IFNULL(r.rating, 0)) as avg_rating, COUNT(r.id) as rating_count
        FROM movies m
        LEFT JOIN ratings r ON m.id = r.movie_id
        WHERE m.id = ? AND m.status = 'active'
        GROUP BY m.id
    ");
    $stmt->execute([$movie_id]);
    $movie = $stmt->fetch();

    if (!$movie) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Error fetching movie details: " . $e->getMessage();
}

// Get theaters and showtimes for this movie
try {
    $stmt = $pdo->prepare("
        SELECT t.id as theater_id, t.name as theater_name, t.location, t.city,
               s.id as showtime_id, s.date, s.time, s.price
        FROM theaters t
        JOIN showtimes s ON t.id = s.theater_id
        WHERE s.movie_id = ? AND s.date >= CURDATE() AND t.status = 'active'
        ORDER BY s.date, t.name, s.time
    ");
    $stmt->execute([$movie_id]);
    $showtimes = $stmt->fetchAll();

    // Group showtimes by date and theater
    $showtimes_grouped = [];
    
    foreach ($showtimes as $showtime) {
        $date = $showtime['date'];
        $theater_id = $showtime['theater_id'];
        
        if (!isset($showtimes_grouped[$date])) {
            $showtimes_grouped[$date] = [];
        }
        
        if (!isset($showtimes_grouped[$date][$theater_id])) {
            $showtimes_grouped[$date][$theater_id] = [
                'theater_name' => $showtime['theater_name'],
                'location' => $showtime['location'],
                'city' => $showtime['city'],
                'times' => []
            ];
        }
        
        $showtimes_grouped[$date][$theater_id]['times'][] = [
            'id' => $showtime['showtime_id'],
            'time' => $showtime['time'],
            'price' => $showtime['price']
        ];
    }
} catch (PDOException $e) {
    $error = "Error fetching showtimes: " . $e->getMessage();
}

// Get reviews for this movie
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at, u.name as user_name
        FROM ratings r
        JOIN users u ON r.user_id = u.id
        WHERE r.movie_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$movie_id]);
    $reviews = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching reviews: " . $e->getMessage();
}

$pageTitle = $movie['title'] . " - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php else: ?>
        <!-- Movie Banner Section -->
        <div class="row mb-4">
            <div class="col-12">
                <?php 
                $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $movie['poster'];
                $has_poster = !empty($movie['poster']) && file_exists($poster_path);
                $poster_url = $has_poster ? '../uploads/movie_posters/' . $movie['poster'] : 'https://via.placeholder.com/800x400?text=No+Movie+Banner';
                ?>
                <div class="movie-banner rounded shadow" style="background-image: url(<?= $poster_url ?>);">
                    <div class="movie-overlay rounded">
                        <div class="container py-5">
                            <div class="row align-items-center">
                                <div class="col-md-4 col-lg-3 mb-4 mb-md-0">
                                    <!-- Movie Poster -->
                                    <div class="movie-poster-container">
                                        <?php if ($has_poster): ?>
                                            <img src="../uploads/movie_posters/<?= htmlspecialchars($movie['poster']) ?>" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($movie['title']) ?> Poster">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/350x500?text=No+Poster" class="img-fluid rounded shadow" alt="<?= htmlspecialchars($movie['title']) ?> Poster">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-9">
                                    <!-- Movie Details -->
                                    <h1 class="text-white mb-2"><?= htmlspecialchars($movie['title']) ?></h1>
                                    <div class="d-flex flex-wrap mb-3">
                                        <span class="badge bg-primary me-2 mb-2"><?= htmlspecialchars($movie['genre']) ?></span>
                                        <span class="badge bg-primary me-2 mb-2"><?= htmlspecialchars($movie['language']) ?></span>
                                        <span class="badge bg-primary me-2 mb-2"><?= htmlspecialchars(formatDuration($movie['duration'])) ?></span>
                                        <span class="badge bg-primary me-2 mb-2">Release: <?= date('M d, Y', strtotime($movie['release_date'])) ?></span>
                                    </div>
                                    <div class="rating-container mb-3">
                                        <div class="stars">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <?php if($i <= round($movie['avg_rating'])): ?>
                                                    <i class="fa fa-star text-warning"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-star text-secondary"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            <span class="text-light ms-2"><?= number_format($movie['avg_rating'], 1) ?>/5 (<?= $movie['rating_count'] ?> ratings)</span>
                                        </div>
                                    </div>
                                    <p class="text-light mb-4"><?= nl2br(htmlspecialchars($movie['description'])) ?></p>
                                    
                                    <?php if (!empty($movie['trailer_url'])): ?>
                                        <a href="<?= htmlspecialchars($movie['trailer_url']) ?>" target="_blank" class="btn btn-outline-light me-2">
                                            <i class="fa fa-play-circle me-1"></i> Watch Trailer
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Showtimes Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card bg-dark text-light shadow">
                    <div class="card-header bg-primary">
                        <h3 class="mb-0">Select Showtime</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($showtimes_grouped)): ?>
                            <div class="alert alert-info">No showtimes available for this movie.</div>
                        <?php else: ?>
                            <!-- Date Selection Tabs -->
                            <ul class="nav nav-tabs mb-4" id="showDateTabs" role="tablist">
                                <?php $first_date = true; ?>
                                <?php foreach (array_keys($showtimes_grouped) as $index => $date): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= $first_date ? 'active' : '' ?>" 
                                                id="date-<?= $index ?>-tab" 
                                                data-bs-toggle="tab" 
                                                data-bs-target="#date-<?= $index ?>" 
                                                type="button" 
                                                role="tab" 
                                                aria-controls="date-<?= $index ?>" 
                                                aria-selected="<?= $first_date ? 'true' : 'false' ?>">
                                            <span class="d-block"><?= date('D', strtotime($date)) ?></span>
                                            <span class="d-block fw-bold"><?= date('M d', strtotime($date)) ?></span>
                                        </button>
                                    </li>
                                    <?php $first_date = false; ?>
                                <?php endforeach; ?>
                            </ul>
                            
                            <!-- Date Tab Contents -->
                            <div class="tab-content" id="showDateTabContent">
                                <?php $first_date = true; ?>
                                <?php foreach (array_keys($showtimes_grouped) as $index => $date): ?>
                                    <div class="tab-pane fade <?= $first_date ? 'show active' : '' ?>" 
                                         id="date-<?= $index ?>" 
                                         role="tabpanel" 
                                         aria-labelledby="date-<?= $index ?>-tab">
                                        
                                        <?php foreach ($showtimes_grouped[$date] as $theater_id => $theater): ?>
                                            <div class="theater-container mb-4 pb-4 border-bottom border-secondary">
                                                <div class="theater-info mb-3">
                                                    <h4><?= htmlspecialchars($theater['theater_name']) ?></h4>
                                                    <p class="text-muted">
                                                        <?= htmlspecialchars($theater['location']) ?>, <?= htmlspecialchars($theater['city']) ?>
                                                    </p>
                                                </div>
                                                <div class="showtime-buttons">
                                                    <?php foreach ($theater['times'] as $time): ?>
                                                        <a href="select_seats.php?showtime_id=<?= $time['id'] ?>" class="btn btn-outline-primary me-2 mb-2">
                                                            <?= date('h:i A', strtotime($time['time'])) ?>
                                                            <div class="small">₹<?= number_format($time['price'], 2) ?></div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php $first_date = false; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="row">
            <div class="col-12">
                <div class="card bg-dark text-light shadow">
                    <div class="card-header bg-primary">
                        <h3 class="mb-0">Reviews & Ratings</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="add-review-form mb-4">
                                <h5>Add Your Review</h5>
                                <form action="../frontend/add_review.php" method="POST">
                                    <input type="hidden" name="movie_id" value="<?= $movie_id ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Your Rating</label>
                                        <div class="rating-select">
                                            <select class="form-select" name="rating" required>
                                                <option value="">Select Rating</option>
                                                <option value="5">5 - Excellent</option>
                                                <option value="4">4 - Very Good</option>
                                                <option value="3">3 - Good</option>
                                                <option value="2">2 - Fair</option>
                                                <option value="1">1 - Poor</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Your Comment</label>
                                        <textarea class="form-control" name="comment" rows="3" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Submit Review</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-4">
                                <a href="./index.php" class="alert-link">Login</a> to leave a review.
                            </div>
                        <?php endif; ?>

                        <!-- Display Reviews -->
                        <div class="reviews-container">
                            <h5>User Reviews (<?= count($reviews) ?>)</h5>
                            
                            <?php if (empty($reviews)): ?>
                                <div class="text-muted">No reviews yet. Be the first to review!</div>
                            <?php else: ?>
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-card mb-3 p-3 border border-secondary rounded">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="fw-bold"><?= htmlspecialchars($review['user_name']) ?></span>
                                                <div class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['rating']): ?>
                                                            <i class="fa fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="fa fa-star-o"></i>
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
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
