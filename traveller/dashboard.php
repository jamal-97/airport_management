<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a traveller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'traveller') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Get user's upcoming flights - UPDATED QUERY to filter out cancelled bookings
$query = "SELECT b.id as booking_id, b.booking_reference, b.status as booking_status, b.created_at as booking_date,
         f.id as flight_id, f.flight_number, f.airline_id, f.departure_airport, f.arrival_airport, 
         f.departure_time, f.arrival_time, f.status as flight_status, f.terminal, f.gate,
         a.name as airline_name, ci.boarding_pass_number
         FROM bookings b
         JOIN flights f ON b.flight_id = f.id
         JOIN airlines a ON f.airline_id = a.id
         LEFT JOIN check_ins ci ON b.id = ci.booking_id
         WHERE b.user_id = ? AND f.departure_time > NOW() AND b.status != 'cancelled' AND f.status != 'cancelled'
         ORDER BY f.departure_time ASC
         LIMIT 3";
$upcoming_flights = $db->executeQuery($query, "i", [$user_id]);

// Get user's recent flights
$query = "SELECT b.id as booking_id, b.booking_reference, b.status as booking_status,
         f.id as flight_id, f.flight_number, f.airline_id, f.departure_airport, f.arrival_airport, 
         f.departure_time, f.arrival_time, f.status as flight_status,
         a.name as airline_name
         FROM bookings b
         JOIN flights f ON b.flight_id = f.id
         JOIN airlines a ON f.airline_id = a.id
         WHERE b.user_id = ? AND f.departure_time < NOW() AND b.status != 'cancelled'
         ORDER BY f.departure_time DESC
         LIMIT 3";
$recent_flights = $db->executeQuery($query, "i", [$user_id]);

// Get user's baggage status
$query = "SELECT bg.id, bg.tracking_number, bg.status, bg.last_updated,
         f.flight_number, f.departure_airport, f.arrival_airport, f.departure_time
         FROM baggage bg
         JOIN bookings b ON bg.booking_id = b.id
         JOIN flights f ON b.flight_id = f.id
         WHERE b.user_id = ? AND f.departure_time > NOW() - INTERVAL 7 DAY AND b.status != 'cancelled'
         ORDER BY bg.last_updated DESC";
$baggage = $db->executeQuery($query, "i", [$user_id]);

// Get flight deals
$query = "SELECT f.id, f.flight_number, f.departure_airport, f.arrival_airport, 
         f.departure_time, f.arrival_time, p.amount as price,
         a.name as airline_name
         FROM flights f
         JOIN prices p ON f.id = p.flight_id
         JOIN airlines a ON f.airline_id = a.id
         WHERE f.departure_time > NOW() 
         AND f.status = 'scheduled'
         AND p.is_promotion = 1
         ORDER BY RAND()
         LIMIT 3";
$flight_deals = $db->executeQuery($query);

// Include header
$page_title = "Traveller Dashboard";
include '../includes/header.php';
?>

<div class="dashboard-header">
    <h1>Welcome to Your Dashboard</h1>
    <p>Hello, <?php echo $first_name . ' ' . $last_name; ?>! Manage Your flights and travel with ease.</p>
</div>

<!-- Upcoming Flights -->
<div class="dashboard-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-plane-departure"></i> My Upcoming Flights</h2>
        <a href="flight_info.php" class="btn btn-outline-primary btn-sm">View All Flights</a>
    </div>
    
    <?php if (count($upcoming_flights) > 0): ?>
        <div class="flight-info-container">
            <?php foreach ($upcoming_flights as $flight): ?>
                <?php
                // Calculate time remaining until flight
                $departure_time = strtotime($flight['departure_time']);
                $current_time = time();
                $time_remaining = $departure_time - $current_time;
                $days_remaining = floor($time_remaining / (60 * 60 * 24));
                $hours_remaining = floor(($time_remaining % (60 * 60 * 24)) / (60 * 60));
                
                // Determine if check-in is available (typically 24-48 hours before flight)
                $check_in_available = ($time_remaining <= 48 * 60 * 60 && $time_remaining > 0);
                ?>
                <div class="flight-card">
                    <div class="flight-card-header">
                        <div>
                            <h4><?php echo $flight['airline_name']; ?></h4>
                            <span><?php echo $flight['flight_number']; ?></span>
                        </div>
                        <div>
                            <span class="status-badge badge-<?php echo getStatusBadgeClass($flight['flight_status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $flight['flight_status'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flight-card-body">
                        <div class="flight-route">
                            <div class="flight-city">
                                <div class="flight-city-code"><?php echo $flight['departure_airport']; ?></div>
                                <div class="flight-city-name"><?php echo getAirportName($flight['departure_airport']); ?></div>
                            </div>
                            <div class="flight-route-line">
                                <i class="fas fa-plane"></i>
                            </div>
                            <div class="flight-city">
                                <div class="flight-city-code"><?php echo $flight['arrival_airport']; ?></div>
                                <div class="flight-city-name"><?php echo getAirportName($flight['arrival_airport']); ?></div>
                            </div>
                        </div>
                        <div class="flight-time-info">
                            <div class="flight-time">
                                <div class="flight-time-value"><?php echo date('H:i', strtotime($flight['departure_time'])); ?></div>
                                <div class="flight-time-label"><?php echo date('M d, Y', strtotime($flight['departure_time'])); ?></div>
                            </div>
                            <div class="flight-duration">
                                <div class="flight-duration-value"><?php echo calculateFlightDuration($flight['departure_time'], $flight['arrival_time']); ?></div>
                                <div class="flight-duration-label">Duration</div>
                            </div>
                            <div class="flight-time">
                                <div class="flight-time-value"><?php echo date('H:i', strtotime($flight['arrival_time'])); ?></div>
                                <div class="flight-time-label"><?php echo date('M d, Y', strtotime($flight['arrival_time'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($flight['terminal'] && $flight['gate']): ?>
                            <div class="flight-details">
                                <div><strong>Terminal:</strong> <?php echo $flight['terminal']; ?></div>
                                <div><strong>Gate:</strong> <?php echo $flight['gate']; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flight-countdown mt-3">
                            <div class="text-center">
                                <?php if ($days_remaining > 0): ?>
                                    <div class="countdown-value"><?php echo $days_remaining; ?> days, <?php echo $hours_remaining; ?> hours</div>
                                <?php else: ?>
                                    <div class="countdown-value"><?php echo $hours_remaining; ?> hours</div>
                                <?php endif; ?>
                                <div class="countdown-label">until departure</div>
                            </div>
                        </div>
                    </div>
                    <div class="flight-card-footer">
                        <div>
                            <strong>Booking Ref:</strong> <?php echo $flight['booking_reference']; ?>
                            <?php if (!empty($flight['boarding_pass_number'])): ?>
                                <br><strong>Boarding Pass:</strong> <?php echo $flight['boarding_pass_number']; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if (!empty($flight['boarding_pass_number'])): ?>
                                <a href="view_boarding_pass.php?booking_id=<?php echo $flight['booking_id']; ?>" class="btn btn-success">
                                    <i class="fas fa-ticket-alt"></i> Boarding Pass
                                </a>
                            <?php elseif ($check_in_available): ?>
                                <a href="check_in.php?booking_id=<?php echo $flight['booking_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-check-square"></i> Check-in
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-clock"></i> Check-in Soon
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> You don't have any upcoming flights. <a href="purchase_tickets.php">Book a flight now!</a>
        </div>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Baggage Tracking -->
    <div class="col-lg-6">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-suitcase"></i> My Baggage</h2>
                <a href="baggage_tracking.php" class="btn btn-outline-primary btn-sm">Track Baggage</a>
            </div>
            
            <?php if (count($baggage) > 0): ?>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Flight</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baggage as $bag): ?>
                                <tr>
                                    <td><?php echo $bag['tracking_number']; ?></td>
                                    <td>
                                        <?php echo $bag['flight_number']; ?><br>
                                        <small><?php echo $bag['departure_airport'] . ' → ' . $bag['arrival_airport']; ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?php echo getBaggageStatusBadgeClass($bag['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $bag['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, H:i', strtotime($bag['last_updated'])); ?></td>
                                    <td>
                                        <a href="baggage_details.php?id=<?php echo $bag['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-search"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No baggage information available.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Flights -->
    <div class="col-lg-6">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-history"></i> Recent Flights</h2>
                <a href="flight_history.php" class="btn btn-outline-primary btn-sm">View History</a>
            </div>
            
            <?php if (count($recent_flights) > 0): ?>
                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Flight</th>
                                <th>Route</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_flights as $flight): ?>
                                <tr>
                                    <td>
                                        <?php echo $flight['flight_number']; ?><br>
                                        <small><?php echo $flight['airline_name']; ?></small>
                                    </td>
                                    <td><?php echo $flight['departure_airport'] . ' → ' . $flight['arrival_airport']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($flight['departure_time'])); ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo getStatusBadgeClass($flight['flight_status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $flight['flight_status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No flight history available.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rest of dashboard.php code (flight deals, quick actions, etc.) -->

<?php
// Helper functions
function calculateFlightDuration($departure, $arrival) {
    $dep = new DateTime($departure);
    $arr = new DateTime($arrival);
    $interval = $dep->diff($arr);
    
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    
    return $hours . 'h ' . $minutes . 'm';
}

function getAirportName($code) {
    // This would typically come from a database lookup
    // For simplicity, we'll use a static array
    $airports = [
        'JFK' => 'New York',
        'LAX' => 'Los Angeles',
        'ORD' => 'Chicago',
        'LHR' => 'London',
        'CDG' => 'Paris',
        'DXB' => 'Dubai',
        'HKG' => 'Hong Kong',
        'SYD' => 'Sydney',
        'SIN' => 'Singapore',
        'DEL' => 'New Delhi',
        'DAC' => 'Dhaka'
    ];
    
    return isset($airports[$code]) ? $airports[$code] : $code;
}

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