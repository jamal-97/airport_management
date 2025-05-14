<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Handle flight deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $flight_id = $_GET['id'];
    
    // Check if flight exists
    $query = "SELECT id, flight_number FROM flights WHERE id = ?";
    $result = $db->executeQuery($query, "i", [$flight_id]);
    
    if ($result && count($result) > 0) {
        $flight_number = $result[0]['flight_number'];
        
        // Check if there are bookings for this flight
        $query = "SELECT COUNT(*) as booking_count FROM bookings WHERE flight_id = ?";
        $result = $db->executeQuery($query, "i", [$flight_id]);
        $booking_count = $result[0]['booking_count'];
        
        if ($booking_count > 0) {
            $error = "Cannot delete flight $flight_number as it has $booking_count booking(s). Cancel the bookings first.";
        } else {
            // Delete flight
            $query = "DELETE FROM flights WHERE id = ?";
            $result = $db->executeQuery($query, "i", [$flight_id]);
            
            if ($result) {
                // Log the action
                $admin_id = $_SESSION['user_id'];
                $query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'delete_flight', ?, NOW())";
                $db->executeQuery($query, "is", [$admin_id, "Deleted flight ID: $flight_id, Flight Number: $flight_number"]);
                
                $success = "Flight $flight_number has been deleted successfully.";
            } else {
                $error = "Failed to delete flight. Please try again.";
            }
        }
    } else {
        $error = "Flight not found.";
    }
}

// Handle flight status change
if (isset($_GET['action']) && $_GET['action'] == 'change_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $flight_id = $_GET['id'];
    $new_status = $_GET['status'];
    
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
            $query = "UPDATE flights SET status = ? WHERE id = ?";
            $result = $db->executeQuery($query, "si", [$new_status, $flight_id]);
            
            if ($result) {
                // Log the action
                $admin_id = $_SESSION['user_id'];
                $query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, 'update_flight_status', ?, NOW())";
                $db->executeQuery($query, "is", [$admin_id, "Changed flight ID: $flight_id, Flight Number: $flight_number status to $new_status"]);
                
                // If cancelled, notify passengers
                if ($new_status == 'cancelled') {
                    // Get passengers
                    $query = "SELECT u.email, u.first_name, u.last_name 
                             FROM bookings b 
                             JOIN users u ON b.user_id = u.id 
                             WHERE b.flight_id = ? AND b.status != 'cancelled'";
                    $passengers = $db->executeQuery($query, "i", [$flight_id]);
                    
                    // TODO: Send email notifications
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

// Pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';

// Parse date range
$start_date = '';
$end_date = '';
if (!empty($date_range)) {
    $dates = explode(' - ', $date_range);
    if (count($dates) == 2) {
        $start_date = date('Y-m-d', strtotime($dates[0]));
        $end_date = date('Y-m-d', strtotime($dates[1]));
    }
}

// Build the query
$query = "SELECT f.id, f.flight_number, f.departure_airport, f.arrival_airport, 
          f.departure_time, f.arrival_time, f.aircraft_id, f.status,
          a.name as aircraft_name
          FROM flights f
          LEFT JOIN aircraft a ON f.aircraft_id = a.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM flights f WHERE 1=1";
$query_params = [];
$param_types = "";

// Add search condition if provided
if (!empty($search)) {
    $query .= " AND (f.flight_number LIKE ? OR f.departure_airport LIKE ? OR f.arrival_airport LIKE ?)";
    $count_query .= " AND (flight_number LIKE ? OR departure_airport LIKE ? OR arrival_airport LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "sss";
}

// Add status filter if provided
if (!empty($status_filter)) {
    $query .= " AND f.status = ?";
    $count_query .= " AND status = ?";
    $query_params[] = $status_filter;
    $param_types .= "s";
}

// Add date range if provided
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(f.departure_time) BETWEEN ? AND ?";
    $count_query .= " AND DATE(departure_time) BETWEEN ? AND ?";
    $query_params[] = $start_date;
    $query_params[] = $end_date;
    $param_types .= "ss";
}

// Add order by and limit
$query .= " ORDER BY f.departure_time DESC LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Execute count query
$count_result = $db->executeQuery($count_query, empty($param_types) ? null : substr($param_types, 0, -2), array_slice($query_params, 0, -2));
$total_flights = $count_result[0]['total'];
$total_pages = ceil($total_flights / $limit);

// Execute main query
$flights = $db->executeQuery($query, $param_types, $query_params);

// Include header
$page_title = "Manage Flights";
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Flights</h1>
    <a href="add_flight.php" class="btn btn-primary">
        <i class="fas fa-plane-departure"></i> Add New Flight
    </a>
</div>

<!-- Filter and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="manage_flights.php" class="row">
            <div class="col-md-3 mb-3">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Flight number or airport">
            </div>
            <div class="col-md-3 mb-3">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="scheduled" <?php echo ($status_filter == 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="boarding" <?php echo ($status_filter == 'boarding') ? 'selected' : ''; ?>>Boarding</option>
                    <option value="departed" <?php echo ($status_filter == 'departed') ? 'selected' : ''; ?>>Departed</option>
                    <option value="in_air" <?php echo ($status_filter == 'in_air') ? 'selected' : ''; ?>>In Air</option>
                    <option value="landed" <?php echo ($status_filter == 'landed') ? 'selected' : ''; ?>>Landed</option>
                    <option value="arrived" <?php echo ($status_filter == 'arrived') ? 'selected' : ''; ?>>Arrived</option>
                    <option value="delayed" <?php echo ($status_filter == 'delayed') ? 'selected' : ''; ?>>Delayed</option>
                    <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="date_range">Date Range</label>
                <input type="text" name="date_range" id="date_range" class="form-control daterangepicker" value="<?php echo htmlspecialchars($date_range); ?>" placeholder="Select date range">
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

<!-- Flights Table -->
<div class="card">
    <div class="card-header">
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-0">Flights List</h5>
            </div>
            <div class="col-md-6 text-right">
                <span class="badge badge-primary"><?php echo number_format($total_flights); ?> Total Flights</span>
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
                            <th>Departure</th>
                            <th>Arrival</th>
                            <th>Aircraft</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flights as $flight): ?>
                            <tr>
                                <td><?php echo $flight['flight_number']; ?></td>
                                <td><?php echo $flight['departure_airport'] . ' - ' . $flight['arrival_airport']; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($flight['departure_time'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($flight['arrival_time'])); ?></td>
                                <td><?php echo $flight['aircraft_name']; ?></td>
                                <td>
                                    <span class="status-badge badge-<?php echo getStatusBadgeClass($flight['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $flight['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="actionDropdown<?php echo $flight['id']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Actions
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="actionDropdown<?php echo $flight['id']; ?>">
                                            <a class="dropdown-item" href="view_flight.php?id=<?php echo $flight['id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <a class="dropdown-item" href="edit_flight.php?id=<?php echo $flight['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit Flight
                                            </a>
                                            <a class="dropdown-item" href="view_passengers.php?flight_id=<?php echo $flight['id']; ?>">
                                                <i class="fas fa-users"></i> View Passengers
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <h6 class="dropdown-header">Change Status</h6>
                                            <?php if ($flight['status'] != 'scheduled'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=scheduled">
                                                    <span class="status-badge badge-secondary">Scheduled</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($flight['status'] != 'boarding'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=boarding">
                                                    <span class="status-badge badge-info">Boarding</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($flight['status'] != 'departed'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=departed">
                                                    <span class="status-badge badge-primary">Departed</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($flight['status'] != 'in_air'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=in_air">
                                                    <span class="status-badge badge-primary">In Air</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($flight['status'] != 'landed'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=landed">
                                                    <span class="status-badge badge-info">Landed</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($flight['status'] != 'arrived'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=arrived">
                                                    <span class="status-badge badge-success">Arrived</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($flight['status'] != 'delayed'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=delayed">
                                                    <span class="status-badge badge-warning">Delayed</span>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($flight['status'] != 'cancelled'): ?>
                                                <a class="dropdown-item" href="?action=change_status&id=<?php echo $flight['id']; ?>&status=cancelled" onclick="return confirm('Are you sure you want to cancel this flight? This will notify all passengers.');">
                                                    <span class="status-badge badge-danger">Cancelled</span>
                                                </a>
                                            <?php endif; ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $flight['id']; ?>" onclick="return confirm('Are you sure you want to delete this flight? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i> Delete Flight
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Flight navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>">
                                    First
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>">
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
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>">
                                    Next
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>">
                                    Last
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No flights found matching your criteria.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Date Range Picker -->
<script>
    $(document).ready(function() {
        if ($.fn.daterangepicker) {
            $('.daterangepicker').daterangepicker({
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear',
                    format: 'YYYY-MM-DD'
                }
            });
            
            $('.daterangepicker').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            });
            
            $('.daterangepicker').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });
        }
    });
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