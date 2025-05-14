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

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: flight_info.php?error=invalid_booking");
    exit();
}

$booking_id = intval($_GET['id']);

// Get booking details
$query = "SELECT b.id, b.booking_reference, b.class, b.price, b.status as booking_status, b.created_at,
         f.id as flight_id, f.flight_number, f.departure_airport, f.arrival_airport, 
         f.departure_time, f.arrival_time, f.status as flight_status, f.terminal, f.gate,
         a.name as airline_name, ac.name as aircraft_name, ac.model as aircraft_model,
         ci.boarding_pass_number, ci.check_in_time
         FROM bookings b
         JOIN flights f ON b.flight_id = f.id
         JOIN airlines a ON f.airline_id = a.id
         JOIN aircraft ac ON f.aircraft_id = ac.id
         LEFT JOIN check_ins ci ON b.id = ci.booking_id
         WHERE b.id = ? AND b.user_id = ?";
$result = $db->executeQuery($query, "ii", [$booking_id, $user_id]);

if (!$result || count($result) == 0) {
    header("Location: flight_info.php?error=booking_not_found");
    exit();
}

$booking = $result[0];

// Calculate if cancellation is available
$departure_time = strtotime($booking['departure_time']);
$current_time = time();
$time_remaining = $departure_time - $current_time;
$days_remaining = floor($time_remaining / (60 * 60 * 24));
$hours_remaining = floor(($time_remaining % (60 * 60 * 24)) / (60 * 60));
$cancellation_available = ($time_remaining > 24 * 60 * 60 && $booking['booking_status'] !== 'cancelled');

// Get payment details
$query = "SELECT p.id, p.amount, p.payment_method, p.card_last_four, p.created_at
         FROM payments p
         WHERE p.booking_id = ?";
$payment_result = $db->executeQuery($query, "i", [$booking_id]);
$payment = isset($payment_result[0]) ? $payment_result[0] : null;

// Include header
$page_title = "Booking Details";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Booking Details</h3>
                    <span class="badge badge-<?php echo getStatusBadgeClass($booking['booking_status']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $booking['booking_status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <!-- Display Cancel Option prominently for eligible bookings -->
                    <?php if ($booking['booking_status'] !== 'cancelled' && $cancellation_available): ?>
                        <div class="alert alert-warning mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Need to cancel this booking?</strong> You can cancel up to 24 hours before departure for a partial refund.
                                </div>
                                <a href="cancel_booking.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Cancel Booking
                                </a>
                            </div>
                        </div>
                    <?php elseif ($booking['booking_status'] === 'cancelled'): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-circle"></i> This booking has been cancelled.
                        </div>
                    <?php elseif ($time_remaining <= 24 * 60 * 60): ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle"></i> Your flight is departing soon. Cancellation is no longer available for this booking.
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4>Booking Information</h4>
                            <p><strong>Booking Reference:</strong> <?php echo $booking['booking_reference']; ?></p>
                            <p><strong>Booking Date:</strong> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?></p>
                            <p><strong>Class:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['class'])); ?></p>
                            <p><strong>Price:</strong> $<?php echo number_format($booking['price'], 2); ?></p>
                            
                            <?php if ($payment): ?>
                                <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></p>
                                <?php if ($payment['card_last_four']): ?>
                                    <p><strong>Card:</strong> **** **** **** <?php echo $payment['card_last_four']; ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h4>Flight Information</h4>
                            <p><strong>Airline:</strong> <?php echo $booking['airline_name']; ?></p>
                            <p><strong>Flight:</strong> <?php echo $booking['flight_number']; ?></p>
                            <p><strong>Aircraft:</strong> <?php echo $booking['aircraft_name'] . ' (' . $booking['aircraft_model'] . ')'; ?></p>
                            
                            <?php if ($booking['terminal'] && $booking['gate']): ?>
                                <p><strong>Terminal:</strong> <?php echo $booking['terminal']; ?></p>
                                <p><strong>Gate:</strong> <?php echo $booking['gate']; ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($booking['boarding_pass_number'])): ?>
                                <p><strong>Boarding Pass:</strong> <?php echo $booking['boarding_pass_number']; ?></p>
                                <p><strong>Checked In:</strong> <?php echo date('M d, Y H:i', strtotime($booking['check_in_time'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flight-card mb-4">
                        <div class="flight-card-header">
                            <div>
                                <h4><?php echo $booking['airline_name']; ?></h4>
                                <span><?php echo $booking['flight_number']; ?></span>
                            </div>
                            <div>
                                <span class="status-badge badge-<?php echo getStatusBadgeClass($booking['flight_status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['flight_status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flight-card-body">
                            <div class="flight-route">
                                <div class="flight-city">
                                    <div class="flight-city-code"><?php echo $booking['departure_airport']; ?></div>
                                    <div class="flight-city-name"><?php echo getAirportName($booking['departure_airport']); ?></div>
                                </div>
                                <div class="flight-route-line">
                                    <i class="fas fa-plane"></i>
                                </div>
                                <div class="flight-city">
                                    <div class="flight-city-code"><?php echo $booking['arrival_airport']; ?></div>
                                    <div class="flight-city-name"><?php echo getAirportName($booking['arrival_airport']); ?></div>
                                </div>
                            </div>
                            <div class="flight-time-info">
                                <div class="flight-time">
                                    <div class="flight-time-value"><?php echo date('H:i', strtotime($booking['departure_time'])); ?></div>
                                    <div class="flight-time-label"><?php echo date('M d, Y', strtotime($booking['departure_time'])); ?></div>
                                </div>
                                <div class="flight-duration">
                                    <div class="flight-duration-value"><?php echo calculateFlightDuration($booking['departure_time'], $booking['arrival_time']); ?></div>
                                    <div class="flight-duration-label">Duration</div>
                                </div>
                                <div class="flight-time">
                                    <div class="flight-time-value"><?php echo date('H:i', strtotime($booking['arrival_time'])); ?></div>
                                    <div class="flight-time-label"><?php echo date('M d, Y', strtotime($booking['arrival_time'])); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($booking['booking_status'] !== 'cancelled' && $time_remaining > 0): ?>
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
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($booking['booking_status'] !== 'cancelled' && $cancellation_available): ?>
                        <!-- Cancellation Policy Information -->
                        <div class="card mb-4 border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Cancellation Policy</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <ul class="mb-0">
                                            <li>More than 24 hours before departure: 75% refund (Eligible)</li>
                                            <li>Less than 24 hours before departure: No refund</li>
                                        </ul>
                                        <p class="mt-2">If you cancel this booking, you will receive a refund of approximately $<?php echo number_format($booking['price'] * 0.75, 2); ?></p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <a href="cancel_booking.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-danger btn-lg">
                                            <i class="fas fa-times-circle"></i> Cancel This Booking
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between">
                                <a href="flight_info.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to My Flights
                                </a>
                                
                                <div class="button-group">
                                    <?php if ($booking['booking_status'] !== 'cancelled'): ?>
                                        <?php if (!empty($booking['boarding_pass_number'])): ?>
                                            <a href="view_boarding_pass.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success">
                                                <i class="fas fa-ticket-alt"></i> View Boarding Pass
                                            </a>
                                        <?php elseif ($time_remaining <= 48 * 60 * 60 && $time_remaining > 0): ?>
                                            <a href="check_in.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-check-square"></i> Check-in
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Ensure Cancel button is always shown if eligible -->
                                        <?php if ($cancellation_available): ?>
                                            <a href="cancel_booking.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-danger ml-2">
                                                <i class="fas fa-times"></i> Cancel Booking
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="book_similar.php?flight_id=<?php echo $booking['flight_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-redo"></i> Book Similar Flight
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

include '../includes/footer.php';
?>