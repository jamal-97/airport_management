<?php
// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Airport Management System</title>
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="<?php echo APP_URL; ?>/index.php">
                    <i class="fas fa-plane-departure"></i> 
                    <?php echo APP_NAME; ?>
                </a>
                
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ml-auto">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/admin/dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage_users.php">Users</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'manage_flights.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage_flights.php">Flights</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'manage_services.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage_services.php">Services</a>
                                </li>
                            <?php elseif ($_SESSION['role'] === 'staff'): ?>
                                <li class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/staff/dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'flight_operations.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/staff/flight_operations.php">Flights</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'baggage_management.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/staff/baggage_management.php">Baggage</a>
                                </li>
                            <?php elseif ($_SESSION['role'] === 'traveller'): ?>
                                <li class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/traveller/dashboard.php">Dashboard</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'flight_info.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/traveller/flight_info.php">Flights</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'purchase_tickets.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/traveller/purchase_tickets.php">Tickets</a>
                                </li>
                                <li class="nav-item <?php echo ($current_page == 'baggage_tracking.php') ? 'active' : ''; ?>">
                                    <a class="nav-link" href="<?php echo APP_URL; ?>/traveller/baggage_tracking.php">Baggage</a>
                                </li>
                            <?php endif; ?>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-user"></i> <?php echo $_SESSION['first_name']; ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/<?php echo $_SESSION['role']; ?>/profile.php">
                                        <i class="fas fa-id-card"></i> Profile
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </div>
                            </li>
                        <?php else: ?>
                            <li class="nav-item <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?php echo APP_URL; ?>/login.php">Login</a>
                            </li>
                            <li class="nav-item <?php echo ($current_page == 'register.php') ? 'active' : ''; ?>">
                                <a class="nav-link" href="<?php echo APP_URL; ?>/register.php">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="container py-4">
        <?php
        // Display success or error message if exists
        if (isset($_GET['error'])) {
            $error_code = $_GET['error'];
            $error_message = "";
            
            switch ($error_code) {
                case 'unauthorized':
                    $error_message = "You are not authorized to access this page.";
                    break;
                case 'invalid_role':
                    $error_message = "Your account has an invalid role. Please contact support.";
                    break;
                default:
                    $error_message = "An error occurred. Please try again.";
            }
            
            echo '<div class="alert alert-danger">' . $error_message . '</div>';
        }
        
        if (isset($_GET['success'])) {
            $success_code = $_GET['success'];
            $success_message = "";
            
            switch ($success_code) {
                case 'profile_updated':
                    $success_message = "Your profile has been updated successfully.";
                    break;
                case 'password_changed':
                    $success_message = "Your password has been changed successfully.";
                    break;
                default:
                    $success_message = "Operation completed successfully.";
            }
            
            echo '<div class="alert alert-success">' . $success_message . '</div>';
        }
        
        if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
            echo '<div class="alert alert-success">You have been logged out successfully.</div>';
        }
        
        if (isset($_GET['registration']) && $_GET['registration'] == 'success') {
            echo '<div class="alert alert-success">Your account has been created successfully. You can now login.</div>';
        }
?>