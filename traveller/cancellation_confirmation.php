<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a traveller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'traveller') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Check if cancellation details exist in session
if (!isset($_SESSION['cancellation_details'])) {
    header("Location: flight_info.php");
    exit();
}

// Get cancellation details
$cancellation = $_SESSION['cancellation_details'];

// Clear the cancellation details from session to prevent refresh issues
unset($_SESSION['cancellation_details']);

// Include header
$page_title = "Cancellation Confirmation";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0">Booking Cancellation Confirmed</h3>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                    <h4 class="mt-3">Your flight booking has been successfully cancelled!</h4>
                    <p class="lead">Booking Reference: <strong><?php echo $cancellation['booking_reference']; ?></strong></p>
                    
                    <div class="cancellation-details my-4">
                        <div class="row">
                            <div class="col-md-6 text-md-right">
                                <p><strong>Flight Number:</strong></p>
                                <p><strong>Scheduled Departure:</strong></p>
                                <p><strong>Cancellation Date:</strong></p>
                            </div>
                            <div class="col-md-6 text-md-left">
                                <p><?php echo $cancellation['flight_number']; ?></p>
                                <p><?php echo date('M d, Y H:i', strtotime($cancellation['departure_time'])); ?></p>
                                <p><?php echo date('M d, Y H:i'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($cancellation['refund_amount'] > 0): ?>
                        <div class="alert alert-info">
                            <h5>Refund Information</h5>
                            <p>A refund of <strong>$<?php echo number_format($cancellation['refund_amount'], 2); ?></strong> will be processed to your original payment method.</p>
                            <p>Please allow 5-7 business days for the refund to appear on your account.</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h5>No Refund Available</h5>
                            <p>As per our cancellation policy, no refund is available for this booking.</p>
                        </div>
                    <?php endif; ?>
                    
                    <p>A confirmation email has been sent to your registered email address.</p>
                    
                    <div class="mt-4">
                        <a href="flight_info.php" class="btn btn-primary">
                            <i class="fas fa-plane"></i> View My Flights
                        </a>
                        <a href="purchase_tickets.php" class="btn btn-outline-primary ml-2">
                            <i class="fas fa-search"></i> Book New Flight
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>