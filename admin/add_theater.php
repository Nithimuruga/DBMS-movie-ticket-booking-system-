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
$name = $location = $city = '';
$rows = 10;
$columns = 10;
$status = 'active';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $city = trim($_POST['city']);
    $rows = (int)$_POST['rows'];
    $columns = (int)$_POST['columns'];
    $status = $_POST['status'];
    
    // Validate input
    if (empty($name)) {
        $errors[] = "Theater name is required";
    }
    
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    
    if (empty($city)) {
        $errors[] = "City is required";
    }
    
    if ($rows <= 0 || $rows > 26) { // A-Z (26 letters max)
        $errors[] = "Rows must be between 1 and 26";
    }
    
    if ($columns <= 0 || $columns > 20) {
        $errors[] = "Columns must be between 1 and 20";
    }
    
    // Insert theater if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO theaters (
                    name, location, city, `rows`, `columns`, status, created_at
                ) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $name, $location, $city, $rows, $columns, $status
            ]);
            
            $success = true;
            
            // Reset form values
            $name = $location = $city = '';
            $rows = 10;
            $columns = 10;
            $status = 'active';
        } catch (PDOException $e) {
            $errors[] = "Error adding theater: " . $e->getMessage();
        }
    }
}

$pageTitle = "Add New Theater - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Add New Theater</h4>
                    <a href="manage_theaters.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-list me-1"></i> Manage Theaters
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Theater has been added successfully!
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
                                    <label for="name" class="form-label">Theater Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                                    <small class="form-text text-muted">E.g., PVR Cinemas, INOX, etc.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($location) ?>" required>
                                    <small class="form-text text-muted">E.g., MG Road, Mall of India, etc.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($city) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        <option value="under_maintenance" <?= $status === 'under_maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rows" class="form-label">Number of Rows <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="rows" name="rows" value="<?= htmlspecialchars($rows) ?>" min="1" max="26" required>
                                    <small class="form-text text-muted">Maximum 26 rows (A-Z)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="columns" class="form-label">Number of Columns <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="columns" name="columns" value="<?= htmlspecialchars($columns) ?>" min="1" max="20" required>
                                    <small class="form-text text-muted">Maximum 20 columns</small>
                                </div>
                                
                                <div class="mt-4">
                                    <div class="card bg-secondary">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Seat Layout Preview</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="seat-layout-preview">
                                                <div class="screen-container text-center mb-3">
                                                    <div class="screen mx-auto">
                                                        <div class="screen-text">SCREEN</div>
                                                    </div>
                                                    <div class="screen-shadow"></div>
                                                </div>
                                                
                                                <div id="seat-map-preview" class="seat-map-preview">
                                                    <!-- JavaScript will generate the preview -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <a href="manage_theaters.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Add Theater
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
    const rowsInput = document.getElementById('rows');
    const columnsInput = document.getElementById('columns');
    const seatMapPreview = document.getElementById('seat-map-preview');
    
    // Initial generation
    generateSeatPreview();
    
    // Update on input change
    rowsInput.addEventListener('input', generateSeatPreview);
    columnsInput.addEventListener('input', generateSeatPreview);
    
    function generateSeatPreview() {
        const rows = parseInt(rowsInput.value) || 0;
        const columns = parseInt(columnsInput.value) || 0;
        
        // Validate input
        if (rows <= 0 || rows > 26 || columns <= 0 || columns > 20) {
            seatMapPreview.innerHTML = '<div class="alert alert-warning">Please enter valid rows (1-26) and columns (1-20).</div>';
            return;
        }
        
        // Clear previous preview
        seatMapPreview.innerHTML = '';
        
        // Generate preview
        for (let r = 1; r <= Math.min(rows, 10); r++) {
            const rowDiv = document.createElement('div');
            rowDiv.className = 'seat-row-preview';
            
            // Row label
            const rowLabel = document.createElement('div');
            rowLabel.className = 'row-label-preview';
            rowLabel.textContent = String.fromCharCode(64 + r);
            rowDiv.appendChild(rowLabel);
            
            // Generate seats
            for (let c = 1; c <= Math.min(columns, 20); c++) {
                const seat = document.createElement('div');
                seat.className = 'seat-preview available-preview';
                seat.innerHTML = `<span class="seat-number-preview">${c}</span>`;
                rowDiv.appendChild(seat);
            }
            
            seatMapPreview.appendChild(rowDiv);
        }
        
        // Add note if preview is limited
        if (rows > 10 || columns > 20) {
            const noteDiv = document.createElement('div');
            noteDiv.className = 'text-center mt-3 small text-muted';
            noteDiv.textContent = '(Preview limited for display purposes)';
            seatMapPreview.appendChild(noteDiv);
        }
    }
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
