<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != ROLE_ADMIN) {
    header("Location: index.php");
    exit;
}

// Handle user status change (activate/deactivate)
if (isset($_POST['update_status']) && !empty($_POST['user_id'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['status'] === '1' ? 1 : 0;
        
        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        $status_message = $new_status ? "User has been promoted to admin." : "Admin has been changed to regular user.";
        $status_success = true;
    } catch (PDOException $e) {
        $status_error = "Error changing user status: " . $e->getMessage();
    }
}

// Handle user deletion
if (isset($_POST['delete_user']) && !empty($_POST['user_id'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        
        // Check if this is the last admin
        if ($_POST['is_admin'] == '1') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 1");
            $stmt->execute();
            $admin_count = $stmt->fetchColumn();
            
            if ($admin_count <= 1) {
                throw new Exception("Cannot delete the last admin account.");
            }
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete user's ratings
        $stmt = $pdo->prepare("DELETE FROM ratings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Get all bookings for this user to delete related booking_seats
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $bookings = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($bookings)) {
            // Delete booking seats
            $stmt = $pdo->prepare("DELETE FROM booking_seats WHERE booking_id IN (" . implode(',', array_fill(0, count($bookings), '?')) . ")");
            $stmt->execute($bookings);
            
            // Delete bookings
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $delete_success = true;
    } catch (Exception $e) {
        // Roll back transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $delete_error = "Error deleting user: " . $e->getMessage();
    }
}

// Get all users
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 10;
    $offset = ($page - 1) * $records_per_page;
    
    // Get search parameter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $role_filter = isset($_GET['role']) && $_GET['role'] !== '' ? (int)$_GET['role'] : null;
    
    // Count total users for pagination
    $count_query = "SELECT COUNT(*) FROM users WHERE 1=1";
    $count_params = [];
    
    if (!empty($search)) {
        $count_query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_param = "%$search%";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }
    
    if (isset($role_filter)) {
        $count_query .= " AND role = ?";
        $count_params[] = $role_filter;
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get users with pagination
    $query = "SELECT id, name, email, phone, role, created_at, last_login FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (isset($role_filter)) {
        $query .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $records_per_page;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}

$pageTitle = "Manage Users - " . SITE_NAME;
include_once '../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-light">
                <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Manage Users</h4>
                    <a href="add_user.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus me-1"></i> Add New User
                    </a>
                </div>
                <div class="card-body">                    <?php if (isset($_SESSION['user_added'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> User has been added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['user_added']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_updated'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> User has been updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['user_updated']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($status_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($status_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($status_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($status_error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($delete_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> User has been deleted successfully!
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
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="search" placeholder="Search by name, email or phone" value="<?= htmlspecialchars($search) ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="role" class="form-select">
                                            <option value="">All Users</option>
                                            <option value="0" <?= $role_filter === 0 ? 'selected' : '' ?>>Regular Users</option>
                                            <option value="1" <?= $role_filter === 1 ? 'selected' : '' ?>>Admins</option>
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
                        
                        <?php if (empty($users)): ?>
                            <div class="alert alert-info">No users found matching your criteria.</div>
                        <?php else: ?>
                            <!-- Users Table -->
                            <div class="table-responsive">
                                <table class="table table-dark table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Registered On</th>
                                            <th>Last Login</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($user['name']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                                <td>
                                                    <?php if ($user['role'] == 1): ?>
                                                        <span class="badge bg-primary">Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">User</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                                <td>
                                                    <?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="view_user.php?id=<?= $user['id'] ?>" class="btn btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <button type="button" class="btn <?= $user['role'] == 1 ? 'btn-warning' : 'btn-success' ?> change-role-btn" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#changeRoleModal" 
                                                                    data-user-id="<?= $user['id'] ?>" 
                                                                    data-user-name="<?= htmlspecialchars($user['name']) ?>"
                                                                    data-current-role="<?= $user['role'] ?>"
                                                                    title="<?= $user['role'] == 1 ? 'Change to User' : 'Promote to Admin' ?>">
                                                                <i class="fas <?= $user['role'] == 1 ? 'fa-user' : 'fa-user-shield' ?>"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-danger delete-user-btn" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deleteUserModal" 
                                                                    data-user-id="<?= $user['id'] ?>" 
                                                                    data-user-name="<?= htmlspecialchars($user['name']) ?>"
                                                                    data-is-admin="<?= $user['role'] ?>"
                                                                    title="Delete">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
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
                                                <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= isset($role_filter) ? '&role=' . urlencode($role_filter) : '' ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= isset($role_filter) ? '&role=' . urlencode($role_filter) : '' ?>" aria-label="Previous">
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
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= isset($role_filter) ? '&role=' . urlencode($role_filter) : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= isset($role_filter) ? '&role=' . urlencode($role_filter) : '' ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= isset($role_filter) ? '&role=' . urlencode($role_filter) : '' ?>" aria-label="Last">
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

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="changeRoleModalLabel">Change User Role</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to change the role of <span id="userNameForRole"></span>?</p>
                <p id="roleChangeMessage"></p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <input type="hidden" name="user_id" id="userIdForRole">
                    <input type="hidden" name="status" id="newUserRole">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Change Role</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the user "<span id="userNameToDelete"></span>"?</p>
                <p class="text-danger mb-0">This action cannot be undone and will also delete all related data including bookings and reviews.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <input type="hidden" name="user_id" id="userIdToDelete">
                    <input type="hidden" name="is_admin" id="isUserAdmin">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up change role modal
    const changeRoleModal = document.getElementById('changeRoleModal');
    if (changeRoleModal) {
        changeRoleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            const currentRole = button.getAttribute('data-current-role');
            
            document.getElementById('userIdForRole').value = userId;
            document.getElementById('userNameForRole').textContent = userName;
            
            if (currentRole === '1') { // Admin -> User
                document.getElementById('newUserRole').value = '0';
                document.getElementById('roleChangeMessage').textContent = 'This user will be changed from Admin to regular User.';
            } else { // User -> Admin
                document.getElementById('newUserRole').value = '1';
                document.getElementById('roleChangeMessage').textContent = 'This user will be promoted from regular User to Admin.';
            }
        });
    }
    
    // Set up delete user modal
    const deleteUserModal = document.getElementById('deleteUserModal');
    if (deleteUserModal) {
        deleteUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            const isAdmin = button.getAttribute('data-is-admin');
            
            document.getElementById('userIdToDelete').value = userId;
            document.getElementById('isUserAdmin').value = isAdmin;
            document.getElementById('userNameToDelete').textContent = userName;
        });
    }
});
</script>

<?php include_once '../includes/admin_footer.php'; ?>
