<?php
/**
 * Admin Authentication Handler
 * Responsibility: Processes administrator login requests and establishes secure sessions.
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Accept credentials from JSON body or standard form POST
    $rawInput = json_decode(file_get_contents('php://input'), true);
    $loginEmail = trim($rawInput['email'] ?? $_POST['email'] ?? '');
    $loginPassword = $rawInput['password'] ?? $_POST['password'] ?? '';

    if (empty($loginEmail) || empty($loginPassword)) {
        sendResponse(false, 'Email and password are both required.');
        exit;
    }

    try {
        $isAuthenticated = false;

        // Priority: Check hardcoded super administrator credentials
        if ($loginEmail === 'admin@gmail.com' && $loginPassword === 'Admin@123') {
            $isAuthenticated = true;
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = $loginEmail;
            $_SESSION['admin_name'] = 'Super Admin';
        }

        // Fallback: Authenticate against the users database table
        if (!$isAuthenticated) {
            $lookupStmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $lookupStmt->execute([$loginEmail]);
            $matchedUser = $lookupStmt->fetch();

            if ($matchedUser && $loginPassword === $matchedUser['password']) {
                $isAuthenticated = true;
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $matchedUser['id'];
                $_SESSION['admin_email'] = $matchedUser['email'];
                $_SESSION['admin_name'] = $matchedUser['name'];
            }
        }

        if ($isAuthenticated) {
            sendResponse(true, 'Login successful.', ['redirect' => '../admin/index.php']);
        }
        else {
            sendResponse(false, 'Invalid email or password. Please try again.');
        }

    }
    catch (PDOException $dbException) {
        error_log("Admin Login DB Error: " . $dbException->getMessage());
        sendResponse(false, 'A system error occurred during authentication. Please try again.');
    }

}
else {
    // GET requests should redirect to the login form
    header("Location: ../front/login.html");
}

/**
 * Sends a standardized JSON response
 */
function sendResponse($isSuccess, $message, $additionalData = [])
{
    $response = ['success' => $isSuccess];
    if ($isSuccess) {
        $response = array_merge($response, $additionalData);
    }
    else {
        $response['error'] = $message;
    }
    echo json_encode($response);
}
?>
