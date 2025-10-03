<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Check if theater ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_theaters.php");
    exit;
}

$theater_id = (int)$_GET['id'];
$errors = [];
$success = false;

// Get theater details
try {
    $stmt = $pdo->prepare("
        SELECT id, name, location, city, `rows`, `columns`, status 
        FROM theaters 
        WHERE id = ?
    ");
    $stmt->execute([$theater_id]);
    $theater = $stmt->fetch();
    
    if (!$theater) {
        header("Location: manage_theaters.php");
        exit;
    }
    
    // Initialize form values with theater data
    $name = $theater['name'];
    $location = $theater['location'];
    $city = $theater['city'];
    $rows = $theater['rows'];
    $columns = $theater['columns'];
    $status = $theater['status'];
} catch (PDOException $e) {
    $errors[] = "Error fetching theater details: " . $e->getMessage();
}

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
    
    // Update theater if no errors
    if (empty($errors)) {
        try {
            // Check if the rows and columns are being reduced
            if ($rows < $theater['rows'] || $columns < $theater['columns']) {
                // Check if there are any existing bookings for this theater that might have seats beyond the new dimensions
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM booking_seats bs
                    JOIN bookings b ON bs.booking_id = b.id
                    JOIN showtimes s ON b.showtime_id = s.id
                    WHERE s.theater_id = ?
                    AND (
                        bs.row_number > ? OR bs.column_number > ?
                    )
                ");
                $stmt->execute([$theater_id, $rows, $columns]);
                $booking_count = $stmt->fetchColumn();
                
                if ($booking_count > 0) {
                    $errors[] = "Cannot reduce theater size: There are bookings with seats that would be removed.";
                    // Don't proceed with update
                    throw new Exception("Theater size reduction would affect existing bookings");
                }
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Update theater details
            $stmt = $pdo->prepare("
                UPDATE theaters 
                SET name = ?, location = ?, city = ?, `rows` = ?, `columns` = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name, $location, $city, $rows, $columns, $status, $theater_id
            ]);
            
            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            if (!in_array("Theater size reduction would affect existing bookings", $errors)) {
                $errors[] = "Error updating theater: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Edit Theater - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Edit Theater</h4>
                    <a href="manage_theaters.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-list me-1"></i> Manage Theaters
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> Theater has been updated successfully!
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
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $theater_id ?>">
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
                                    <?php if (isset($theater) && $rows < $theater['rows']): ?>
                                        <div class="alert alert-warning mt-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i> Reducing rows may affect existing bookings.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="columns" class="form-label">Number of Columns <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="columns" name="columns" value="<?= htmlspecialchars($columns) ?>" min="1" max="20" required>
                                    <small class="form-text text-muted">Maximum 20 columns</small>
                                    <?php if (isset($theater) && $columns < $theater['columns']): ?>
                                        <div class="alert alert-warning mt-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i> Reducing columns may affect existing bookings.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="card bg-dark border border-secondary">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Seat Layout Preview</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="seat-layout-preview text-center bg-secondary p-3 rounded">
                                                <p class="mb-2"><strong>Screen</strong></p>
                                                <div class="screen-bar mb-4"></div>
                                                <div id="seat-preview-container">
                                                    <!-- Seat layout will be populated by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Update Theater</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to update seat layout preview
    function updateSeatPreview() {
        const rows = parseInt(document.getElementById('rows').value) || 0;
        const columns = parseInt(document.getElementById('columns').value) || 0;
        const container = document.getElementById('seat-preview-container');
        
        container.innerHTML = '';
        
        if (rows > 0 && columns > 0) {
            // Create seat layout preview
            for (let r = 0; r < Math.min(rows, 8); r++) {
                const rowElement = document.createElement('div');
                rowElement.className = 'seat-row mb-2';
                
                const rowLabel = document.createElement('span');
                rowLabel.className = 'text-light me-2';
                rowLabel.textContent = String.fromCharCode(65 + r); // A, B, C, ...
                rowElement.appendChild(rowLabel);
                
                for (let c = 0; c < Math.min(columns, 12); c++) {
                    const seat = document.createElement('div');
                    seat.className = 'seat-preview d-inline-block bg-primary m-1';
                    seat.style.width = '20px';
                    seat.style.height = '20px';
                    seat.style.borderRadius = '3px';
                    rowElement.appendChild(seat);
                }
                
                if (columns > 12) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'text-light ms-2';
                    ellipsis.textContent = '...';
                    rowElement.appendChild(ellipsis);
                }
                
                container.appendChild(rowElement);
            }
            
            if (rows > 8) {
                const ellipsisRow = document.createElement('div');
                ellipsisRow.className = 'text-center text-light mt-2';
                ellipsisRow.textContent = '...';
                container.appendChild(ellipsisRow);
            }
        } else {
            container.innerHTML = '<p class="text-danger">Please enter valid row and column counts</p>';
        }
    }
    
    // Add event listeners for input changes
    document.getElementById('rows').addEventListener('input', updateSeatPreview);
    document.getElementById('columns').addEventListener('input', updateSeatPreview);
    
    // Initial update
    updateSeatPreview();
});
</script>

<style>
.screen-bar {
    height: 6px;
    background: linear-gradient(to right, #6c757d, #ffffff, #6c757d);
    border-radius: 3px;
    width: 80%;
    margin: 0 auto;
}
</style>

<?php include_once '../includes/admin_footer.php'; ?>
