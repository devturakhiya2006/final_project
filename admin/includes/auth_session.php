<?php
/**
 * Admin Session Guard
 * Responsibility: Protects administrative pages from unauthorized access.
 */

require_once 'functions.php';

// Redirect unauthenticated users to the public login page
if (!isLoggedIn()) {
    header("Location: ../front/login.html");
    exit();
}
?>
