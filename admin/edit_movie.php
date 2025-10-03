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

// Check if movie ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_movies.php");
    exit;
}

$movie_id = (int)$_GET['id'];

// Get movie details
try {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$movie_id]);
    $movie = $stmt->fetch();
    
    if (!$movie) {
        header("Location: manage_movies.php");
        exit;
    }
    
    // Initialize form values with movie data
    $title = $movie['title'];
    $description = $movie['description'];
    $genre = $movie['genre'];
    $language = $movie['language'];
    $duration = $movie['duration'];
    $release_date = $movie['release_date'];
    $trailer_url = $movie['trailer_url'];
    $status = $movie['status'];
    $current_poster = $movie['poster'];
    
} catch (PDOException $e) {
    $errors[] = "Error fetching movie details: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $genre = trim($_POST['genre']);
    $language = trim($_POST['language']);
    $duration = (int)$_POST['duration'];
    $release_date = $_POST['release_date'];
    $trailer_url = trim($_POST['trailer_url']);
    $status = $_POST['status'];
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Movie title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Movie description is required";
    }
    
    if (empty($genre)) {
        $errors[] = "Genre is required";
    }
    
    if (empty($language)) {
        $errors[] = "Language is required";
    }
    
    if (empty($duration) || $duration <= 0) {
        $errors[] = "Valid duration is required";
    }
    
    if (empty($release_date)) {
        $errors[] = "Release date is required";
    } elseif (!validateDate($release_date)) {
        $errors[] = "Invalid release date format";
    }
    
    // Validate and process poster upload if a new poster is provided
    $poster_filename = $current_poster; // Default to current poster
    
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
        $file = $_FILES['poster'];
        $filename = $file['name'];
        $tmp_name = $file['tmp_name'];
        $file_size = $file['size'];
        
        // Check file size
        if ($file_size > MAX_FILE_SIZE) {
            $errors[] = "Poster file size exceeds the maximum limit of " . formatFileSize(MAX_FILE_SIZE);
        }
        
        // Check file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            $errors[] = "Only " . implode(', ', ALLOWED_EXTENSIONS) . " files are allowed for posters";
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $poster_filename = generateUniqueFilename($ext);
            
            // Create upload directory if it doesn't exist
            if (!file_exists(UPLOAD_PATH)) {
                mkdir(UPLOAD_PATH, 0777, true);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($tmp_name, UPLOAD_PATH . $poster_filename)) {
                $errors[] = "Failed to upload poster file";
            }
        }
    }
    
    // Update movie if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE movies SET
                    title = ?,
                    description = ?,
                    genre = ?,
                    language = ?,
                    duration = ?,
                    release_date = ?,
                    trailer_url = ?,
                    poster = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $title, $description, $genre, $language, $duration,
                $release_date, $trailer_url, $poster_filename, $status, $movie_id
            ]);
            
            // Remove old poster file if a new one was uploaded and it's different
            if ($poster_filename !== $current_poster && !empty($current_poster) && file_exists(UPLOAD_PATH . $current_poster)) {
                unlink(UPLOAD_PATH . $current_poster);
            }
            
            $pdo->commit();
            $success = true;
            
            // Update current poster variable to show the correct image
            $current_poster = $poster_filename;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error updating movie: " . $e->getMessage();
            
            // Delete newly uploaded file if error occurs and it's different from the original
            if ($poster_filename !== $current_poster && !empty($poster_filename) && file_exists(UPLOAD_PATH . $poster_filename)) {
                unlink(UPLOAD_PATH . $poster_filename);
            }
        }
    }
}

$pageTitle = "Edit Movie - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Edit Movie: <?= htmlspecialchars($title) ?></h4>
                    <a href="manage_movies.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-list me-1"></i> Manage Movies
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Movie has been updated successfully!
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
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $movie_id ?>" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Movie Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($description) ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="genre" class="form-label">Genre <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="genre" name="genre" value="<?= htmlspecialchars($genre) ?>" required>
                                            <div class="form-text text-muted">E.g., Action, Comedy, Drama, Thriller</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="language" class="form-label">Language <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="language" name="language" value="<?= htmlspecialchars($language) ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="duration" class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="duration" name="duration" value="<?= $duration ?>" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="release_date" class="form-label">Release Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="release_date" name="release_date" value="<?= $release_date ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trailer_url" class="form-label">Trailer URL</label>
                                    <input type="url" class="form-control" id="trailer_url" name="trailer_url" value="<?= htmlspecialchars($trailer_url) ?>">
                                    <div class="form-text text-muted">YouTube or other video platform URL</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        <option value="coming_soon" <?= $status === 'coming_soon' ? 'selected' : '' ?>>Coming Soon</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card bg-dark border">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Movie Poster</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="current-poster mb-3 text-center">
                                            <?php if (!empty($current_poster) && file_exists(UPLOAD_PATH . $current_poster)): ?>
                                                <img src="<?= UPLOAD_URL . htmlspecialchars($current_poster) ?>" 
                                                     alt="Current Poster" class="img-thumbnail" style="max-height: 300px;">
                                                <p class="mt-2">Current Poster</p>
                                            <?php else: ?>
                                                <div class="no-poster bg-secondary d-flex align-items-center justify-content-center" 
                                                     style="height: 300px;">
                                                    <i class="fas fa-film fa-3x"></i>
                                                </div>
                                                <p class="mt-2">No Poster Available</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="poster" class="form-label">Upload New Poster</label>
                                            <input type="file" class="form-control" id="poster" name="poster" accept="<?= implode(',', array_map(function($ext) { return '.'.$ext; }, ALLOWED_EXTENSIONS)) ?>">
                                            <div class="form-text text-muted">
                                                Max size: <?= formatFileSize(MAX_FILE_SIZE) ?><br>
                                                Allowed types: <?= implode(', ', ALLOWED_EXTENSIONS) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Update Movie
                            </button>
                            <a href="manage_movies.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/admin_footer.php'; ?>
