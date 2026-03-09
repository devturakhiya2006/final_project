<?php
/**
 * Admin Session Termination
 * Responsibility: Safely destroys the administrative session and redirects to the public login page.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

session_unset();
session_destroy();

header("Location: ../front/login.html");
exit();
?>
