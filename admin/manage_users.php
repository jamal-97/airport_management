<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Prevent self-deletion
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        // Check if user exists
        $query = "SELECT id FROM users WHERE id = ?";
        $result = $db->executeQuery($query, "i", [$user_id]);
        
        if ($result && count($result) > 0) {
            // Delete user
            $query = "DELETE FROM users WHERE id = ?";
            $result = $db->executeQuery($query, "i", [$user_id]);
            
            if ($result) {
                // Log the action
                $admin_id = $_SESSION['user_id'];
                $query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'delete_user', ?, NOW())";
                $db->executeQuery($query, "is", [$admin_id, "Deleted user ID: $user_id"]);
                
                $success = "User has been deleted successfully.";
            } else {
                $error = "Failed to delete user. Please try again.";
            }
        } else {
            $error = "User not found.";
        }
    }
}

// Handle user status change
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Prevent changing own status
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot change your own account status.";
    } else {
        // Get current status
        $query = "SELECT status FROM users WHERE id = ?";
        $result = $db->executeQuery($query, "i", [$user_id]);
        
        if ($result && count($result) > 0) {
            $current_status = $result[0]['status'];
            $new_status = ($current_status == 'active') ? 'inactive' : 'active';
            
            // Update status
            $query = "UPDATE users SET status = ? WHERE id = ?";
            $result = $db->executeQuery($query, "si", [$new_status, $user_id]);
            
            if ($result) {
                // Log the action
                $admin_id = $_SESSION['user_id'];
                $query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'update_user_status', ?, NOW())";
                $db->executeQuery($query, "is", [$admin_id, "Changed user ID: $user_id status to $new_status"]);
                
                $success = "User status has been updated successfully.";
            } else {
                $error = "Failed to update user status. Please try again.";
            }
        } else {
            $error = "User not found.";
        }
    }
}

// Pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build the query
$query = "SELECT id, first_name, last_name, email, role, status, created_at FROM users WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$query_params = [];
$param_types = "";

// Add search condition if provided
if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $count_query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "sss";
}

// Add role filter if provided
if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $count_query .= " AND role = ?";
    $query_params[] = $role_filter;
    $param_types .= "s";
}

// Add status filter if provided
if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $query_params[] = $status_filter;
    $param_types .= "s";
}

// Add order by and limit
$query .= " ORDER BY created_at DESC LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Execute count query
$count_result = $db->executeQuery($count_query, empty($param_types) ? null : substr($param_types, 0, -2), array_slice($query_params, 0, -2));
$total_users = $count_result[0]['total'];
$total_pages = ceil($total_users / $limit);

// Execute main query
$users = $db->executeQuery($query, $param_types, $query_params);

// Include header
$page_title = "Manage Users";
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Users</h1>
    <a href="add_user.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Add New User
    </a>
</div>

<!-- Filter and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="manage_users.php" class="row">
            <div class="col-md-4 mb-3">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or Email">
            </div>
            <div class="col-md-3 mb-3">
                <label for="role">Role</label>
                <select name="role" id="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo ($role_filter == 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="staff" <?php echo ($role_filter == 'staff') ? 'selected' : ''; ?>>Staff</option>
                    <option value="traveller" <?php echo ($role_filter == 'traveller') ? 'selected' : ''; ?>>Traveller</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-0">Users List</h5>
            </div>
            <div class="col-md-6 text-right">
                <span class="badge badge-primary"><?php echo number_format($total_users); ?> Total Users</span>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($users) > 0): ?>
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <span class="status-badge badge-<?php echo getRoleBadgeClass($user['role']); ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo ($user['status'] == 'active') ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" data-toggle="tooltip" title="View User">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" data-toggle="tooltip" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=toggle_status&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-<?php echo ($user['status'] == 'active') ? 'warning' : 'success'; ?>" data-toggle="tooltip" title="<?php echo ($user['status'] == 'active') ? 'Deactivate' : 'Activate'; ?> User" onclick="return confirm('Are you sure you want to <?php echo ($user['status'] == 'active') ? 'deactivate' : 'activate'; ?> this user?');">
                                            <i class="fas fa-<?php echo ($user['status'] == 'active') ? 'ban' : 'check'; ?>"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" data-toggle="tooltip" title="Delete User" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
                <nav aria-label="User navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">
                                    First
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">
                                    Next
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($role_filter) ? '&role=' . urlencode($role_filter) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">
                                    Last
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No users found matching your criteria.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Helper function for role badge class
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin':
            return 'danger';
        case 'staff':
            return 'warning';
        case 'traveller':
            return 'info';
        default:
            return 'secondary';
    }
}

include '../includes/footer.php';

?>