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
$error = null;
$success = null;

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header("Location: flight_info.php?error=invalid_booking");
    exit();
}

$booking_id = intval($_GET['booking_id']);

// Verify that the booking belongs to the user and is eligible for cancellation
$query = "SELECT b.id, b.booking_reference, b.status, b.price,
          f.flight_number, f.departure_time, f.status as flight_status
          FROM bookings b
          JOIN flights f ON b.flight_id = f.id
          WHERE b.id = ? AND b.user_id = ?";
$result = $db->executeQuery($query, "ii", [$booking_id, $user_id]);

if (!$result || count($result) == 0) {
    header("Location: flight_info.php?error=booking_not_found");
    exit();
}

$booking = $result[0];

// Check if booking is already cancelled
if ($booking['status'] === 'cancelled') {
    header("Location: flight_info.php?error=already_cancelled");
    exit();
}

// Check if flight has already departed
$departure_time = strtotime($booking['departure_time']);
$current_time = time();
if ($current_time > $departure_time) {
    header("Location: flight_info.php?error=flight_departed");
    exit();
}

// Process cancellation if confirmed
if (isset($_POST['confirm_cancellation'])) {
    $cancellation_reason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : 'user_request';
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    
    try {
        // Get connection for transaction
        $conn = $db->getConnection();
        $conn->begin_transaction();
        
        // Update booking status
        $query = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
        $result = $db->executeQuery($query, "i", [$booking_id]);
        
        if (!$result) {
            throw new Exception("Failed to update booking status.");
        }
        
        // Record cancellation
        $query = "INSERT INTO booking_cancellations (booking_id, reason, comments, created_at) 
                 VALUES (?, ?, ?, NOW())";
        
        // Check if booking_cancellations table exists
        $check_table = "SHOW TABLES LIKE 'booking_cancellations'";
        $table_exists = $db->executeQuery($check_table);
        
        // Create the table if it doesn't exist
        if (!$table_exists || count($table_exists) == 0) {
            $create_table = "CREATE TABLE booking_cancellations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                reason VARCHAR(50) NOT NULL,
                comments TEXT,
                refund_amount DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $db->executeQuery($create_table);
        }
        
        // Insert cancellation record
        $result = $db->executeQuery($query, "iss", [$booking_id, $cancellation_reason, $comments]);
        
        if (!$result) {
            throw new Exception("Failed to record cancellation.");
        }
        
        // Calculate refund amount (if any)
        // This is based on the business logic: 
        // - More than 24 hours before flight: 75% refund
        // - Less than 24 hours before flight: No refund
        $refund_amount = 0;
        $hours_until_departure = ($departure_time - $current_time) / 3600;
        
        if ($hours_until_departure > 24) {
            $refund_amount = $booking['price'] * 0.75;
            
            // Record refund
            $query = "UPDATE booking_cancellations SET refund_amount = ? WHERE booking_id = ?";
            $result = $db->executeQuery($query, "di", [$refund_amount, $booking_id]);
            
            if (!$result) {
                throw new Exception("Failed to record refund amount.");
            }
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Store cancellation details in session for confirmation page
        $_SESSION['cancellation_details'] = [
            'booking_reference' => $booking['booking_reference'],
            'flight_number' => $booking['flight_number'],
            'departure_time' => $booking['departure_time'],
            'refund_amount' => $refund_amount
        ];
        
        // Redirect to confirmation page
        header("Location: cancellation_confirmation.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback the transaction
        $conn->rollback();
        $error = "Cancellation failed: " . $e->getMessage();
    }
} else {
    // Display cancellation form
    // Include header
    $page_title = "Cancel Booking";
    include '../includes/header.php';
    ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h3 class="mb-0">Cancel Flight Booking</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Are you sure you want to cancel your booking? This action cannot be undone.
                        </div>
                        
                        <div class="booking-details mb-4">
                            <h4>Booking Details</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                                    <p><strong>Flight Number:</strong> <?php echo $booking['flight_number']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Departure:</strong> <?php echo date('M d, Y H:i', strtotime($booking['departure_time'])); ?></p>
                                    <p><strong>Status:</strong> <?php echo ucfirst($booking['status']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cancellation-policy mb-4">
                            <h4>Cancellation Policy</h4>
                            <ul>
                                <li>More than 24 hours before departure: 75% refund</li>
                                <li>Less than 24 hours before departure: No refund</li>
                            </ul>
                            
                            <?php 
                            $hours_until_departure = ($departure_time - $current_time) / 3600;
                            $refund_percentage = ($hours_until_departure > 24) ? '75%' : '0%';
                            $refund_amount = ($hours_until_departure > 24) ? ($booking['price'] * 0.75) : 0;
                            ?>
                            
                            <div class="alert alert-info">
                                <p><strong>Your refund eligibility:</strong> <?php echo $refund_percentage; ?></p>
                                <p><strong>Potential refund amount:</strong> $<?php echo number_format($refund_amount, 2); ?></p>
                            </div>
                        </div>
                        
                        <form method="POST" action="cancel_booking.php?booking_id=<?php echo $booking_id; ?>">
                            <div class="form-group">
                                <label for="cancellation_reason">Reason for Cancellation:</label>
                                <select name="cancellation_reason" id="cancellation_reason" class="form-control" required>
                                    <option value="">Select Reason</option>
                                    <option value="schedule_change">Change of Schedule</option>
                                    <option value="personal">Personal Reasons</option>
                                    <option value="business">Business Trip Cancelled</option>
                                    <option value="illness">Illness or Medical Issue</option>
                                    <option value="alternative">Found Alternative Transport</option>
                                    <option value="price">Found Better Price</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="comments">Additional Comments:</label>
                                <textarea name="comments" id="comments" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="confirm_cancellation_checkbox" id="confirm_cancellation_checkbox" class="custom-control-input" required>
                                    <label class="custom-control-label" for="confirm_cancellation_checkbox">I understand that by cancelling this booking, I may only receive a partial refund or no refund as per the cancellation policy.</label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="booking_details.php?id=<?php echo $booking_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Booking
                                </a>
                                <button type="submit" name="confirm_cancellation" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Cancel Booking
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    include '../includes/footer.php';
    exit();
}
?>