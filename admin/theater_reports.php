<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Initialize variables
$theaters = [];
$selected_theater = null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$theater_id = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : null;
$report_data = [];
$error = null;

// Get all theaters for the dropdown
try {
    $stmt = $pdo->prepare("SELECT id, name, location, city FROM theaters WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $theaters = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching theaters: " . $e->getMessage();
}

// If theater is selected, generate the report
if ($theater_id) {
    try {
        // Get theater details
        $stmt = $pdo->prepare("SELECT id, name, location, city FROM theaters WHERE id = ?");
        $stmt->execute([$theater_id]);
        $selected_theater = $stmt->fetch();
        
        if (!$selected_theater) {
            $error = "Selected theater not found";
        } else {
            // Get report data
            $stmt = $pdo->prepare("
                SELECT 
                    b.id as booking_id, 
                    b.booking_reference,
                    b.booking_date,
                    u.name as user_name, 
                    u.email as user_email,
                    m.title as movie_title, 
                    s.date as show_date, 
                    s.time as show_time,
                    t.name as theater_name,
                    t.location as theater_location,
                    t.city as theater_city,
                    GROUP_CONCAT(
                        CONCAT(
                            CHAR(bs.seat_row + 64), -- Convert to A, B, C...
                            bs.seat_column
                        ) 
                        ORDER BY bs.seat_row, bs.seat_column
                        SEPARATOR ', '
                    ) as seats,
                    COUNT(bs.id) as seat_count,
                    b.total_amount,
                    b.status
                FROM 
                    bookings b
                JOIN 
                    users u ON b.user_id = u.id
                JOIN 
                    showtimes s ON b.showtime_id = s.id
                JOIN 
                    theaters t ON s.theater_id = t.id
                JOIN 
                    movies m ON s.movie_id = m.id
                JOIN 
                    booking_seats bs ON b.id = bs.booking_id
                WHERE 
                    t.id = ? AND
                    s.date BETWEEN ? AND ? AND
                    b.status = 'confirmed'
                GROUP BY 
                    b.id
                ORDER BY 
                    s.date ASC, s.time ASC, b.booking_date ASC
            ");
            $stmt->execute([$theater_id, $start_date, $end_date]);
            $report_data = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Error generating report: " . $e->getMessage();
    }
}

// Calculate summary statistics
$total_bookings = count($report_data);
$total_seats = 0;
$total_revenue = 0;
$movies_count = [];
$date_counts = [];

foreach ($report_data as $row) {
    $total_seats += $row['seat_count'];
    $total_revenue += $row['total_amount'];
    
    // Count by movie
    if (!isset($movies_count[$row['movie_title']])) {
        $movies_count[$row['movie_title']] = 0;
    }
    $movies_count[$row['movie_title']] += $row['seat_count'];
    
    // Count by date
    $date = $row['show_date'];
    if (!isset($date_counts[$date])) {
        $date_counts[$date] = 0;
    }
    $date_counts[$date] += $row['seat_count'];
}

$pageTitle = "Theater Reports - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Theater Reports</h4>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <!-- Report Filter Form -->
                    <form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="theater_id" class="form-label">Select Theater</label>
                            <select class="form-select" id="theater_id" name="theater_id" required>
                                <option value="">-- Select Theater --</option>
                                <?php foreach ($theaters as $theater): ?>
                                    <option value="<?= $theater['id'] ?>" <?= $theater_id == $theater['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($theater['name']) ?> 
                                        (<?= htmlspecialchars($theater['location']) ?>, <?= htmlspecialchars($theater['city']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i> Generate Report
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($theater_id && $selected_theater && empty($error)): ?>
                        <div class="report-container">
                            <!-- Report Header -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h3><?= htmlspecialchars($selected_theater['name']) ?></h3>
                                    <p class="text-muted">
                                        <?= htmlspecialchars($selected_theater['location']) ?>, 
                                        <?= htmlspecialchars($selected_theater['city']) ?>
                                    </p>
                                    <p>
                                        Report Period: <?= date('d M Y', strtotime($start_date)) ?> to 
                                        <?= date('d M Y', strtotime($end_date)) ?>
                                    </p>
                                </div>
                                <div>
                                    <button onclick="window.print()" class="btn btn-outline-light">
                                        <i class="fas fa-print me-1"></i> Print Report
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Summary Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Bookings</h5>
                                            <h2><?= $total_bookings ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Seats Booked</h5>
                                            <h2><?= $total_seats ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Revenue</h5>
                                            <h2>₹<?= number_format($total_revenue, 2) ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-dark mb-3">
                                        <div class="card-body">
                                            <h5 class="card-title">Unique Movies</h5>
                                            <h2><?= count($movies_count) ?></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($report_data)): ?>
                                <div class="alert alert-info">No bookings found for the selected theater in this date range.</div>
                            <?php else: ?>
                                <!-- Report Data Table -->
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Show Date/Time</th>
                                                <th>Movie</th>
                                                <th>User</th>
                                                <th>Seats</th>
                                                <th>Booking Reference</th>
                                                <th>Booking Date</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $row): ?>
                                                <tr>
                                                    <td>
                                                        <?= date('d M Y', strtotime($row['show_date'])) ?><br>
                                                        <small class="text-muted"><?= date('h:i A', strtotime($row['show_time'])) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['movie_title']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($row['user_name']) ?><br>
                                                        <small class="text-muted"><?= htmlspecialchars($row['user_email']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= $row['seat_count'] ?> seats</span>
                                                        <small class="d-block mt-1"><?= htmlspecialchars($row['seats']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($row['booking_reference']) ?></span>
                                                    </td>
                                                    <td>
                                                        <?= date('d M Y', strtotime($row['booking_date'])) ?><br>
                                                        <small class="text-muted"><?= date('h:i A', strtotime($row['booking_date'])) ?></small>
                                                    </td>
                                                    <td>₹<?= number_format($row['total_amount'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Movie Statistics -->
                                <div class="row mt-5">
                                    <div class="col-md-6">
                                        <div class="card bg-dark text-light">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Seats Booked by Movie</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-dark table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Movie</th>
                                                                <th>Seats</th>
                                                                <th>Percentage</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            arsort($movies_count);
                                                            foreach ($movies_count as $movie => $count): 
                                                                $percentage = ($count / $total_seats) * 100;
                                                            ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($movie) ?></td>
                                                                    <td><?= $count ?></td>
                                                                    <td>
                                                                        <div class="progress">
                                                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                                                style="width: <?= $percentage ?>%"
                                                                                aria-valuenow="<?= $percentage ?>" 
                                                                                aria-valuemin="0" 
                                                                                aria-valuemax="100">
                                                                                <?= number_format($percentage, 1) ?>%
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-dark text-light">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Seats Booked by Date</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-dark table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Seats</th>
                                                                <th>Percentage</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            ksort($date_counts);
                                                            foreach ($date_counts as $date => $count): 
                                                                $percentage = ($count / $total_seats) * 100;
                                                            ?>
                                                                <tr>
                                                                    <td><?= date('d M Y', strtotime($date)) ?></td>
                                                                    <td><?= $count ?></td>
                                                                    <td>
                                                                        <div class="progress">
                                                                            <div class="progress-bar bg-success" role="progressbar" 
                                                                                style="width: <?= $percentage ?>%"
                                                                                aria-valuenow="<?= $percentage ?>" 
                                                                                aria-valuemin="0" 
                                                                                aria-valuemax="100">
                                                                                <?= number_format($percentage, 1) ?>%
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$theater_id): ?>
                        <div class="alert alert-info">Select a theater and date range to generate a report.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print-specific styles -->
<style media="print">
    .btn, .navbar, footer, form {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
    }
    .card-header {
        background-color: #f8f9fa !important;
        color: #000 !important;
        border-bottom: 1px solid #ddd !important;
    }
    .bg-dark {
        background-color: #fff !important;
    }
    .text-light {
        color: #000 !important;
    }
    .table-dark {
        background-color: #fff !important;
        color: #000 !important;
    }
    .table-dark th,
    .table-dark td {
        border-color: #ddd !important;
        background-color: #fff !important;
        color: #000 !important;
    }
    .text-muted {
        color: #666 !important;
    }
    .badge {
        border: 1px solid #ddd !important;
    }
    .badge.bg-info {
        background-color: #f0f8ff !important;
        color: #0077cc !important;
    }
    .badge.bg-secondary {
        background-color: #f3f3f3 !important;
        color: #666 !important;
    }
    .bg-primary.text-white {
        background-color: #f0f8ff !important;
        color: #000 !important;
        border: 1px solid #0077cc !important;
    }
    .bg-success.text-white {
        background-color: #f0fff0 !important;
        color: #000 !important;
        border: 1px solid #28a745 !important;
    }
    .bg-info.text-white {
        background-color: #e6f7ff !important;
        color: #000 !important;
        border: 1px solid #17a2b8 !important;
    }
    .bg-warning.text-dark {
        background-color: #fffaf0 !important;
        color: #000 !important;
        border: 1px solid #ffc107 !important;
    }
    .progress {
        border: 1px solid #ddd !important;
    }
    .progress-bar {
        background-color: #ddd !important;
        color: #000 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ensure end_date is not before start_date
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    startDateInput.addEventListener('change', function() {
        if (endDateInput.value && new Date(startDateInput.value) > new Date(endDateInput.value)) {
            endDateInput.value = startDateInput.value;
        }
    });
    
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value && new Date(endDateInput.value) < new Date(startDateInput.value)) {
            startDateInput.value = endDateInput.value;
        }
    });
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
