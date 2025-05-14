
<?php
session_start();
include 'db_connection.php';
include 'includes/header.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'staff':
            header("Location: staff/dashboard.php");
            break;
        case 'traveller':
            header("Location: traveller/dashboard.php");
            break;
        default:
            // Handle unexpected role
            session_destroy();
            header("Location: index.php?error=invalid_role");
            exit();
    }
}
?>

<div class="hero-section">
    <div class="container">
        <h1>Welcome to Airport Management System</h1>
        <p>A comprehensive solution for airport operations</p>
        <div class="buttons">
            <a href="login.php" class="btn btn-primary">Login</a>
            <a href="register.php" class="btn btn-secondary">Register</a>
        </div>
    </div>
</div>

<div class="features-section">
    <div class="container">
        <h2>Our Features</h2>
        <div class="feature-grid">
            <div class="feature-item">
                <i class="fas fa-plane"></i>
                <h3>Flight Management</h3>
                <p>Real-time information on all flights</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-suitcase"></i>
                <h3>Baggage Tracking</h3>
                <p>Track your baggage throughout your journey</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-ticket-alt"></i>
                <h3>Ticket Booking</h3>
                <p>Easy and convenient ticket booking service</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-user-shield"></i>
                <h3>Security Management</h3>
                <p>Advanced security protocols for passenger safety</p>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>

<?php
session_start();
include 'db_connection.php';
include 'includes/header.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'staff':
            header("Location: staff/dashboard.php");
            break;
        case 'traveller':
            header("Location: traveller/dashboard.php");
            break;
        default:
            // Handle unexpected role
            session_destroy();
            header("Location: index.php?error=invalid_role");
            exit();
    }
}
?>

<div class="hero-section">
    <div class="container">
        <h1>Welcome to Airport Management System</h1>
        <p>A comprehensive solution for airport operations</p>
        <div class="buttons">
            <a href="login.php" class="btn btn-primary">Login</a>
            <a href="register.php" class="btn btn-secondary">Register</a>
        </div>
    </div>
</div>

<div class="features-section">
    <div class="container">
        <h2>Our Features</h2>
        <div class="feature-grid">
            <div class="feature-item">
                <i class="fas fa-plane"></i>
                <h3>Flight Management</h3>
                <p>Real-time information on all flights</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-suitcase"></i>
                <h3>Baggage Tracking</h3>
                <p>Track your baggage throughout your journey</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-ticket-alt"></i>
                <h3>Ticket Booking</h3>
                <p>Easy and convenient ticket booking service</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-user-shield"></i>
                <h3>Security Management</h3>
                <p>Advanced security protocols for passenger safety</p>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>