<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Handle flight status update
if (isset($_POST['update_status']) && isset($_POST['flight_id']) && isset($_POST['new_status'])) {
    $flight_id = $_POST['flight_id'];
    $new_status = $_POST['new_status'];
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate status
    $valid_statuses = ['scheduled', 'boarding', 'departed', 'in_air', 'landed', 'arrived', 'delayed', 'cancelled'];
    
    if (!in_array($new_status, $valid_statuses)) {
        $error = "Invalid status.";
    } else {
        // Check if flight exists
        $query = "SELECT id, flight_number FROM flights WHERE id = ?";
        $result = $db->executeQuery($query, "i", [$flight_id]);
        
        if ($result && count($result) > 0) {
            $flight_number = $result[0]['flight_number'];
            
            // Update status
            $query = "UPDATE flights SET status = ?, updated_at = NOW() WHERE id = ?";
            $result = $db->executeQuery($query, "si", [$new_status, $flight_id]);
            
            if ($result) {
                // Log the status change
                $staff_id = $_SESSION['user_id'];
                $query = "INSERT INTO flight_status_logs (flight_id, staff_id, previous_status, new_status, remarks, created_at) 
                         VALUES (?, ?, (SELECT status FROM flights WHERE id = ?), ?, ?, NOW())";
                $db->executeQuery($query, "iisss", [$flight_id, $staff_id, $flight_id, $new_status, $remarks]);
                
                // If delayed or cancelled, add delay information
                if ($new_status == 'delayed' || $new_status == 'cancelled') {
                    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
                    $estimated_time = isset($_POST['estimated_time']) ? trim($_POST['estimated_time']) : null;
                    
                    $query = "INSERT INTO flight_delays (flight_id, status, reason, estimated_departure_time, created_at) 
                             VALUES (?, ?, ?, ?, NOW())";
                    $db->executeQuery($query, "isss", [$flight_id, $new_status, $reason, $estimated_time]);
                }
                
                $success = "Flight status has been updated successfully.";
            } else {
                $error = "Failed to update flight status. Please try again.";
            }
        } else {
            $error = "Flight not found.";
        }
    }
}

// Handle gate change
if (isset($_POST['update_gate']) && isset($_POST['flight_id']) && isset($_POST['new_gate']) && isset($_POST['new_terminal'])) {
    $flight_id = $_POST['flight_id'];
    $new_gate = $_POST['new_gate'];
    $new_terminal = $_POST['new_terminal'];
    $remarks = isset($_POST['gate_remarks']) ? trim($_POST['gate_remarks']) : '';
    
    // Check if flight exists
    $query = "SELECT id, flight_number, gate, terminal FROM flights WHERE id = ?";
    $result = $db->executeQuery($query, "i", [$flight_id]);
    
    if ($result && count($result) > 0) {
        $flight_number = $result[0]['flight_number'];
        $old_gate = $result[0]['gate'];
        $old_terminal = $result[0]['terminal'];
        
        // Update gate and terminal
        $query = "UPDATE flights SET gate = ?, terminal = ?, updated_at = NOW() WHERE id = ?";
        $result = $db->executeQuery($query, "ssi", [$new_gate, $new_terminal, $flight_id]);
        
        if ($result) {
            // Log the gate change
            $staff_id = $_SESSION['user_id'];
            $query = "INSERT INTO gate_change_logs (flight_id, staff_id, previous_gate, new_gate, previous_terminal, new_terminal, remarks, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $db->executeQuery($query, "iisssss", [$flight_id, $staff_id, $old_gate, $new_gate, $old_terminal, $new_terminal, $remarks]);
            
            $success = "Flight gate and terminal have been updated successfully.";
        } else {
            $error = "Failed to update gate and terminal. Please try again.";
        }
    } else {
        $error = "Flight not found.";
    }
}

// Filter parameters
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : 'all';

// Build the query for flights
$query = "SELECT f.id, f.flight_number, f.airline_id, f.departure_airport, f.arrival_airport, 
          f.departure_time, f.arrival_time, f.status, f.terminal, f.gate, 
          f.created_at, f.updated_at, a.name as airline_name, 
          ac.name as aircraft_name, ac.capacity,
          COUNT(b.id) as booked_seats
          FROM flights f
          LEFT JOIN airlines a ON f.airline_id = a.id
          LEFT JOIN aircraft ac ON f.aircraft_id = ac.id
          LEFT JOIN bookings b ON f.id = b.flight_id AND b.status IN ('confirmed', 'checked_in')
          WHERE 1=1";

// Add filters
$query_params = [];
$param_types = "";

// Date filter
if (!empty($filter_date)) {
    if ($filter_type == 'departures') {
        $query .= " AND DATE(f.departure_time) = ?";
    } elseif ($filter_type == 'arrivals') {
        $query .= " AND DATE(f.arrival_time) = ?";
    } else {
        $query .= " AND (DATE(f.departure_time) = ? OR DATE(f.arrival_time) = ?)";
        $query_params[] = $filter_date;
        $param_types .= "s";
    }
    $query_params[] = $filter_date;
    $param_types .= "s";
}

// Status filter
if (!empty($filter_status)) {
    $query .= " AND f.status = ?";
    $query_params[] = $filter_status;
    $param_types .= "s";
}

// Group by and order
$query .= " GROUP BY f.id";
if ($filter_type == 'departures') {
    $query .= " ORDER BY f.departure_time ASC";
} elseif ($filter_type == 'arrivals') {
    $query .= " ORDER BY f.arrival_time ASC";
} else {
    $query .= " ORDER BY CASE WHEN f.departure_time > NOW() THEN f.departure_time ELSE f.arrival_time END ASC";
}

// Execute query
$flights = $db->executeQuery($query, empty($param_types) ? null : $param_types, $query_params);

// Include header
$page_title = "Flight Operations";
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Flight Operations</h1>
    <div>
        <a href="flight_search.php" class="btn btn-outline-primary mr-2">
            <i class="fas fa-search"></i> Advanced Search
        </a>
        <a href="update_flight_status.php" class="btn btn-primary">
            <i class="fas fa-plane"></i> Update Flight Status
        </a>
    </div>
</div>

<!-- Filter Options -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="flight_operations.php" class="row">
            <div class="col-md-3 mb-3">
                <label for="date">Date</label>
                <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="type">Flight Type</label>
                <select name="type" id="type" class="form-control">
                    <option value="all" <?php echo ($filter_type == 'all') ? 'selected' : ''; ?>>All Flights</option>
                    <option value="departures" <?php echo ($filter_type == 'departures') ? 'selected' : ''; ?>>Departures</option>
                    <option value="arrivals" <?php echo ($filter_type == 'arrivals') ? 'selected' : ''; ?>>Arrivals</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="scheduled" <?php echo ($filter_status == 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="boarding" <?php echo ($filter_status == 'boarding') ? 'selected' : ''; ?>>Boarding</option>
                    <option value="departed" <?php echo ($filter_status == 'departed') ? 'selected' : ''; ?>>Departed</option>
                    <option value="in_air" <?php echo ($filter_status == 'in_air') ? 'selected' : ''; ?>>In Air</option>
                    <option value="landed" <?php echo ($filter_status == 'landed') ? 'selected' : ''; ?>>Landed</option>
                    <option value="arrived" <?php echo ($filter_status == 'arrived') ? 'selected' : ''; ?>>Arrived</option>
                    <option value="delayed" <?php echo ($filter_status == 'delayed') ? 'selected' : ''; ?>>Delayed</option>
                    <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Apply Filters
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

<!-- Flights Table -->
<div class="card">
    <div class="card-header">
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-0">
                    <?php 
                    if ($filter_type == 'departures') {
                        echo 'Departures';
                    } elseif ($filter_type == 'arrivals') {
                        echo 'Arrivals';
                    } else {
                        echo 'All Flights';
                    }
                    ?>
                    <?php if (!empty($filter_date)): ?>
                        for <?php echo date('F j, Y', strtotime($filter_date)); ?>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="col-md-6 text-right">
                <span class="badge badge-primary"><?php echo count($flights); ?> Flights</span>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($flights) > 0): ?>
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Flight</th>
                            <th>Route</th>
                            <th>Scheduled Time</th>
                            <th>Terminal/Gate</th>
                            <th>Status</th>
                            <th>Capacity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flights as $flight): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $flight['flight_number']; ?></strong><br>
                                    <small><?php echo $flight['airline_name']; ?></small>
                                </td>
                                <td>
                                    <?php if ($filter_type == 'arrivals'): ?>
                                        <span class="badge badge-light"><?php echo $flight['departure_airport']; ?></span>
                                        <i class="fas fa-arrow-right mx-1"></i>
                                        <span class="badge badge-primary"><?php echo $flight['arrival_airport']; ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-primary"><?php echo $flight['departure_airport']; ?></span>
                                        <i class="fas fa-arrow-right mx-1"></i>
                                        <span class="badge badge-light"><?php echo $flight['arrival_airport']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($filter_type == 'arrivals'): ?>
                                        <div>
                                            <i class="fas fa-plane-arrival"></i> 
                                            <?php echo date('H:i', strtotime($flight['arrival_time'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <i class="fas fa-plane-departure"></i> 
                                            <?php echo date('H:i', strtotime($flight['departure_time'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>T<?php echo $flight['terminal']; ?> / G<?php echo $flight['gate']; ?></td>
                                <td>
                                    <span class="status-badge badge-<?php echo getStatusBadgeClass($flight['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $flight['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $flight['booked_seats']; ?> / <?php echo $flight['capacity']; ?>
                                    <div class="progress mt-1" style="height: 5px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo ($flight['capacity'] > 0) ? ($flight['booked_seats'] / $flight['capacity'] * 100) : 0; ?>%" aria-valuenow="<?php echo $flight['booked_seats']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $flight['capacity']; ?>"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="actionDropdown<?php echo $flight['id']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Actions
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionDropdown<?php echo $flight['id']; ?>">
                                            <a class="dropdown-item" href="view_flight_details.php?id=<?php echo $flight['id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <a class="dropdown-item" href="view_passengers.php?flight_id=<?php echo $flight['id']; ?>">
                                                <i class="fas fa-users"></i> View Passengers
                                            </a>
                                            <a class="dropdown-item" href="view_flight_baggage.php?flight_id=<?php echo $flight['id']; ?>">
                                                <i class="fas fa-suitcase"></i> View Baggage
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#updateStatusModal" data-flight-id="<?php echo $flight['id']; ?>" data-flight-number="<?php echo $flight['flight_number']; ?>" data-current-status="<?php echo $flight['status']; ?>">
                                                <i class="fas fa-plane"></i> Update Status
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#changeGateModal" data-flight-id="<?php echo $flight['id']; ?>" data-flight-number="<?php echo $flight['flight_number']; ?>" data-current-gate="<?php echo $flight['gate']; ?>" data-current-terminal="<?php echo $flight['terminal']; ?>">
                                                <i class="fas fa-door-open"></i> Change Gate
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No flights found matching your criteria.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="flight_operations.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Update Flight Status</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="flight_id" id="status_flight_id">
                    
                    <div class="form-group">
                        <label>Flight Number:</label>
                        <div id="status_flight_number" class="font-weight-bold"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Status:</label>
                        <div id="current_status" class="font-weight-bold"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_status">New Status:</label>
                        <select name="new_status" id="new_status" class="form-control" required>
                            <option value="">Select Status</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="boarding">Boarding</option>
                            <option value="departed">Departed</option>
                            <option value="in_air">In Air</option>
                            <option value="landed">Landed</option>
                            <option value="arrived">Arrived</option>
                            <option value="delayed">Delayed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <!-- Conditional fields for delayed/cancelled status -->
                    <div id="delay_fields" style="display: none;">
                        <div class="form-group">
                            <label for="reason">Reason:</label>
                            <select name="reason" id="reason" class="form-control">
                                <option value="">Select Reason</option>
                                <option value="weather">Weather Conditions</option>
                                <option value="technical">Technical Issues</option>
                                <option value="air_traffic">Air Traffic Control</option>
                                <option value="crew">Crew Issues</option>
                                <option value="security">Security Concerns</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="estimated_time">Estimated New Departure Time:</label>
                            <input type="datetime-local" name="estimated_time" id="estimated_time" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="remarks">Remarks:</label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Gate Modal -->
<div class="modal fade" id="changeGateModal" tabindex="-1" role="dialog" aria-labelledby="changeGateModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="flight_operations.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeGateModalLabel">Change Gate Assignment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="flight_id" id="gate_flight_id">
                    
                    <div class="form-group">
                        <label>Flight Number:</label>
                        <div id="gate_flight_number" class="font-weight-bold"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Terminal/Gate:</label>
                        <div id="current_gate" class="font-weight-bold"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_terminal">New Terminal:</label>
                        <select name="new_terminal" id="new_terminal" class="form-control" required>
                            <option value="">Select Terminal</option>
                            <option value="1">Terminal 1</option>
                            <option value="2">Terminal 2</option>
                            <option value="3">Terminal 3</option>
                            <option value="4">Terminal 4</option>
                            <option value="5">Terminal 5</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_gate">New Gate:</label>
                        <select name="new_gate" id="new_gate" class="form-control" required>
                            <option value="">Select Gate</option>
                            <?php for ($i = 1; $i <= 30; $i++): ?>
                                <option value="<?php echo $i; ?>">Gate <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="gate_remarks">Remarks:</label>
                        <textarea name="gate_remarks" id="gate_remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_gate" class="btn btn-primary">Change Gate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Update Status Modal
        $('#updateStatusModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var flightId = button.data('flight-id');
            var flightNumber = button.data('flight-number');
            var currentStatus = button.data('current-status');
            
            var modal = $(this);
            modal.find('#status_flight_id').val(flightId);
            modal.find('#status_flight_number').text(flightNumber);
            modal.find('#current_status').html('<span class="status-badge badge-' + getStatusBadgeClass(currentStatus) + '">' + currentStatus.replace('_', ' ').charAt(0).toUpperCase() + currentStatus.slice(1) + '</span>');
            
            // Reset fields
            modal.find('#new_status').val('');
            modal.find('#delay_fields').hide();
        });
        
        // Show/hide delay fields based on status selection
        $('#new_status').change(function() {
            if ($(this).val() === 'delayed' || $(this).val() === 'cancelled') {
                $('#delay_fields').show();
            } else {
                $('#delay_fields').hide();
            }
        });
        
        // Change Gate Modal
        $('#changeGateModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var flightId = button.data('flight-id');
            var flightNumber = button.data('flight-number');
            var currentGate = button.data('current-gate');
            var currentTerminal = button.data('current-terminal');
            
            var modal = $(this);
            modal.find('#gate_flight_id').val(flightId);
            modal.find('#gate_flight_number').text(flightNumber);
            modal.find('#current_gate').text('Terminal ' + currentTerminal + ' / Gate ' + currentGate);
            
            // Reset fields
            modal.find('#new_terminal').val('');
            modal.find('#new_gate').val('');
        });
    });
    
    // Helper function for status badge class
    function getStatusBadgeClass(status) {
        switch (status) {
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
</script>

<?php
// Helper function for status badge class
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

include '../includes/footer.php';

?>