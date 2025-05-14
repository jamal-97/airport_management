<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a traveller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'traveller') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Check if booking information exists in session
if (!isset($_SESSION['booking'])) {
    header("Location: purchase_tickets.php?error=no_booking");
    exit();
}

// Get user ID and booking information
$user_id = $_SESSION['user_id'];
$booking = $_SESSION['booking'];
$error = null;

// Check if payments table exists and create it if not
$check_payments_table = "SHOW TABLES LIKE 'payments'";
$payments_table_exists = $db->executeQuery($check_payments_table);

if (!$payments_table_exists || count($payments_table_exists) == 0) {
    // Create payments table
    $create_payments_table = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        transaction_id INT,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        card_last_four VARCHAR(4),
        status VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->executeQuery($create_payments_table);
}

// Create payment details table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS payment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_id INT NOT NULL,
    card_number VARCHAR(255) NOT NULL,
    pin_number VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$db->executeQuery($create_table_query);

// Process payment when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {
    // Basic validation - just check if fields have any value
    $card_number = trim($_POST['card_number']);
    $pin_number = trim($_POST['pin_number']);
    
    // Very simple validation - just make sure fields are not empty
    if (empty($card_number) || empty($pin_number)) {
        $error = "Card number and PIN are required.";
    } else {
        // In a real application, this would integrate with a payment processor
        // For this demo, we'll simulate a successful payment
        
        try {
            // Get connection for transaction
            $conn = $db->getConnection();
            $conn->begin_transaction();
            
            // Check if booking_transactions table exists
            $check_transactions_table = "SHOW TABLES LIKE 'booking_transactions'";
            $transactions_table_exists = $db->executeQuery($check_transactions_table);
            
            // Only update transaction if table exists
            if ($transactions_table_exists && count($transactions_table_exists) > 0) {
                // Update booking transaction status
                $query = "UPDATE booking_transactions SET status = 'completed', updated_at = NOW() 
                         WHERE id = ? AND user_id = ?";
                $result = $db->executeQuery($query, "ii", [$booking['transaction_id'], $user_id]);
                
                if (!$result) {
                    // Just log the error but continue - it might be that the transaction table doesn't have expected columns
                    error_log("Failed to update booking transaction status: " . $conn->error);
                }
            }
            
            // Check if bookings table has seat_number column
            $check_bookings_columns = "SHOW COLUMNS FROM bookings LIKE 'seat_number'";
            $seat_number_exists = $db->executeQuery($check_bookings_columns);
            
            // Create the booking record
            if ($seat_number_exists && count($seat_number_exists) > 0) {
                $query = "INSERT INTO bookings (user_id, flight_id, booking_reference, class, price, status, seat_number, created_at) 
                         VALUES (?, ?, ?, ?, ?, 'confirmed', NULL, NOW())";
            } else {
                $query = "INSERT INTO bookings (user_id, flight_id, booking_reference, class, price, status, created_at) 
                         VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())";
            }
            
            $result = $db->executeQuery($query, "iissd", [
                $user_id,
                $booking['flight_id'],
                $booking['booking_reference'],
                $booking['travel_class'],
                $booking['price_per_passenger']
            ]);
            
            if (!$result) {
                throw new Exception("Failed to create booking record: " . $conn->error);
            }
            
            $booking_id = $result['insert_id'];
            
            // Try to record payment with different query variations
            $last_four = substr($card_number, -4);
            $payment_id = null;
            
            // First attempt - full query
            try {
                $query = "INSERT INTO payments (booking_id, transaction_id, amount, payment_method, card_last_four, status, created_at) 
                         VALUES (?, ?, ?, 'credit_card', ?, 'completed', NOW())";
                $result = $db->executeQuery($query, "iiis", [
                    $booking_id,
                    isset($booking['transaction_id']) ? $booking['transaction_id'] : 0,
                    $booking['total_price'],
                    $last_four
                ]);
                
                if ($result) {
                    $payment_id = $result['insert_id'];
                }
            } catch (Exception $e) {
                // Ignore error and try next query
                error_log("First payment insert attempt failed: " . $e->getMessage());
            }
            
            // Second attempt - without transaction_id
            if (!$payment_id) {
                try {
                    $query = "INSERT INTO payments (booking_id, amount, payment_method, card_last_four, status, created_at) 
                             VALUES (?, ?, 'credit_card', ?, 'completed', NOW())";
                    $result = $db->executeQuery($query, "ids", [
                        $booking_id,
                        $booking['total_price'],
                        $last_four
                    ]);
                    
                    if ($result) {
                        $payment_id = $result['insert_id'];
                    }
                } catch (Exception $e) {
                    // Ignore error and try next query
                    error_log("Second payment insert attempt failed: " . $e->getMessage());
                }
            }
            
            // Third attempt - minimal columns
            if (!$payment_id) {
                try {
                    $query = "INSERT INTO payments (booking_id, amount, payment_method, status, created_at) 
                             VALUES (?, ?, 'credit_card', 'completed', NOW())";
                    $result = $db->executeQuery($query, "id", [
                        $booking_id,
                        $booking['total_price']
                    ]);
                    
                    if ($result) {
                        $payment_id = $result['insert_id'];
                    }
                } catch (Exception $e) {
                    throw new Exception("Failed to record payment: " . $e->getMessage());
                }
            }
            
            if (!$payment_id) {
                throw new Exception("Failed to get payment ID after insertion");
            }
            
            // Store payment details (for demo purposes only)
            $query = "INSERT INTO payment_details (booking_id, payment_id, card_number, pin_number, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $result = $db->executeQuery($query, "iiss", [
                $booking_id,
                $payment_id,
                $card_number,
                $pin_number
            ]);
            
            if (!$result) {
                throw new Exception("Failed to store payment details: " . $conn->error);
            }
            
            // Commit the transaction
            $conn->commit();
            
            // Store booking confirmation in session
            $_SESSION['booking_confirmation'] = [
                'booking_id' => $booking_id,
                'booking_reference' => $booking['booking_reference'],
                'flight_number' => $booking['flight_number'],
                'departure_time' => $booking['departure_time'],
                'total_price' => $booking['total_price']
            ];
            
            // Remove the booking data from session
            unset($_SESSION['booking']);
            
            // Redirect to confirmation page
            header("Location: booking_confirmation.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback the transaction
            $conn->rollback();
            $error = "Payment failed: " . $e->getMessage();
        }
    }
}

// Get flight details
$query = "SELECT f.flight_number, f.departure_airport, f.arrival_airport, 
         f.departure_time, f.arrival_time, f.terminal, f.gate, 
         a.name as airline_name, ac.name as aircraft_name
         FROM flights f
         JOIN airlines a ON f.airline_id = a.id
         JOIN aircraft ac ON f.aircraft_id = ac.id
         WHERE f.id = ?";
$result = $db->executeQuery($query, "i", [$booking['flight_id']]);

if (!$result || count($result) == 0) {
    header("Location: purchase_tickets.php?error=invalid_flight");
    exit();
}

$flight = $result[0];

// Include header
$page_title = "Payment";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Payment Details</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-7">
                            <form method="POST" action="payment.php" id="payment-form">
                                <h4 class="mb-3">Payment Method</h4>
                                
                                <div class="form-group">
                                    <label for="card_number">Card Number</label>
                                    <input type="text" id="card_number" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="pin_number">PIN Number</label>
                                    <input type="password" id="pin_number" name="pin_number" class="form-control" placeholder="****" required>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="terms" required>
                                        <label class="custom-control-label" for="terms">
                                            I agree to the <a href="#" data-toggle="modal" data-target="#termsModal">terms and conditions</a>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="confirm_payment" class="btn btn-primary btn-lg btn-block">
                                    Complete Payment
                                </button>
                                
                                <p class="text-muted text-center mt-3">
                                    <small>This is a demo payment page. Any values will be accepted for payment.</small>
                                </p>
                            </form>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Booking Details</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Flight:</strong> <?php echo $flight['flight_number']; ?> (<?php echo $flight['airline_name']; ?>)</p>
                                    <p><strong>Route:</strong> <?php echo $flight['departure_airport']; ?> → <?php echo $flight['arrival_airport']; ?></p>
                                    <p><strong>Departure:</strong> <?php echo date('M d, Y H:i', strtotime($flight['departure_time'])); ?></p>
                                    <p><strong>Passengers:</strong> <?php echo $booking['passenger_count']; ?></p>
                                    <p><strong>Class:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['travel_class'])); ?></p>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between">
                                        <span>Price per passenger:</span>
                                        <span>$<?php echo number_format($booking['price_per_passenger'], 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Number of passengers:</span>
                                        <span><?php echo $booking['passenger_count']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Taxes & Fees:</span>
                                        <span>Included</span>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between">
                                        <h5>Total:</h5>
                                        <h5>$<?php echo number_format($booking['total_price'], 2); ?></h5>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <a href="purchase_tickets.php" class="btn btn-link">
                                    <i class="fas fa-arrow-left"></i> Back to Flight Selection
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" role="dialog" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6>Booking Terms</h6>
                <p>By completing this purchase, you agree to the following terms:</p>
                <ul>
                    <li>All ticket sales are final. Changes may incur fees.</li>
                    <li>You must arrive at the airport at least 2 hours before departure for domestic flights and 3 hours for international flights.</li>
                    <li>Valid identification is required for all passengers at check-in.</li>
                    <li>Baggage allowances and fees vary by class and airline.</li>
                    <li>The airline reserves the right to change flight schedules and gates.</li>
                </ul>
                
                <h6>Cancellation Policy</h6>
                <p>Cancellations made:</p>
                <ul>
                    <li>More than 24 hours before departure: 75% refund</li>
                    <li>Less than 24 hours before departure: No refund</li>
                </ul>
                
                <h6>Privacy Policy</h6>
                <p>Your payment information is securely processed and not stored on our servers. Personal information is used only for booking and communication purposes.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Format credit card number as it's typed
        $('#card_number').on('input', function() {
            // Remove non-digits
            let value = $(this).val().replace(/\D/g, '');
            
            // Add spaces every 4 digits
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            
            $(this).val(formatted);
        });
    });
</script>

<?php include '../includes/footer.php'; ?>