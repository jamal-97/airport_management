<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a traveller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'traveller') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Check if booking confirmation exists in session
if (!isset($_SESSION['booking_confirmation'])) {
    header("Location: purchase_tickets.php");
    exit();
}

// Get user ID and booking confirmation
$user_id = $_SESSION['user_id'];
$confirmation = $_SESSION['booking_confirmation'];
$booking_id = $confirmation['booking_id'];

// Get booking details
$query = "SELECT b.id, b.booking_reference, b.class, b.price, b.created_at,
         f.flight_number, f.departure_airport, f.arrival_airport, 
         f.departure_time, f.arrival_time, f.terminal, f.gate,
         a.name as airline_name, ac.name as aircraft_name, ac.model as aircraft_model
         FROM bookings b
         JOIN flights f ON b.flight_id = f.id
         JOIN airlines a ON f.airline_id = a.id
         JOIN aircraft ac ON f.aircraft_id = ac.id
         WHERE b.id = ? AND b.user_id = ?";
$result = $db->executeQuery($query, "ii", [$booking_id, $user_id]);

if (!$result || count($result) == 0) {
    header("Location: flight_info.php");
    exit();
}

$booking = $result[0];

// Get payment details
$query = "SELECT p.amount, p.payment_method, p.card_last_four, p.created_at
         FROM payments p
         WHERE p.booking_id = ?";
$payment_result = $db->executeQuery($query, "i", [$booking_id]);
$payment = $payment_result[0];

// Clear the booking confirmation from session to prevent refresh issues
unset($_SESSION['booking_confirmation']);

// Include header
$page_title = "Booking Confirmation";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0">Booking Confirmed!</h3>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                        <h4 class="mt-3">Your flight has been successfully booked!</h4>
                        <p class="lead">Booking Reference: <strong><?php echo $booking['booking_reference']; ?></strong></p>
                        <p>An email confirmation has been sent to your registered email address.</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Flight Details</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Airline:</strong> <?php echo $booking['airline_name']; ?></p>
                                    <p><strong>Flight:</strong> <?php echo $booking['flight_number']; ?></p>
                                    <p><strong>Aircraft:</strong> <?php echo $booking['aircraft_name'] . ' (' . $booking['aircraft_model'] . ')'; ?></p>
                                    <p><strong>Class:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['class'])); ?></p>
                                    
                                    <hr>
                                    
                                    <div class="flight-route mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-center">
                                                <div class="h5 mb-0"><?php echo $booking['departure_airport']; ?></div>
                                                <div class="small"><?php echo getAirportName($booking['departure_airport']); ?></div>
                                            </div>
                                            <div class="flight-route-line">
                                                <i class="fas fa-plane"></i>
                                            </div>
                                            <div class="text-center">
                                                <div class="h5 mb-0"><?php echo $booking['arrival_airport']; ?></div>
                                                <div class="small"><?php echo getAirportName($booking['arrival_airport']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <p><strong>Departure:</strong><br>
                                            <?php echo date('M d, Y', strtotime($booking['departure_time'])); ?><br>
                                            <?php echo date('H:i', strtotime($booking['departure_time'])); ?></p>
                                        </div>
                                        <div class="col-6">
                                            <p><strong>Arrival:</strong><br>
                                            <?php echo date('M d, Y', strtotime($booking['arrival_time'])); ?><br>
                                            <?php echo date('H:i', strtotime($booking['arrival_time'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($booking['terminal'] && $booking['gate']): ?>
                                        <p><strong>Terminal:</strong> <?php echo $booking['terminal']; ?></p>
                                        <p><strong>Gate:</strong> <?php echo $booking['gate']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Payment Information</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Amount Paid:</strong> $<?php echo number_format($payment['amount'], 2); ?></p>
                                    <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></p>
                                    <?php if (isset($payment['card_last_four'])): ?>
                                        <p><strong>Card:</strong> **** **** **** <?php echo $payment['card_last_four']; ?></p>
                                    <?php endif; ?>
                                    <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></p>
                                    
                                    <hr>
                                    
                                    <h6>Important Information</h6>
                                    <ul class="mb-0">
                                        <li>Please arrive at the airport at least 2 hours before departure.</li>
                                        <li>Check-in opens 24 hours before departure.</li>
                                        <li>Bring a valid ID or passport for check-in.</li>
                                        <li>Baggage policies vary by airline. Please check with <?php echo $booking['airline_name']; ?> for details.</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Next Steps</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-3">
                                        <a href="flight_info.php" class="btn btn-primary btn-block">
                                            <i class="fas fa-plane"></i> View My Flights
                                        </a>
                                        <a href="#" class="btn btn-outline-primary btn-block" onclick="window.print();">
                                            <i class="fas fa-print"></i> Print Confirmation
                                        </a>
                                        <a href="purchase_tickets.php" class="btn btn-outline-secondary btn-block">
                                            <i class="fas fa-search"></i> Search More Flights
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.flight-route-line {
    position: relative;
    height: 2px;
    background-color: #ddd;
    flex-grow: 1;
    margin: 0 15px;
}

.flight-route-line i {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--primary);
}

@media print {
    header, footer, .card-header, .btn {
        display: none;
    }
    
    .container {
        width: 100%;
        max-width: none;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
}
</style>

<?php
// Helper functions
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