<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Get dashboard statistics
// 1. Total active flights
$query = "SELECT COUNT(*) as total_active_flights FROM flights 
          WHERE status IN ('scheduled', 'boarding', 'departed', 'in_air', 'landed') 
          AND departure_time > NOW() - INTERVAL 24 HOUR";
$result = $db->executeQuery($query);
$total_active_flights = $result[0]['total_active_flights'];

// 2. Today's flights
$query = "SELECT COUNT(*) as today_flights FROM flights 
          WHERE DATE(departure_time) = CURDATE()";
$result = $db->executeQuery($query);
$today_flights = $result[0]['today_flights'];

// 3. Pending baggage
$query = "SELECT COUNT(*) as pending_baggage FROM baggage 
          WHERE status IN ('checked_in', 'security_screening', 'loading')";
$result = $db->executeQuery($query);
$pending_baggage = $result[0]['pending_baggage'];

// 4. Passenger check-ins today
$query = "SELECT COUNT(*) as today_checkins FROM check_ins 
          WHERE DATE(check_in_time) = CURDATE()";
$result = $db->executeQuery($query);
$today_checkins = $result[0]['today_checkins'];

// Get upcoming departures
$query = "SELECT f.id, f.flight_number, f.departure_airport, f.arrival_airport, 
          f.departure_time, f.arrival_time, f.status, f.terminal, f.gate,
          COUNT(b.id) as booked_passengers
          FROM flights f
          LEFT JOIN bookings b ON f.id = b.flight_id AND b.status = 'confirmed'
          WHERE f.departure_time > NOW() 
          AND f.departure_time < NOW() + INTERVAL 8 HOUR
          GROUP BY f.id
          ORDER BY f.departure_time ASC
          LIMIT 5";
$upcoming_departures = $db->executeQuery($query);

// Get recent check-ins
$query = "SELECT ci.id, ci.check_in_time, ci.boarding_pass_number, 
          u.first_name, u.last_name, f.flight_number, f.departure_airport, f.arrival_airport
          FROM check_ins ci
          JOIN bookings b ON ci.booking_id = b.id
          JOIN users u ON b.user_id = u.id
          JOIN flights f ON b.flight_id = f.id
          ORDER BY ci.check_in_time DESC
          LIMIT 5";
$recent_checkins = $db->executeQuery($query);

// Get baggage status updates
$query = "SELECT bg.id, bg.tracking_number, bg.status, bg.last_updated,
          u.first_name, u.last_name, f.flight_number
          FROM baggage bg
          JOIN bookings b ON bg.booking_id = b.id
          JOIN users u ON b.user_id = u.id
          JOIN flights f ON b.flight_id = f.id
          ORDER BY bg.last_updated DESC
          LIMIT 5";
$recent_baggage = $db->executeQuery($query);

// Include header
$page_title = "Staff Dashboard";
include '../includes/header.php';
?>

<div class="dashboard-header">
    <h1>Staff Dashboard</h1>
    <p>Welcome back, <?php echo $first_name . ' ' . $last_name; ?>!</p>
</div>

<!-- Dashboard Statistics -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-plane"></i>
        </div>
        <div class="stat-value"><?php echo number_format($total_active_flights); ?></div>
        <div class="stat-title">Active Flights</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-value"><?php echo number_format($today_flights); ?></div>
        <div class="stat-title">Today's Flights</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-suitcase"></i>
        </div>
        <div class="stat-value"><?php echo number_format($pending_baggage); ?></div>
        <div class="stat-title">Pending Baggage</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-value"><?php echo number_format($today_checkins); ?></div>
        <div class="stat-title">Today's Check-ins</div>
    </div>
</div>

<div class="row">
    <!-- Upcoming Departures -->
    <div class="col-lg-6">
        <div class="dashboard-content">
            <h2><i class="fas fa-plane-departure"></i> Upcoming Departures</h2>
            
            <?php if (count($upcoming_departures) > 0): ?>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Flight</th>
                                <th>Route</th>
                                <th>Departure</th>
                                <th>Terminal/Gate</th>
                                <th>Status</th>
                                <th>Passengers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_departures as $flight): ?>
                                <tr>
                                    <td><?php echo $flight['flight_number']; ?></td>
                                    <td><?php echo $flight['departure_airport'] . ' - ' . $flight['arrival_airport']; ?></td>
                                    <td><?php echo date('H:i', strtotime($flight['departure_time'])); ?></td>
                                    <td>T<?php echo $flight['terminal']; ?> / G<?php echo $flight['gate']; ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo getStatusBadgeClass($flight['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $flight['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($flight['booked_passengers']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right">
                    <a href="flight_operations.php" class="btn btn-sm btn-primary">View All Flights</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No upcoming departures in the next 8 hours.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Check-ins -->
    <div class="col-lg-6">
        <div class="dashboard-content">
            <h2><i class="fas fa-user-check"></i> Recent Check-ins</h2>
            
            <?php if (count($recent_checkins) > 0): ?>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Passenger</th>
                                <th>Flight</th>
                                <th>Boarding Pass</th>
                                <th>Check-in Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_checkins as $checkin): ?>
                                <tr>
                                    <td><?php echo $checkin['first_name'] . ' ' . $checkin['last_name']; ?></td>
                                    <td><?php echo $checkin['flight_number']; ?> (<?php echo $checkin['departure_airport'] . '-' . $checkin['arrival_airport']; ?>)</td>
                                    <td><?php echo $checkin['boarding_pass_number']; ?></td>
                                    <td><?php echo date('M d, H:i', strtotime($checkin['check_in_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right">
                    <a href="check_in_management.php" class="btn btn-sm btn-primary">Manage Check-ins</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No recent check-ins.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Baggage Status Updates -->
<div class="dashboard-content">
    <h2><i class="fas fa-suitcase"></i> Recent Baggage Updates</h2>
    
    <?php if (count($recent_baggage) > 0): ?>
        <div class="table-responsive">
            <table class="table custom-table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Passenger</th>
                        <th>Flight</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_baggage as $baggage): ?>
                        <tr>
                            <td><?php echo $baggage['tracking_number']; ?></td>
                            <td><?php echo $baggage['first_name'] . ' ' . $baggage['last_name']; ?></td>
                            <td><?php echo $baggage['flight_number']; ?></td>
                            <td>
                                <span class="status-badge badge-<?php echo getBaggageStatusBadgeClass($baggage['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $baggage['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, H:i', strtotime($baggage['last_updated'])); ?></td>
                            <td>
                                <a href="update_baggage.php?id=<?php echo $baggage['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Update
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-right">
            <a href="baggage_management.php" class="btn btn-sm btn-primary">Manage All Baggage</a>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No recent baggage updates.
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="dashboard-content">
    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
    
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <a href="passenger_check_in.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-user-check"></i><br>
                Check-in Passenger
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="baggage_check.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-suitcase"></i><br>
                Process Baggage
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="update_flight_status.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-plane-departure"></i><br>
                Update Flight Status
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="gate_management.php" class="btn btn-lg btn-block btn-outline-primary mb-3">
                <i class="fas fa-door-open"></i><br>
                Gate Management
            </a>
        </div>
    </div>
</div>

<?php
// Helper functions for badge classes
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'scheduled':
            return 'secondary';
        case 'boarding':
        case 'landed':
            return 'info';
        case 'departed':
        case 'in_air':
            return 'primary';
        case 'arrived':
            return 'success';
        case 'delayed':
            return 'warning';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getBaggageStatusBadgeClass($status) {
    switch ($status) {
        case 'checked_in':
            return 'secondary';
        case 'security_screening':
            return 'info';
        case 'loading':
            return 'primary';
        case 'in_transit':
            return 'primary';
        case 'unloading':
            return 'info';
        case 'arrived':
        case 'delivered':
            return 'success';
        case 'delayed':
            return 'warning';
        case 'lost':
            return 'danger';
        default:
            return 'secondary';
    }
}

include '../includes/footer.php';

?>