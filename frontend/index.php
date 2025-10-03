<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Get current movies from database
try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.title, m.description, m.genre, m.duration, m.release_date, m.language, m.poster, m.trailer_url, 
               AVG(IFNULL(r.rating, 0)) as avg_rating, COUNT(DISTINCT s.id) as showtime_count
        FROM movies m
        LEFT JOIN showtimes s ON m.id = s.movie_id AND s.date >= CURDATE()
        LEFT JOIN ratings r ON m.id = r.movie_id
        WHERE m.status = 'active'
        GROUP BY m.id
        ORDER BY m.release_date DESC
    ");
    $stmt->execute();
    $movies = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching movies: " . $e->getMessage();
}

// Get genres for filter
try {
    $stmt = $pdo->prepare("SELECT DISTINCT genre FROM movies WHERE status = 'active'");
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $genres = [];
}

// Get languages for filter
try {
    $stmt = $pdo->prepare("SELECT DISTINCT language FROM movies WHERE status = 'active'");
    $stmt->execute();
    $languages = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $languages = [];
}

$pageTitle = "Home - " . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Hero Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="hero-banner rounded shadow" style="background-image: url('https://pixabay.com/get/gc170ff6d5e44eba06f493daf8bfd103ae00cd52bf88cbf6ab413500752a4d58eca4ff7f6fceb5957c474823796da06c4eacc170121ec66cfca3edc8b02002b75_1280.jpg');">
                <div class="hero-overlay rounded d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <h1 class="display-4 fw-bold text-white mb-3">Welcome to <?= SITE_NAME ?></h1>
                    <p class="lead text-white mb-4">Book your movie tickets online and enjoy the latest blockbusters!</p>
                    <form class="search-form" action="index.php" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg" name="search" placeholder="Search for movies...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fa fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Movies Section -->
    <div class="row">
        <!-- Filters Column -->
        <div class="col-lg-3 mb-4">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form action="index.php" method="GET">
                        <!-- Genre Filter -->
                        <div class="mb-3">
                            <label class="form-label">Genre</label>
                            <select class="form-select" name="genre">
                                <option value="">All Genres</option>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?= htmlspecialchars($genre) ?>"><?= htmlspecialchars($genre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Language Filter -->
                        <div class="mb-3">
                            <label class="form-label">Language</label>
                            <select class="form-select" name="language">
                                <option value="">All Languages</option>
                                <?php foreach ($languages as $language): ?>
                                    <option value="<?= htmlspecialchars($language) ?>"><?= htmlspecialchars($language) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Date Filter -->
                        <div class="mb-3">
                            <label class="form-label">Release Date</label>
                            <input type="date" class="form-control" name="release_date">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Movies Grid Column -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-light">Now Showing</h2>
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Sort By
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                        <li><a class="dropdown-item" href="?sort=newest">Newest First</a></li>
                        <li><a class="dropdown-item" href="?sort=rating">Highest Rated</a></li>
                        <li><a class="dropdown-item" href="?sort=title">Title (A-Z)</a></li>
                    </ul>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php elseif (empty($movies)): ?>
                <div class="alert alert-info">No movies found matching your criteria.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                    <?php foreach ($movies as $movie): ?>
                        <div class="col">
                            <div class="card h-100 movie-card bg-dark text-light shadow">
                                <div class="position-relative">
                                    <?php 
                                    $poster_path = dirname(__DIR__) . '/uploads/movie_posters/' . $movie['poster'];
                                    if (!empty($movie['poster']) && file_exists($poster_path)): 
                                    ?>
                                        <img src="../uploads/movie_posters/<?= htmlspecialchars($movie['poster']) ?>" class="card-img-top movie-poster" alt="<?= htmlspecialchars($movie['title']) ?> Poster">
                                    <?php else: ?>
                                        <img src="https://via.placeholder.com/350x500?text=No+Poster" class="card-img-top movie-poster" alt="<?= htmlspecialchars($movie['title']) ?> Poster">
                                    <?php endif; ?>
                                    <div class="movie-rating position-absolute top-0 end-0 m-2 bg-primary text-white px-2 py-1 rounded">
                                        <i class="fa fa-star"></i> <?= number_format($movie['avg_rating'], 1) ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($movie['title']) ?></h5>
                                    <div class="d-flex mb-2">
                                        <span class="badge bg-secondary me-2"><?= htmlspecialchars($movie['genre']) ?></span>
                                        <span class="badge bg-secondary me-2"><?= htmlspecialchars($movie['language']) ?></span>
                                        <span class="badge bg-secondary"><?= htmlspecialchars(formatDuration($movie['duration'])) ?></span>
                                    </div>
                                    <p class="card-text small text-truncate-3">
                                        <?= htmlspecialchars(substr($movie['description'], 0, 120)) ?>...
                                    </p>
                                </div>
                                <div class="card-footer border-top border-secondary">
                                    <div class="d-grid">
                                        <a href="movie_details.php?id=<?= $movie['id'] ?>" class="btn btn-primary">
                                            <i class="fa fa-ticket-alt me-1"></i> Book Tickets
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
