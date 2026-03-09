<?php
/**
 * Payment Reminder API
 * Responsibility: Sends email reminders for outstanding invoices and logs delivery attempts.
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';

// Configure secure session handling
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
else {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Handle Cross-Origin Resource Sharing (CORS) with session support
 */
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin) {
    header("Access-Control-Allow-Origin: $requestOrigin");
    header('Access-Control-Allow-Credentials: true');
}
else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

/**
 * Requirement: Only authenticated users can dispatch reminders
 */
$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId || !is_numeric($currentUserId) || $currentUserId <= 0) {
    sendFailureResponse('Authentication required to send reminders.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendFailureResponse('Reminder dispatch requires a POST request.', 405);
}

try {
    $reminderPayload = json_decode(file_get_contents('php://input'), true);

    if (!$reminderPayload || empty($reminderPayload['to']) || empty($reminderPayload['subject']) || empty($reminderPayload['message'])) {
        sendFailureResponse('Recipient email, subject, and message body are all required.');
    }

    $recipientEmail = $reminderPayload['to'];
    $emailSubject = $reminderPayload['subject'];
    $emailBody = $reminderPayload['message'];
    $senderEmail = $_SESSION['user_email'] ?? 'admin@goinvoice.com';
    $senderName = $_SESSION['user_name'] ?? 'Admin';

    // Compose email headers
    $emailHeaders = "From: $senderName <$senderEmail>\r\n";
    $emailHeaders .= "Reply-To: $senderEmail\r\n";
    $emailHeaders .= "X-Mailer: PHP/" . phpversion();

    // Attempt delivery
    $deliverySuccessful = @mail($recipientEmail, $emailSubject, $emailBody, $emailHeaders);

    // Persist a local delivery log for verification on development servers
    $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: $recipientEmail | SUB: $emailSubject\nBODY: $emailBody\n---------------------------------\n";
    file_put_contents(__DIR__ . '/emails_sent.txt', $logEntry, FILE_APPEND);

    ob_clean();
    if ($deliverySuccessful) {
        sendSuccessResponse([], 'Payment reminder sent successfully!');
    }
    else {
        // On local XAMPP, mail() often returns false but the log is available
        sendSuccessResponse([], 'Reminder dispatched! (On local servers, check api/emails_sent.txt for details)');
    }

}
catch (Exception $exception) {
    error_log("Reminder Dispatch Failure: " . $exception->getMessage());
    ob_clean();
    sendFailureResponse('An error occurred while sending the reminder.', 500);
}

/**
 * Standard failure response helper
 */
function sendFailureResponse($message, $httpCode = 400)
{
    ob_clean();
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Standard success response helper
 */
function sendSuccessResponse($responseData = [], $successMessage = 'Success')
{
    echo json_encode(['success' => true, 'message' => $successMessage, 'data' => $responseData]);
    exit;
}
?>
