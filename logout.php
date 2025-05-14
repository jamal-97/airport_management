<?php
session_start();

// Check if the user is logged in
if(isset($_SESSION['user_id'])) {
    // Log the logout time
    require_once 'db_connection.php';
    
    $query = "INSERT INTO logout_logs (user_id, logout_time, ip_address) VALUES (?, NOW(), ?)";
    $db->executeQuery($query, "is", [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    
    // Delete remember me token if it exists
    if(isset($_COOKIE['remember_me'])) {
        list($selector) = explode(':', $_COOKIE['remember_me']);
        
        $query = "DELETE FROM auth_tokens WHERE user_id = ? AND selector = ?";
        $db->executeQuery($query, "is", [$_SESSION['user_id'], $selector]);
        
        // Expire the cookie
        setcookie('remember_me', '', time() - 3600, '/');
    }
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("location: index.php?logout=success");
exit;

?>