<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php?error=unauthorized");
    exit();
}

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
    $economy_price = trim($_POST['economy_price']);
    $premium_economy_price = trim($_POST['premium_economy_price']);
    $business_price = trim($_POST['business_price']);
    $first_price = trim($_POST['first_price']);

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

    // Validate prices
    $prices = [
        'economy' => $economy_price,
        'premium_economy' => $premium_economy_price,
        'business' => $business_price,
        'first' => $first_price
    ];
    
    foreach ($prices as $class => $price) {
        if (empty($price)) {
            $errors[] = ucfirst(str_replace('_', ' ', $class)) . " price is required.";
        } elseif (!is_numeric($price) || $price <= 0) {
            $errors[] = ucfirst(str_replace('_', ' ', $class)) . " price must be a positive number.";
        }
    }
    
    // If no errors, proceed with flight creation
    if (empty($errors)) {
        try {
            // Start transaction manually since Database class doesn't have beginTransaction
            $conn = $db->getConnection();
            $conn->begin_transaction();
            
            // Insert flight
            $query = "INSERT INTO flights (
                flight_number, airline_id, aircraft_id, 
                departure_airport, arrival_airport, 
                departure_time, arrival_time, 
                status, terminal, gate, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $result = $db->executeQuery($query, "siisssssss", [
                $flight_number,
                $airline_id,
                $aircraft_id,
                $departure_airport,
                $arrival_airport,
                $departure_time,
                $arrival_time,
                $status,
                $terminal,
                $gate
            ]);
            
            if ($result === false || !isset($result['insert_id'])) {
                throw new Exception("Failed to insert flight: " . $conn->error);
            }
            
            $flight_id = $result['insert_id'];
            
            // Insert prices
            foreach ($prices as $class => $price) {
                $query = "INSERT INTO prices (
                    flight_id, class, amount, currency, valid_from
                ) VALUES (?, ?, ?, 'USD', NOW())";
                
                $priceResult = $db->executeQuery($query, "isd", [
                    $flight_id,
                    $class,
                    $price
                ]);
                
                if ($priceResult === false) {
                    throw new Exception("Failed to insert price for $class class: " . $conn->error);
                }
            }
            
            // Log the action
            $admin_id = $_SESSION['user_id'];
            $query = "INSERT INTO admin_logs (
                admin_id, action, details, created_at
            ) VALUES (?, 'add_flight', ?, NOW())";
            
            $logResult = $db->executeQuery($query, "is", [
                $admin_id,
                "Added new flight ID: $flight_id, Flight Number: $flight_number"
            ]);
            
            if ($logResult === false) {
                throw new Exception("Failed to log admin action: " . $conn->error);
            }
            
            // Commit the transaction
            $conn->commit();
            
            $_SESSION['success'] = "Flight added successfully!";
            header("Location: manage_flights.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback the transaction
            $conn = $db->getConnection();
            $conn->rollback();
            
            error_log("Flight creation error: " . $e->getMessage());
            $errors[] = "Failed to add flight. Error: " . $e->getMessage();
        }
    }
}

// Include header
$page_title = "Add New Flight";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Add New Flight</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="add_flight.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="flight_number" class="form-label">Flight Number</label>
                                <input type="text" class="form-control" id="flight_number" name="flight_number" 
                                       value="<?php echo isset($_POST['flight_number']) ? htmlspecialchars($_POST['flight_number']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="airline_id" class="form-label">Airline</label>
                                <select class="form-select" id="airline_id" name="airline_id" required>
                                    <option value="">Select Airline</option>
                                    <?php foreach ($airlines as $airline): ?>
                                        <option value="<?php echo $airline['id']; ?>"
                                            <?php echo (isset($_POST['airline_id']) && $_POST['airline_id'] == $airline['id']) ? 'selected' : ''; ?>>
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
                                            <?php echo (isset($_POST['aircraft_id']) && $_POST['aircraft_id'] == $aircraft['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($aircraft['name'] . ' (' . $aircraft['model'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="scheduled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="boarding" <?php echo (isset($_POST['status']) && $_POST['status'] === 'boarding') ? 'selected' : ''; ?>>Boarding</option>
                                    <option value="departed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'departed') ? 'selected' : ''; ?>>Departed</option>
                                    <option value="in_air" <?php echo (isset($_POST['status']) && $_POST['status'] === 'in_air') ? 'selected' : ''; ?>>In Air</option>
                                    <option value="landed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'landed') ? 'selected' : ''; ?>>Landed</option>
                                    <option value="arrived" <?php echo (isset($_POST['status']) && $_POST['status'] === 'arrived') ? 'selected' : ''; ?>>Arrived</option>
                                    <option value="delayed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'delayed') ? 'selected' : ''; ?>>Delayed</option>
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
                                            <?php echo (isset($_POST['departure_airport']) && $_POST['departure_airport'] == $airport['code']) ? 'selected' : ''; ?>>
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
                                            <?php echo (isset($_POST['arrival_airport']) && $_POST['arrival_airport'] == $airport['code']) ? 'selected' : ''; ?>>
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
                                       value="<?php echo isset($_POST['departure_time']) ? htmlspecialchars($_POST['departure_time']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="arrival_time" class="form-label">Arrival Time</label>
                                <input type="datetime-local" class="form-control" id="arrival_time" name="arrival_time" 
                                       value="<?php echo isset($_POST['arrival_time']) ? htmlspecialchars($_POST['arrival_time']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="terminal" class="form-label">Terminal</label>
                                <input type="text" class="form-control" id="terminal" name="terminal" 
                                       value="<?php echo isset($_POST['terminal']) ? htmlspecialchars($_POST['terminal']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gate" class="form-label">Gate</label>
                                <input type="text" class="form-control" id="gate" name="gate" 
                                       value="<?php echo isset($_POST['gate']) ? htmlspecialchars($_POST['gate']) : ''; ?>">
                            </div>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Ticket Prices</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="economy_price" class="form-label">Economy Class ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="economy_price" name="economy_price" 
                                       value="<?php echo isset($_POST['economy_price']) ? htmlspecialchars($_POST['economy_price']) : ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="premium_economy_price" class="form-label">Premium Economy ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="premium_economy_price" name="premium_economy_price" 
                                       value="<?php echo isset($_POST['premium_economy_price']) ? htmlspecialchars($_POST['premium_economy_price']) : ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="business_price" class="form-label">Business Class ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="business_price" name="business_price" 
                                       value="<?php echo isset($_POST['business_price']) ? htmlspecialchars($_POST['business_price']) : ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="first_price" class="form-label">First Class ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="first_price" name="first_price" 
                                       value="<?php echo isset($_POST['first_price']) ? htmlspecialchars($_POST['first_price']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="manage_flights.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plane-departure"></i> Add Flight
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>