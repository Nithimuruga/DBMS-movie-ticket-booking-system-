<?php
/**
 * Authentication Check
 * 
 * This file checks if a user is logged in. If not, redirects to login page.
 * Include this file in pages that require user authentication.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: " . SITE_URL . "./index.php");
    exit;
}

// Check for session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Session has expired, destroy the session
    session_unset();
    session_destroy();
    
    // Start a new session to store the redirect message
    session_start();
    $_SESSION['login_message'] = "Your session has expired. Please login again.";
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: " . SITE_URL . "./index.php");
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>
