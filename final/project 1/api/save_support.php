<?php
/**
 * Support Inquiry API
 * Responsibility: Securely captures and persists support requests and partner inquiries.
 */

require_once '../config/database.php';

// Global error handling configuration
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

/**
 * Handle Cross-Origin Resource Sharing (CORS) - Support inquiries can be public
 */
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin) {
    header("Access-Control-Allow-Origin: $requestOrigin");
}
else {
    header('Access-Control-Allow-Origin: *');
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== 'POST') {
    sendFailureResponse('The support inquiry requires a POST submission.', 405);
}

try {
    $database = getDatabaseConnection();
    processSupportInquiry($database);
}
catch (Exception $exception) {
    error_log("Support Inquiry API Core Failure: " . $exception->getMessage());
    sendFailureResponse('An unexpected error occurred while submitting your request. Please try again later.', 500);
}

/**
 * Processes the incoming inquiry payload and persists it to the database
 */
function processSupportInquiry($database)
{
    // Collect inquiry metadata
    $inquirySource = trim($_POST['source'] ?? '');

    // Core contact information
    $inquirerName = trim($_POST['name'] ?? '');
    $inquirerEmail = trim($_POST['email'] ?? '');
    $inquirerPhone = trim($_POST['phone'] ?? '');

    // Optional business/partner context
    $professionalTitle = $_POST['profession'] ?? null;
    $targetCity = $_POST['city'] ?? null;
    $inquirySubject = $_POST['subject'] ?? null;
    $inquiryMessage = trim($_POST['message'] ?? '');

    // Validate mandatory fields
    $requiredFields = ['name' => $inquirerName, 'email' => $inquirerEmail, 'source' => $inquirySource];
    $missingFields = [];

    foreach ($requiredFields as $label => $value) {
        if (empty($value)) {
            $missingFields[] = $label;
        }
    }

    if (!empty($missingFields)) {
        sendFailureResponse('Please provide the following required information: ' . implode(', ', $missingFields));
    }

    try {
        $insertSql = "
            INSERT INTO support_inquiries (name, email, phone, profession, city, subject, message, source) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $inquiryStmt = $database->prepare($insertSql);
        $isPersisted = $inquiryStmt->execute([
            $inquirerName,
            $inquirerEmail,
            $inquirerPhone,
            $professionalTitle,
            $targetCity,
            $inquirySubject,
            $inquirerMessage,
            $inquirySource
        ]);

        if ($isPersisted) {
            sendSuccessResponse([], 'Your inquiry has been submitted successfully. Our team will contact you shortly.');
        }
        else {
            sendFailureResponse('We encountered an issue while saving your inquiry. Please try again.');
        }

    }
    catch (PDOException $pdoException) {
        error_log("Support Inquiry DB Save Failure: " . $pdoException->getMessage());
        sendFailureResponse('A system error occurred while processing your inquiry.', 500);
    }
}

/**
 * Returns the active PDO database instance
 */
function getDatabaseConnection()
{
    global $pdo;
    return $pdo;
}

/**
 * Standard failure response helper
 */
function sendFailureResponse($message, $httpCode = 400)
{
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
