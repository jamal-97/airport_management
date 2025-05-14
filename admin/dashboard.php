<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Get dashboard statistics
// 1. Total users
$query = "SELECT COUNT(*) as total_users FROM users";
$result = $db->executeQuery($query);
$total_users = $result[0]['total_users'];

// 2. Total flights
$query = "SELECT COUNT(*) as total_flights FROM flights";
$result = $db->executeQuery($query);
$total_flights = $result[0]['total_flights'];

// 3. Total bookings
$query = "SELECT COUNT(*) as total_bookings FROM bookings";
$result = $db->executeQuery($query);
$total_bookings = $result[0]['total_bookings'];

// 4. Revenue
$query = "SELECT SUM(amount) as total_revenue FROM payments WHERE status = 'completed'";
$result = $db->executeQuery($query);
$total_revenue = ($result && isset($result[0]['total_revenue'])) ? $result[0]['total_revenue'] : 0;

// Get recent user registrations
$query = "SELECT id, first_name, last_name, email, role, created_at 
          FROM users 
          ORDER BY created_at DESC 
          LIMIT 5";
$recent_users = $db->executeQuery($query);

// Get recent bookings
$query = "SELECT b.id, b.booking_reference, u.first_name, u.last_name, f.flight_number, 
          f.departure_airport, f.arrival_airport, f.departure_time, b.status, b.created_at
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN flights f ON b.flight_id = f.id
          ORDER BY b.created_at DESC
          LIMIT 5";
$recent_bookings = $db->executeQuery($query);

// Get upcoming flights
$query = "SELECT id, flight_number, departure_airport, arrival_airport, 
          departure_time, arrival_time, status
          FROM flights
          WHERE departure_time > NOW()
          ORDER BY departure_time ASC
          LIMIT 5";
$upcoming_flights = $db->executeQuery($query);

// Include header
$page_title = "Admin Dashboard";
include '../includes/header.php';
?>

<div class="dashboard-header">
    <h1>Admin Dashboard</h1>
    <p>Welcome back, <?php echo $first_name . ' ' . $last_name; ?>!</p>
</div>

<!-- Dashboard Statistics -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-value"><?php echo number_format($total_users); ?></div>
        <div class="stat-title">Total Users</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-plane"></i>
        </div>
        <div class="stat-value"><?php echo number_format($total_flights); ?></div>
        <div class="stat-title">Total Flights</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="stat-value"><?php echo number_format($total_bookings); ?></div>
        <div class="stat-title">Total Bookings</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
        <div class="stat-title">Total Revenue</div>
    </div>
</div>

<div class="row">
    <!-- Recent User Registrations -->
    <div class="col-md-6">
        <div class="dashboard-content">
            <h2><i class="fas fa-user-plus"></i> Recent User Registrations</h2>
            
            <?php if (count($recent_users) > 0): ?>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo getRoleBadgeClass($user['role']); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right">
                    <a href="manage_users.php" class="btn btn-sm btn-primary">View All Users</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No user registrations yet.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <div class="col-md-6">
        <div class="dashboard-content">
            <h2><i class="fas fa-ticket-alt"></i> Recent Bookings</h2>
            
            <?php if (count($recent_bookings) > 0): ?>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Passenger</th>
                                <th>Flight</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['booking_reference']; ?></td>
                                    <td><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></td>
                                    <td><?php echo $booking['flight_number']; ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo getStatusBadgeClass($booking['status']); ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right">
                    <a href="view_bookings.php" class="btn btn-sm btn-primary">View All Bookings</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No bookings yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upcoming Flights -->
<div class="dashboard-content">
    <h2><i class="fas fa-plane-departure"></i> Upcoming Flights</h2>
    
    <?php if (count($upcoming_flights) > 0): ?>
        <div class="table-responsive">
            <table class="table custom-table">
                <thead>
                    <tr>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_flights as $flight): ?>
                        <tr>
                            <td><?php echo $flight['flight_number']; ?></td>
                            <td><?php echo $flight['departure_airport'] . ' to ' . $flight['arrival_airport']; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($flight['departure_time'])); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($flight['arrival_time'])); ?></td>
                            <td>
                                <span class="status-badge badge-<?php echo getStatusBadgeClass($flight['status']); ?>">
                                    <?php echo ucfirst($flight['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_flight.php?id=<?php echo $flight['id']; ?>" class="btn btn-sm btn-info" data-toggle="tooltip" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_flight.php?id=<?php echo $flight['id']; ?>" class="btn btn-sm btn-primary" data-toggle="tooltip" title="Edit Flight">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-right">
            <a href="manage_flights.php" class="btn btn-sm btn-primary">View All Flights</a>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No upcoming flights scheduled.</div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="dashboard-content">
    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
    
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <a href="add_user.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-user-plus"></i><br>
                Add New User
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="add_flight.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-plane-departure"></i><br>
                Add New Flight
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="system_logs.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-clipboard-list"></i><br>
                View System Logs
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="reports.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-chart-bar"></i><br>
                Generate Reports
            </a>
        </div>
    </div>
</div>

<?php
// Helper functions for badge classes
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

function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'confirmed':
        case 'completed':
        case 'on schedule':
            return 'success';
        case 'pending':
        case 'delayed':
            return 'warning';
        case 'cancelled':
            return 'danger';
        case 'in progress':
            return 'info';
        default:
            return 'secondary';
    }
}

include '../includes/footer.php';

?>