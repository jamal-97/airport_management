<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'airport_management');

// Application configuration
define('APP_NAME', 'Integrated Airport Management System');
define('APP_URL', 'http://localhost/airport_system');
define('APP_VERSION', '1.0.0');

// Email configuration (for password reset and notifications)
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'notifications@airport-system.com');
define('MAIL_PASSWORD', 'mail_password');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'no-reply@airport-system.com');
define('MAIL_FROM_NAME', APP_NAME);

// File uploads
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'airport_session');

// Security
define('HASH_COST', 12); // for password_hash()