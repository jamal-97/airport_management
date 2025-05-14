<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Check if flight ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage_flights.php?error=missing_id");
    exit();
}

$flight_id = intval($_GET['id']);

// Get dropdown data
$aircrafts = $db->executeQuery("SELECT id, name, model FROM aircraft WHERE status = 'active' ORDER BY name");
$airlines = $db->executeQuery("SELECT id, code, name FROM airlines WHERE status = 'active' ORDER BY name");
$airports = $db->executeQuery("SELECT code, name, city, country FROM airports WHERE status = 'active' ORDER BY name");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $flight_number = trim($_POST['flight_number']);
    $airline_id = $_POST['airline_id'];
    $aircraft_id = $_POST['aircraft_id'];
    $departure_airport = trim($_POST['departure_airport']);
    $arrival_airport = trim($_POST['arrival_airport']);
    $departure_time = trim($_POST['departure_time']);
    $arrival_time = trim($_POST['arrival_time']);
    $status = $_POST['status'];
    $terminal = trim($_POST['terminal']);
    $gate = trim($_POST['gate']);

    // Validate inputs
    $errors = [];
    
    if (empty($flight_number)) $errors[] = "Flight number is required.";
    if (empty($airline_id)) $errors[] = "Airline is required.";
    if (empty($aircraft_id)) $errors[] = "Aircraft is required.";
    if (empty($departure_airport)) $errors[] = "Departure airport is required.";
    if (empty($arrival_airport)) $errors[] = "Arrival airport is required.";
    if ($arrival_airport === $departure_airport) $errors[] = "Arrival airport must be different from departure airport.";
    if (empty($departure_time)) $errors[] = "Departure time is required.";
    if (empty($arrival_time)) $errors[] = "Arrival time is required.";
    if (strtotime($arrival_time) <= strtotime($departure_time)) $errors[] = "Arrival time must be after departure time.";
    if (empty($status)) $errors[] = "Status is required.";

    // If no errors, proceed with flight update
    if (empty($errors)) {
        try {
            // Update flight
            $query = "UPDATE flights SET 
                flight_number = ?, airline_id = ?, aircraft_id = ?, 
                departure_airport = ?, arrival_airport = ?, 
                departure_time = ?, arrival_time = ?, 
                status = ?, terminal = ?, gate = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $result = $db->executeQuery($query, "siisssssssi", [
                $flight_number,
                $airline_id,
                $aircraft_id,
                $departure_airport,
                $arrival_airport,
                $departure_time,
                $arrival_time,
                $status,
                $terminal,
                $gate,
                $flight_id
            ]);
            
            if ($result) {
                // Log the action
                $admin_id = $_SESSION['user_id'];
                $query = "INSERT INTO admin_logs (
                    admin_id, action, details, created_at
                ) VALUES (?, 'edit_flight', ?, NOW())";
                
                $db->executeQuery($query, "is", [
                    $admin_id,
                    "Updated flight ID: $flight_id, Flight Number: $flight_number"
                ]);
                
                $_SESSION['success'] = "Flight updated successfully!";
                header("Location: manage_flights.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again later.";
            }
        } catch (Exception $e) {
            error_log("Flight update error: " . $e->getMessage());
            $error = "Failed to update flight. Error: " . $e->getMessage();
        }
    }
}

// Get flight data
$query = "SELECT * FROM flights WHERE id = ?";
$result = $db->executeQuery($query, "i", [$flight_id]);

if (!$result || count($result) == 0) {
    header("Location: manage_flights.php?error=flight_not_found");
    exit();
}

$flight = $result[0];

// Include header
$page_title = "Edit Flight";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Edit Flight: <?php echo htmlspecialchars($flight['flight_number']); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="edit_flight.php?id=<?php echo $flight_id; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="flight_number" class="form-label">Flight Number</label>
                                <input type="text" class="form-control" id="flight_number" name="flight_number" 
                                       value="<?php echo isset($_POST['flight_number']) ? htmlspecialchars($_POST['flight_number']) : htmlspecialchars($flight['flight_number']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="airline_id" class="form-label">Airline</label>
                                <select class="form-select" id="airline_id" name="airline_id" required>
                                    <option value="">Select Airline</option>
                                    <?php foreach ($airlines as $airline): ?>
                                        <option value="<?php echo $airline['id']; ?>"
                                            <?php echo (isset($_POST['airline_id']) && $_POST['airline_id'] == $airline['id']) || (!isset($_POST['airline_id']) && $flight['airline_id'] == $airline['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($airline['name'] . ' (' . $airline['code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="aircraft_id" class="form-label">Aircraft</label>
                                <select class="form-select" id="aircraft_id" name="aircraft_id" required>
                                    <option value="">Select Aircraft</option>
                                    <?php foreach ($aircrafts as $aircraft): ?>
                                        <option value="<?php echo $aircraft['id']; ?>"
                                            <?php echo (isset($_POST['aircraft_id']) && $_POST['aircraft_id'] == $aircraft['id']) || (!isset($_POST['aircraft_id']) && $flight['aircraft_id'] == $aircraft['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($aircraft['name'] . ' (' . $aircraft['model'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="scheduled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'scheduled') || (!isset($_POST['status']) && $flight['status'] === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="boarding" <?php echo (isset($_POST['status']) && $_POST['status'] === 'boarding') || (!isset($_POST['status']) && $flight['status'] === 'boarding') ? 'selected' : ''; ?>>Boarding</option>
                                    <option value="departed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'departed') || (!isset($_POST['status']) && $flight['status'] === 'departed') ? 'selected' : ''; ?>>Departed</option>
                                    <option value="in_air" <?php echo (isset($_POST['status']) && $_POST['status'] === 'in_air') || (!isset($_POST['status']) && $flight['status'] === 'in_air') ? 'selected' : ''; ?>>In Air</option>
                                    <option value="landed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'landed') || (!isset($_POST['status']) && $flight['status'] === 'landed') ? 'selected' : ''; ?>>Landed</option>
                                    <option value="arrived" <?php echo (isset($_POST['status']) && $_POST['status'] === 'arrived') || (!isset($_POST['status']) && $flight['status'] === 'arrived') ? 'selected' : ''; ?>>Arrived</option>
                                    <option value="delayed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'delayed') || (!isset($_POST['status']) && $flight['status'] === 'delayed') ? 'selected' : ''; ?>>Delayed</option>
                                    <option value="cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'cancelled') || (!isset($_POST['status']) && $flight['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="departure_airport" class="form-label">Departure Airport</label>
                                <select class="form-select" id="departure_airport" name="departure_airport" required>
                                    <option value="">Select Departure Airport</option>
                                    <?php foreach ($airports as $airport): ?>
                                        <option value="<?php echo $airport['code']; ?>"
                                            <?php echo (isset($_POST['departure_airport']) && $_POST['departure_airport'] == $airport['code']) || (!isset($_POST['departure_airport']) && $flight['departure_airport'] == $airport['code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($airport['name'] . ' (' . $airport['code'] . ') - ' . $airport['city'] . ', ' . $airport['country']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="arrival_airport" class="form-label">Arrival Airport</label>
                                <select class="form-select" id="arrival_airport" name="arrival_airport" required>
                                    <option value="">Select Arrival Airport</option>
                                    <?php foreach ($airports as $airport): ?>
                                        <option value="<?php echo $airport['code']; ?>"
                                            <?php echo (isset($_POST['arrival_airport']) && $_POST['arrival_airport'] == $airport['code']) || (!isset($_POST['arrival_airport']) && $flight['arrival_airport'] == $airport['code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($airport['name'] . ' (' . $airport['code'] . ') - ' . $airport['city'] . ', ' . $airport['country']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="departure_time" class="form-label">Departure Time</label>
                                <input type="datetime-local" class="form-control" id="departure_time" name="departure_time" 
                                       value="<?php echo isset($_POST['departure_time']) ? htmlspecialchars($_POST['departure_time']) : date('Y-m-d\TH:i', strtotime($flight['departure_time'])); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="arrival_time" class="form-label">Arrival Time</label>
                                <input type="datetime-local" class="form-control" id="arrival_time" name="arrival_time" 
                                       value="<?php echo isset($_POST['arrival_time']) ? htmlspecialchars($_POST['arrival_time']) : date('Y-m-d\TH:i', strtotime($flight['arrival_time'])); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="terminal" class="form-label">Terminal</label>
                                <input type="text" class="form-control" id="terminal" name="terminal" 
                                       value="<?php echo isset($_POST['terminal']) ? htmlspecialchars($_POST['terminal']) : htmlspecialchars($flight['terminal']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gate" class="form-label">Gate</label>
                                <input type="text" class="form-control" id="gate" name="gate" 
                                       value="<?php echo isset($_POST['gate']) ? htmlspecialchars($_POST['gate']) : htmlspecialchars($flight['gate']); ?>">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="manage_flights.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Flight
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>