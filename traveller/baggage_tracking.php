<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a traveller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'traveller') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Process tracking form submission
$baggage_details = null;
$tracking_history = null;
$error_message = null;

if (isset($_POST['track']) && !empty($_POST['tracking_number'])) {
    $tracking_number = trim($_POST['tracking_number']);
    
    // Check if the baggage exists and belongs to the user
    $query = "SELECT bg.id, bg.tracking_number, bg.status, bg.weight, bg.size, bg.last_updated,
             b.booking_reference, f.flight_number, f.departure_airport, f.arrival_airport,
             f.departure_time, f.arrival_time, f.status as flight_status, a.name as airline_name
             FROM baggage bg
             JOIN bookings b ON bg.booking_id = b.id
             JOIN flights f ON b.flight_id = f.id
             JOIN airlines a ON f.airline_id = a.id
             WHERE bg.tracking_number = ? AND b.user_id = ?";
    $result = $db->executeQuery($query, "si", [$tracking_number, $user_id]);
    
    if ($result && count($result) > 0) {
        $baggage_details = $result[0];
        
        // Get baggage status history
        $query = "SELECT bsl.previous_status, bsl.new_status, bsl.location, bsl.remarks, bsl.created_at
                 FROM baggage_status_logs bsl
                 WHERE bsl.baggage_id = ?
                 ORDER BY bsl.created_at ASC";
        $tracking_history = $db->executeQuery($query, "i", [$baggage_details['id']]);
    } else {
        // Try to find the baggage regardless of user (for public tracking)
        $query = "SELECT bg.id, bg.tracking_number, bg.status, bg.weight, bg.size, bg.last_updated,
                 b.booking_reference, f.flight_number, f.departure_airport, f.arrival_airport,
                 f.departure_time, f.arrival_time, f.status as flight_status, a.name as airline_name
                 FROM baggage bg
                 JOIN bookings b ON bg.booking_id = b.id
                 JOIN flights f ON b.flight_id = f.id
                 JOIN airlines a ON f.airline_id = a.id
                 WHERE bg.tracking_number = ?";
        $result = $db->executeQuery($query, "s", [$tracking_number]);
        
        if ($result && count($result) > 0) {
            $baggage_details = $result[0];
            
            // Get baggage status history
            $query = "SELECT bsl.previous_status, bsl.new_status, bsl.location, bsl.remarks, bsl.created_at
                     FROM baggage_status_logs bsl
                     WHERE bsl.baggage_id = ?
                     ORDER BY bsl.created_at ASC";
            $tracking_history = $db->executeQuery($query, "i", [$baggage_details['id']]);
        } else {
            $error_message = "No baggage found with tracking number: $tracking_number. Please check the number and try again.";
        }
    }
}

// Get user's recent baggage
$query = "SELECT bg.id, bg.tracking_number, bg.status, bg.last_updated,
         f.flight_number, f.departure_airport, f.arrival_airport, f.departure_time,
         b.booking_reference
         FROM baggage bg
         JOIN bookings b ON bg.booking_id = b.id
         JOIN flights f ON b.flight_id = f.id
         WHERE b.user_id = ?
         ORDER BY bg.last_updated DESC";
$user_baggage = $db->executeQuery($query, "i", [$user_id]);

// Include header
$page_title = "Baggage Tracking";
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Baggage Tracking</h1>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Tracking Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Track Your Baggage</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="baggage_tracking.php" class="baggage-tracking-form">
                    <div class="input-group">
                        <input type="text" name="tracking_number" id="tracking_number" class="form-control" placeholder="Enter Baggage Tracking Number" value="<?php echo isset($_POST['tracking_number']) ? htmlspecialchars($_POST['tracking_number']) : ''; ?>" required>
                        <div class="input-group-append">
                            <button type="submit" name="track" class="btn btn-primary">
                                <i class="fas fa-search"></i> Track
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($baggage_details): ?>
            <!-- Tracking Results -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tracking Results</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Tracking Number:</strong> <?php echo $baggage_details['tracking_number']; ?></p>
                            <p><strong>Flight:</strong> <?php echo $baggage_details['flight_number']; ?> (<?php echo $baggage_details['airline_name']; ?>)</p>
                            <p><strong>Route:</strong> <?php echo $baggage_details['departure_airport'] . ' → ' . $baggage_details['arrival_airport']; ?></p>
                            <p><strong>Departure:</strong> <?php echo date('M d, Y H:i', strtotime($baggage_details['departure_time'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Booking Reference:</strong> <?php echo $baggage_details['booking_reference']; ?></p>
                            <p><strong>Weight:</strong> <?php echo $baggage_details['weight']; ?> kg</p>
                            <p><strong>Size:</strong> <?php echo $baggage_details['size']; ?></p>
                            <p>
                                <strong>Status:</strong> 
                                <span class="status-badge badge-<?php echo getBaggageStatusBadgeClass($baggage_details['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $baggage_details['status'])); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Status Timeline -->
                    <h6 class="mb-3">Tracking Timeline</h6>
                    <div class="tracking-timeline">
                        <?php
                        // Create a complete timeline with all possible status steps
                        $all_statuses = [
                            'checked_in' => [
                                'title' => 'Checked In',
                                'description' => 'Baggage has been checked in at the departure airport.',
                                'icon' => 'fas fa-suitcase',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ],
                            'security_screening' => [
                                'title' => 'Security Screening',
                                'description' => 'Baggage is going through security screening.',
                                'icon' => 'fas fa-shield-alt',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ],
                            'loading' => [
                                'title' => 'Loading',
                                'description' => 'Baggage is being loaded onto the aircraft.',
                                'icon' => 'fas fa-dolly',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ],
                            'in_transit' => [
                                'title' => 'In Transit',
                                'description' => 'Baggage is in transit on the aircraft.',
                                'icon' => 'fas fa-plane',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ],
                            'unloading' => [
                                'title' => 'Unloading',
                                'description' => 'Baggage is being unloaded from the aircraft.',
                                'icon' => 'fas fa-dolly-flatbed',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ],
                            'arrived' => [
                                'title' => 'Arrived',
                                'description' => 'Baggage has arrived at the destination airport.',
                                'icon' => 'fas fa-map-marker-alt',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ],
                            'delivered' => [
                                'title' => 'Delivered',
                                'description' => 'Baggage has been delivered to the passenger.',
                                'icon' => 'fas fa-check-circle',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ]
                        ];
                        
                        // Special statuses
                        $special_statuses = [
                            'delayed' => [
                                'title' => 'Delayed',
                                'description' => 'Baggage has been delayed.',
                                'icon' => 'fas fa-clock',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ],
                            'lost' => [
                                'title' => 'Lost',
                                'description' => 'Baggage has been reported as lost.',
                                'icon' => 'fas fa-question-circle',
                                'completed' => false,
                                'active' => false,
                                'timestamp' => null,
                                'location' => null,
                                'remarks' => null
                            ]
                        ];
                        
                        // Fill in timeline from tracking history
                        if ($tracking_history && count($tracking_history) > 0) {
                            foreach ($tracking_history as $log) {
                                $status = $log['new_status'];
                                
                                if (isset($all_statuses[$status])) {
                                    $all_statuses[$status]['timestamp'] = $log['created_at'];
                                    $all_statuses[$status]['location'] = $log['location'];
                                    $all_statuses[$status]['remarks'] = $log['remarks'];
                                    $all_statuses[$status]['completed'] = true;
                                } elseif (isset($special_statuses[$status])) {
                                    $special_statuses[$status]['timestamp'] = $log['created_at'];
                                    $special_statuses[$status]['location'] = $log['location'];
                                    $special_statuses[$status]['remarks'] = $log['remarks'];
                                    $special_statuses[$status]['completed'] = true;
                                }
                            }
                        }
                        
                        // Mark the current status as active
                        $current_status = $baggage_details['status'];
                        if (isset($all_statuses[$current_status])) {
                            $all_statuses[$current_status]['active'] = true;
                        } elseif (isset($special_statuses[$current_status])) {
                            $special_statuses[$current_status]['active'] = true;
                        }
                        
                        // Render the timeline
                        foreach ($all_statuses as $status_key => $status) {
                            $step_class = '';
                            if ($status['completed']) {
                                $step_class = 'completed';
                            } elseif ($status['active']) {
                                $step_class = 'active';
                            }
                            
                            echo '<div class="tracking-step ' . $step_class . '">';
                            echo '<div class="tracking-step-content">';
                            echo '<div class="tracking-step-title"><i class="' . $status['icon'] . '"></i> ' . $status['title'] . '</div>';
                            echo '<div class="tracking-step-info">';
                            echo '<p>' . $status['description'] . '</p>';
                            
                            if ($status['timestamp']) {
                                echo '<p><i class="far fa-clock"></i> ' . date('M d, Y H:i', strtotime($status['timestamp'])) . '</p>';
                            }
                            
                            if ($status['location']) {
                                echo '<p><i class="fas fa-map-marker-alt"></i> ' . $status['location'] . '</p>';
                            }
                            
                            if ($status['remarks']) {
                                echo '<p><i class="fas fa-info-circle"></i> ' . $status['remarks'] . '</p>';
                            }
                            
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        // Add special statuses if active
                        foreach ($special_statuses as $status_key => $status) {
                            if ($status['completed'] || $status['active']) {
                                $step_class = $status['active'] ? 'active' : 'completed';
                                
                                echo '<div class="tracking-step ' . $step_class . '">';
                                echo '<div class="tracking-step-content">';
                                echo '<div class="tracking-step-title"><i class="' . $status['icon'] . '"></i> ' . $status['title'] . '</div>';
                                echo '<div class="tracking-step-info">';
                                echo '<p>' . $status['description'] . '</p>';
                                
                                if ($status['timestamp']) {
                                    echo '<p><i class="far fa-clock"></i> ' . date('M d, Y H:i', strtotime($status['timestamp'])) . '</p>';
                                }
                                
                                if ($status['location']) {
                                    echo '<p><i class="fas fa-map-marker-alt"></i> ' . $status['location'] . '</p>';
                                }
                                
                                if ($status['remarks']) {
                                    echo '<p><i class="fas fa-info-circle"></i> ' . $status['remarks'] . '</p>';
                                }
                                
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Your Baggage -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Your Recent Baggage</h5>
            </div>
            <div class="card-body">
                <?php if (count($user_baggage) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($user_baggage as $baggage): ?>
                            <a href="#" class="list-group-item list-group-item-action" onclick="document.getElementById('tracking_number').value='<?php echo $baggage['tracking_number']; ?>'; document.querySelector('.baggage-tracking-form').submit(); return false;">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo $baggage['tracking_number']; ?></h6>
                                    <span class="status-badge badge-<?php echo getBaggageStatusBadgeClass($baggage['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $baggage['status'])); ?>
                                    </span>
                                </div>
                                <p class="mb-1">
                                    Flight: <?php echo $baggage['flight_number']; ?><br>
                                    <?php echo $baggage['departure_airport'] . ' → ' . $baggage['arrival_airport']; ?>
                                </p>
                                <small class="text-muted">
                                    Ref: <?php echo $baggage['booking_reference']; ?> | 
                                    Last Updated: <?php echo date('M d, H:i', strtotime($baggage['last_updated'])); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You don't have any baggage records yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Baggage Tracking Tips -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Baggage Tracking Tips</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-tag text-primary"></i> Ensure your baggage tag is securely attached.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-id-card text-primary"></i> Attach personal identification inside and outside your baggage.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-camera text-primary"></i> Take a photo of your baggage before check-in.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-suitcase text-primary"></i> Use distinctive baggage with unique colors or markings.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-clock text-primary"></i> Arrive early for check-in to avoid last-minute processing.
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Need Help?</h5>
            </div>
            <div class="card-body">
                <p>If you're having trouble locating your baggage, please contact our Baggage Services:</p>
                <ul class="list-unstyled">
                    <li><i class="fas fa-phone"></i> Baggage Hotline: <strong>+1 800 555 1234</strong></li>
                    <li><i class="fas fa-envelope"></i> Email: <strong>baggage@airport-system.com</strong></li>
                    <li><i class="fas fa-map-marker-alt"></i> Baggage Service Center: <strong>Terminal 2, Ground Floor</strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function for baggage status badge class
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