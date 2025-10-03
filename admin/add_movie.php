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
$title = $description = $genre = $language = $duration = $release_date = $trailer_url = '';
$status = 'active';

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
    
    // Validate and process poster upload
    $poster_filename = '';
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
    
    // Insert movie if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO movies (
                    title, description, genre, language, duration, 
                    release_date, trailer_url, poster, status, created_at
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $title, $description, $genre, $language, $duration,
                $release_date, $trailer_url, $poster_filename, $status
            ]);
            
            $success = true;
            
            // Reset form values
            $title = $description = $genre = $language = $duration = $release_date = $trailer_url = '';
            $status = 'active';
        } catch (PDOException $e) {
            $errors[] = "Error adding movie: " . $e->getMessage();
            
            // Delete uploaded file if error occurs
            if (!empty($poster_filename) && file_exists(UPLOAD_PATH . $poster_filename)) {
                unlink(UPLOAD_PATH . $poster_filename);
            }
        }
    }
}

$pageTitle = "Add New Movie - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Add New Movie</h4>
                    <a href="manage_movies.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-list me-1"></i> Manage Movies
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Movie has been added successfully!
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
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data">
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
                                            <small class="form-text text-muted">E.g., Action, Comedy, Drama, etc.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="language" class="form-label">Language <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="language" name="language" value="<?= htmlspecialchars($language) ?>" required>
                                            <small class="form-text text-muted">E.g., English, Hindi, Spanish, etc.</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="duration" class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="duration" name="duration" value="<?= htmlspecialchars($duration) ?>" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="release_date" class="form-label">Release Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="release_date" name="release_date" value="<?= htmlspecialchars($release_date) ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trailer_url" class="form-label">Trailer URL</label>
                                    <input type="url" class="form-control" id="trailer_url" name="trailer_url" value="<?= htmlspecialchars($trailer_url) ?>">
                                    <small class="form-text text-muted">YouTube or other video platform URL</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="poster" class="form-label">Movie Poster</label>
                                    <div class="poster-preview mb-2">
                                        <img id="poster-preview" src="https://pixabay.com/get/g9b1bfb92eb5909a18295c806fd46359e6b805aff4c2a4832a233a336b3ea007ac0b0f0846275ea1f8fcc97879c1ab5c0b7e8f8e0b1f558489dd0b5573a1db525_1280.jpg" class="img-fluid rounded border border-secondary" alt="Poster Preview">
                                    </div>
                                    <input type="file" class="form-control" id="poster" name="poster" accept=".jpg,.jpeg,.png">
                                    <small class="form-text text-muted">
                                        Max file size: <?= formatFileSize(MAX_FILE_SIZE) ?>. 
                                        Allowed formats: <?= implode(', ', ALLOWED_EXTENSIONS) ?>.
                                    </small>
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
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <a href="manage_movies.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Add Movie
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview uploaded image
    const posterInput = document.getElementById('poster');
    const posterPreview = document.getElementById('poster-preview');
    
    posterInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                posterPreview.src = e.target.result;
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
