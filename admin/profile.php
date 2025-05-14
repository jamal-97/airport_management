<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = "";

// Get user data
$query = "SELECT id, first_name, last_name, email, role, created_at, status FROM users WHERE id = ?";
$result = $db->executeQuery($query, "i", [$user_id]);

if (!$result || count($result) == 0) {
    header("Location: dashboard.php?error=user_not_found");
    exit();
}

$user = $result[0];

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else if ($email !== $user['email']) {
        // Check if the new email already exists for another user
        $query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $result = $db->executeQuery($query, "si", [$email, $user_id]);
        
        if ($result && count($result) > 0) {
            $errors[] = "Email already exists.";
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?";
        $result = $db->executeQuery($query, "sssi", [$first_name, $last_name, $email, $user_id]);
        
        if ($result) {
            // Update session variables
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            
            // Log the action
            $query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'update_profile', 'Admin updated their profile', NOW())";
            $db->executeQuery($query, "i", [$user_id]);
            
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $query = "SELECT id, first_name, last_name, email, role, created_at, status FROM users WHERE id = ?";
            $result = $db->executeQuery($query, "i", [$user_id]);
            $user = $result[0];
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = "Current password is required.";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters.";
    }
    
    if (empty($confirm_password)) {
        $errors[] = "Please confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    
    // If no basic errors, verify current password
    if (empty($errors)) {
        $query = "SELECT password FROM users WHERE id = ?";
        $result = $db->executeQuery($query, "i", [$user_id]);
        
        if ($result && count($result) > 0) {
            $stored_password = $result[0]['password'];
            
            if (!password_verify($current_password, $stored_password)) {
                $errors[] = "Current password is incorrect.";
            }
        } else {
            $errors[] = "User not found.";
        }
    }
    
    // If all validations pass, update password
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $result = $db->executeQuery($query, "si", [$hashed_password, $user_id]);
        
        if ($result) {
            // Log the action
            $query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'change_password', 'Admin changed their password', NOW())";
            $db->executeQuery($query, "i", [$user_id]);
            
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Failed to change password. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get recent login history
$query = "SELECT login_time, ip_address, user_agent FROM login_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 5";
$login_history = $db->executeQuery($query, "i", [$user_id]);

// Get recent admin actions
$query = "SELECT action, details, created_at FROM admin_logs WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$admin_actions = $db->executeQuery($query, "i", [$user_id]);

// Include header
$page_title = "Admin Profile";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Profile Information</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <div class="text-center mb-4">
                        <div class="avatar-circle">
                            <span class="avatar-initials"><?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?></span>
                        </div>
                        <h4 class="mt-3"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h4>
                        <span class="badge bg-danger">Administrator</span>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-envelope"></i> Email</span>
                            <span><?php echo $user['email']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-shield"></i> Role</span>
                            <span class="badge bg-danger">Administrator</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-alt"></i> Member Since</span>
                            <span><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-circle"></i> Status</span>
                            <span class="badge bg-success">Active</span>
                        </li>
                    </ul>
                    
                    <div class="d-grid gap-2 mt-3">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Recent Login History</h4>
                </div>
                <div class="card-body">
                    <?php if ($login_history && count($login_history) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>IP Address</th>
                                        <th>Device</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($login_history as $login): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i:s', strtotime($login['login_time'])); ?></td>
                                            <td><?php echo $login['ip_address']; ?></td>
                                            <td><?php echo getBrowserInfo($login['user_agent']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No login history found.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Recent Actions</h4>
                </div>
                <div class="card-body">
                    <?php if ($admin_actions && count($admin_actions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admin_actions as $action): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i:s', strtotime($action['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getActionBadgeClass($action['action']); ?>">
                                                    <?php echo formatActionName($action['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $action['details']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No actions recorded yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="profile.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="profile.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
}

.avatar-initials {
    color: white;
    font-size: 40px;
    font-weight: bold;
}
</style>

<?php
// Helper function to get browser information
function getBrowserInfo($user_agent) {
    if (strpos($user_agent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($user_agent, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($user_agent, 'Safari') !== false) {
        $browser = 'Safari';
    } elseif (strpos($user_agent, 'Edge') !== false) {
        $browser = 'Edge';
    } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
        $browser = 'Internet Explorer';
    } else {
        $browser = 'Unknown Browser';
    }
    
    if (strpos($user_agent, 'Mobile') !== false) {
        $device = 'Mobile';
    } elseif (strpos($user_agent, 'Tablet') !== false) {
        $device = 'Tablet';
    } else {
        $device = 'Desktop';
    }
    
    return "$browser ($device)";
}

// Helper function to get badge class for action
function getActionBadgeClass($action) {
    switch ($action) {
        case 'add_user':
        case 'add_flight':
        case 'add_airport':
            return 'success';
        case 'edit_user':
        case 'edit_flight':
        case 'edit_airport':
        case 'update_profile':
        case 'change_password':
            return 'primary';
        case 'delete_user':
        case 'delete_flight':
        case 'delete_airport':
            return 'danger';
        case 'update_user_status':
        case 'update_flight_status':
            return 'warning';
        default:
            return 'info';
    }
}

// Helper function to format action name
function formatActionName($action) {
    $action = str_replace('_', ' ', $action);
    return ucwords($action);
}

include '../includes/footer.php';
?>