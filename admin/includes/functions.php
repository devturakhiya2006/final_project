<?php
/**
 * Admin Utility Functions
 * Responsibility: Provides shared helper functions for the administrative panel.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitizes user input to prevent XSS and injection attacks
 */
function sanitize($rawInput)
{
    return htmlspecialchars(strip_tags(trim($rawInput)));
}

/**
 * Performs a safe redirect to the specified URL
 */
function redirect($targetUrl)
{
    header("Location: $targetUrl");
    exit();
}

/**
 * Checks whether the current session belongs to an authenticated administrator
 */
function isLoggedIn()
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Enforces authentication by redirecting unauthenticated sessions to the login page
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}
?>
