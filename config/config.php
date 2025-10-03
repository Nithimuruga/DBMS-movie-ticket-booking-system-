<?php
/**
 * Database Configuration and Constants
 * 
 * This file contains the database connection settings and
 * application constants used throughout the movie booking system.
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'movie_booking_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Constants
define('SITE_NAME', 'CinemaTime');
define('SITE_URL', 'http://localhost/dbmsMovie');
define('ADMIN_EMAIL', 'admin@cinematime.com');

// Session timeout in seconds (2 hours)
define('SESSION_TIMEOUT', 7200);

// File upload paths
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/movie_posters/');
define('UPLOAD_URL', SITE_URL . '/uploads/movie_posters/');

// Maximum file upload size in bytes (2MB)
define('MAX_FILE_SIZE', 2097152);

// Allowed file types for movie posters
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Ticket constants
define('TICKET_PREFIX', 'CT');

// Seat status constants
define('SEAT_AVAILABLE', 0);
define('SEAT_BOOKED', 1);
define('SEAT_RESERVED', 2);

// User roles
define('ROLE_USER', 0);
define('ROLE_ADMIN', 1);

// Database connection using PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('UTC');
?>
