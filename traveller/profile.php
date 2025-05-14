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

// Get user details
$query = "SELECT * FROM users WHERE id = ?";
$result = $db->executeQuery($query, "i", [$user_id]);

if (!$result || count($result) == 0) {
    header("Location: dashboard.php");
    exit();
}

$user = $result[0];

// Get user's travel statistics
$query = "SELECT 
            COUNT(DISTINCT b.id) as total_bookings,
            COUNT(DISTINCT CASE WHEN f.departure_time < NOW() AND b.status != 'cancelled' THEN b.id END) as completed_flights,
            COUNT(DISTINCT CASE WHEN f.departure_time > NOW() AND b.status != 'cancelled' THEN b.id END) as upcoming_flights,
            COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.id END) as cancelled_flights,
            SUM(b.price) as total_spent,
            COUNT(DISTINCT f.departure_airport) as departure_airports_count,
            COUNT(DISTINCT f.arrival_airport) as arrival_airports_count
          FROM bookings b
          JOIN flights f ON b.flight_id = f.id
          WHERE b.user_id = ?";
$stats_result = $db->executeQuery($query, "i", [$user_id]);
$stats = $stats_result[0];

// Get user's most recent booking
$query = "SELECT b.id, b.booking_reference, b.status, f.flight_number, f.departure_airport, f.arrival_airport, f.departure_time
          FROM bookings b
          JOIN flights f ON b.flight_id = f.id
          WHERE b.user_id = ?
          ORDER BY b.created_at DESC
          LIMIT 1";
$recent_booking_result = $db->executeQuery($query, "i", [$user_id]);
$recent_booking = isset($recent_booking_result[0]) ? $recent_booking_result[0] : null;

// Get user's most visited destinations
$query = "SELECT f.arrival_airport, COUNT(*) as visit_count
          FROM bookings b
          JOIN flights f ON b.flight_id = f.id
          WHERE b.user_id = ? AND b.status != 'cancelled' AND f.departure_time < NOW()
          GROUP BY f.arrival_airport
          ORDER BY visit_count DESC
          LIMIT 5";
$destinations_result = $db->executeQuery($query, "i", [$user_id]);

// Process profile update if submitted
$update_success = false;
$update_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        // First, get the actual columns from the users table
        $columns_query = "SHOW COLUMNS FROM users";
        $columns_result = $db->executeQuery($columns_query);
        
        if (!$columns_result) {
            throw new Exception("Unable to get database structure.");
        }
        
        // Create an array of existing column names
        $existing_columns = [];
        foreach ($columns_result as $column) {
            $existing_columns[] = $column['Field'];
        }
        
        // Check if email column exists (minimum required)
        if (!in_array('email', $existing_columns)) {
            throw new Exception("Email column not found in database.");
        }
        
        // Get email from the form
        $email = trim($_POST['email']);
        
        // Basic validation for email
        if (empty($email)) {
            throw new Exception("Email is required.");
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        // Build dynamic update query based on existing columns
        $update_fields = [];
        $update_values = [];
        $update_types = "";
        
        // Always include email
        $update_fields[] = "email = ?";
        $update_values[] = $email;
        $update_types .= "s";
        
        // Optional fields - only include if they exist in the database
        $optional_fields = [
            'phone' => 's',
            'address' => 's',
            'city' => 's',
            'country' => 's',
            'passport_number' => 's'
        ];
        
        foreach ($optional_fields as $field => $type) {
            if (in_array($field, $existing_columns) && isset($_POST[$field])) {
                $update_fields[] = "$field = ?";
                $update_values[] = trim($_POST[$field]);
                $update_types .= $type;
            }
        }
        
        // Add updated_at if it exists
        if (in_array('updated_at', $existing_columns)) {
            $update_fields[] = "updated_at = NOW()";
        }
        
        // Add user_id to values array
        $update_values[] = $user_id;
        $update_types .= "i";
        
        // Construct the final query
        $update_query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
        
        // Execute the update
        $update_result = $db->executeQuery($update_query, $update_types, $update_values);
        
        if (!$update_result) {
            throw new Exception("Database update failed.");
        }
        
        // Update was successful
        $update_success = true;
        
        // Update session email if it changed
        if ($_SESSION['email'] !== $email) {
            $_SESSION['email'] = $email;
        }
        
        // Refresh user data
        $query = "SELECT * FROM users WHERE id = ?";
        $result = $db->executeQuery($query, "i", [$user_id]);
        $user = $result[0];
        
        // Redirect to avoid form resubmission
        header("Location: profile.php?success=profile_updated");
        exit();
        
    } catch (Exception $e) {
        $update_error = "Failed to update profile: " . $e->getMessage();
    }
}

// Include header
$page_title = "My Profile";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="mb-0">My Profile</h3>
                </div>
                <div class="card-body">
                    <?php if ($update_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Your profile has been updated successfully.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($update_error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $update_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="profile.php">
                        <!-- Always show first and last name fields (disabled) -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" class="form-control" value="<?php echo $user['first_name']; ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" class="form-control" value="<?php echo $user['last_name']; ?>" disabled>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Email field (always required) -->
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                        </div>
                        
                        <!-- Only show other fields if they exist in the database -->
                        <?php if (isset($user['phone'])): ?>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $user['phone']; ?>">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($user['address'])): ?>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" class="form-control" value="<?php echo $user['address']; ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php if (isset($user['city'])): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="city">City</label>
                                        <input type="text" id="city" name="city" class="form-control" value="<?php echo $user['city']; ?>">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($user['country'])): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="country">Country</label>
                                        <input type="text" id="country" name="country" class="form-control" value="<?php echo $user['country']; ?>">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($user['passport_number'])): ?>
                            <div class="form-group">
                                <label for="passport_number">Passport Number</label>
                                <input type="text" id="passport_number" name="passport_number" class="form-control" value="<?php echo $user['passport_number']; ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-right">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="mb-0">Password Management</h3>
                </div>
                <div class="card-body">
                    <p>You can update your password to keep your account secure.</p>
                    <a href="change_password.php" class="btn btn-outline-primary">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Travel Statistics -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="mb-0">Travel Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="stat-item mb-3">
                        <div class="stat-label">Total Bookings</div>
                        <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                    </div>
                    
                    <div class="stat-item mb-3">
                        <div class="stat-label">Completed Flights</div>
                        <div class="stat-value"><?php echo $stats['completed_flights']; ?></div>
                    </div>
                    
                    <div class="stat-item mb-3">
                        <div class="stat-label">Upcoming Flights</div>
                        <div class="stat-value"><?php echo $stats['upcoming_flights']; ?></div>
                    </div>
                    
                    <div class="stat-item mb-3">
                        <div class="stat-label">Cancelled Flights</div>
                        <div class="stat-value"><?php echo $stats['cancelled_flights']; ?></div>
                    </div>
                    
                    <div class="stat-item mb-3">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-value">$<?php echo number_format($stats['total_spent'], 2); ?></div>
                    </div>
                    
                    <div class="stat-item mb-3">
                        <div class="stat-label">Airports Visited</div>
                        <div class="stat-value"><?php echo $stats['arrival_airports_count']; ?></div>
                    </div>
                    
                    <?php if ($recent_booking): ?>
                        <hr>
                        <h5>Most Recent Booking</h5>
                        <p>
                            <strong>Reference:</strong> <?php echo $recent_booking['booking_reference']; ?><br>
                            <strong>Flight:</strong> <?php echo $recent_booking['flight_number']; ?><br>
                            <strong>Route:</strong> <?php echo $recent_booking['departure_airport'] . ' → ' . $recent_booking['arrival_airport']; ?><br>
                            <strong>Departure:</strong> <?php echo date('M d, Y', strtotime($recent_booking['departure_time'])); ?><br>
                            <strong>Status:</strong> 
                            <span class="badge badge-<?php echo getStatusBadgeClass($recent_booking['status']); ?>">
                                <?php echo ucfirst($recent_booking['status']); ?>
                            </span>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (count($destinations_result) > 0): ?>
                        <hr>
                        <h5>Top Destinations</h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($destinations_result as $destination): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo $destination['arrival_airport']; ?> (<?php echo getAirportName($destination['arrival_airport']); ?>)
                                    <span class="badge badge-primary badge-pill"><?php echo $destination['visit_count']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="mb-0">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <a href="flight_info.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-plane"></i><br>
                                My Flights
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="purchase_tickets.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-ticket-alt"></i><br>
                                Book Flight
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="baggage_tracking.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-suitcase"></i><br>
                                Track Baggage
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="dashboard.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-tachometer-alt"></i><br>
                                Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-label {
    font-weight: 500;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}
</style>

<?php
// Helper function
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'scheduled':
        case 'confirmed':
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

include '../includes/footer.php';
?>