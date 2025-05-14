
<?php
session_start();
require_once 'db_connection.php';

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

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Prepare a select statement
        $query = "SELECT id, email, password, role, first_name, last_name FROM users WHERE email = ?";
        $result = $db->executeQuery($query, "s", [$email]);
        
        if ($result && count($result) == 1) {
            $user = $result[0];
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, start a new session
                session_regenerate_id(true);
                
                // Store data in session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['logged_in'] = true;
                
                // Set remember me cookie if requested
                if ($remember) {
                    $selector = bin2hex(random_bytes(8));
                    $authenticator = random_bytes(32);
                    
                    $expires = time() + 60 * 60 * 24 * 30; // 30 days
                    setcookie(
                        'remember_me',
                        $selector . ':' . bin2hex($authenticator),
                        $expires,
                        '/',
                        '',
                        true, // Secure
                        true  // HttpOnly
                    );
                    
                    // Store token in the database
                    $token_hash = hash('sha256', $authenticator);
                    $expiry = date('Y-m-d H:i:s', $expires);
                    
                    $query = "INSERT INTO auth_tokens (user_id, selector, token, expires_at) VALUES (?, ?, ?, ?)";
                    $db->executeQuery($query, "isss", [$user['id'], $selector, $token_hash, $expiry]);
                }
                
                // Log successful login
                $query = "INSERT INTO login_logs (user_id, login_time, ip_address, user_agent) VALUES (?, NOW(), ?, ?)";
                $db->executeQuery($query, "iss", [
                    $user['id'], 
                    $_SERVER['REMOTE_ADDR'], 
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                // Redirect based on user role
                switch ($user['role']) {
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
                exit();
            } else {
                // Password is not valid
                $error = "Invalid email or password.";
                
                // Log failed login attempt
                $query = "INSERT INTO login_attempts (email, attempt_time, ip_address, status) VALUES (?, NOW(), ?, 'failed')";
                $db->executeQuery($query, "ss", [$email, $_SERVER['REMOTE_ADDR']]);
            }
        } else {
            // No user found with that email
            $error = "Invalid email or password.";
            
            // Log failed login attempt
            $query = "INSERT INTO login_attempts (email, attempt_time, ip_address, status) VALUES (?, NOW(), ?, 'failed')";
            $db->executeQuery($query, "ss", [$email, $_SERVER['REMOTE_ADDR']]);
        }
    }
}

include 'includes/header.php';
?>

<div class="login-container">
    <div class="login-form-container">
        <h2>Login to Airport Management System</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" name="remember" id="remember" class="custom-control-input">
                    <label class="custom-control-label" for="remember">Remember me</label>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </div>
            
            <div class="text-center">
                <a href="forgot_password.php">Forgot Password?</a>
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>