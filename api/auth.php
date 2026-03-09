<?php
/**
 * Authentication API
 * Responsibility: Manages user lifecycle including login, signup, and session verification.
 */

// Secure error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('log_errors', 1);

// Initialize output buffering to prevent header issues
ob_start();

require_once '../config/database.php';

/**
 * Configure secure session parameters
 */
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
 * Handle Cross-Origin Resource Sharing (CORS)
 */
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin) {
    header("Access-Control-Allow-Origin: $requestOrigin");
    header('Vary: Origin');
}
else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json');

// Stop execution for pre-flight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_clean();
    exit(0);
}

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestAction = $_GET['action'] ?? '';

try {
    // Attempt to retrieve database instance
    $database = getDatabaseConnection();

    if (!$database) {
        throw new Exception('Internal connection failure');
    }

    // Direct the request to the appropriate handler
    switch ($requestAction) {
        case 'login':
            processUserLogin($database);
            break;
        case 'signup':
            processUserRegistration($database);
            break;
        case 'logout':
            handleUserLogout();
            break;
        case 'check_session':
            validateCurrentSession($database);
            break;
        case 'send_verification':
            initiateEmailVerification($database);
            break;
        case 'verify_email':
            finalizeEmailVerification($database);
            break;
        default:
            sendFailureResponse('Requested operation is not supported.');
    }
}
catch (Exception $exception) {
    // Record internal error details and provide human-friendly feedback
    error_log("Authentication API Error: " . $exception->getMessage());
    ob_clean();
    sendFailureResponse('A system error occurred while processing your request. Please try again shortly.', 500);
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
    ob_clean();
    echo json_encode(['success' => true, 'message' => $successMessage, 'data' => $responseData]);
    exit;
}

/**
 * Processes a user login attempt
 */
function processUserLogin($database)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendFailureResponse('A POST request is required for this operation.');
    }

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendFailureResponse('The request contained invalid data format.');
    }

    $emailAddress = trim($inputData['email'] ?? '');
    $userPassword = $inputData['password'] ?? '';

    if (empty($emailAddress) || empty($userPassword)) {
        sendFailureResponse('Please provide both your email address and password.');
    }

    try {
        $loginQuery = "SELECT id, name, email, password, business_name, status FROM users WHERE email = ? AND status = 'active'";
        $statement = $database->prepare($loginQuery);
        $statement->execute([$emailAddress]);
        $userRecord = $statement->fetch();

        if ($userRecord && password_verify($userPassword, $userRecord['password'])) {
            // Established verified user session
            $_SESSION['user_id'] = $userRecord['id'];
            $_SESSION['user_name'] = $userRecord['name'];
            $_SESSION['user_email'] = $userRecord['email'];
            $_SESSION['business_name'] = $userRecord['business_name'];

            sendSuccessResponse([
                'user_id' => $userRecord['id'],
                'name' => $userRecord['name'],
                'email' => $userRecord['email'],
                'business_name' => $userRecord['business_name']
            ], 'You have successfully logged in.');
        }
        else {
            sendFailureResponse('The credentials provided do not match our records.', 401);
        }
    }
    catch (PDOException $pdoException) {
        error_log("Login DB Error: " . $pdoException->getMessage());
        sendFailureResponse('Unable to verify credentials due to a system error. Please try again later.', 500);
    }
}

/**
 * Processes a new user registration
 */
function processUserRegistration($database)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendFailureResponse('A POST request is required for this operation.');
    }

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendFailureResponse('The registration data is not in a valid format.');
    }

    // Capture and clean required fields
    $userName = trim($inputData['name'] ?? '');
    $userEmail = trim($inputData['email'] ?? '');
    $userMobile = trim($inputData['mobile'] ?? '');
    $plainPassword = $inputData['password'] ?? '';

    if (empty($userName) || empty($userEmail) || empty($userMobile) || empty($plainPassword)) {
        sendFailureResponse('All primary fields (Name, Email, Mobile, Password) are required.');
    }

    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        sendFailureResponse('Please provide a valid email address format.');
    }

    try {
        // Prevent duplicate registrations
        $checkDuplicate = $database->prepare("SELECT id FROM users WHERE email = ?");
        $checkDuplicate->execute([$userEmail]);
        if ($checkDuplicate->fetch()) {
            sendFailureResponse('An account with this email address already exists.');
        }

        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // Prepare optional fields
        $businessName = trim($inputData['business_name'] ?? '');
        $gstNumber = trim($inputData['gst_number'] ?? '');
        $physicalAddress = trim($inputData['address'] ?? '');
        $cityName = trim($inputData['city'] ?? '');
        $stateName = trim($inputData['state'] ?? '');
        $pincodeValue = trim($inputData['pincode'] ?? '');

        $insertQuery = "
            INSERT INTO users (name, email, mobile, password, business_name, gst_number, address, city, state, pincode) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $registrationStatement = $database->prepare($insertQuery);
        $isRegistered = $registrationStatement->execute([
            $userName, $userEmail, $userMobile, $hashedPassword, $businessName,
            $gstNumber, $physicalAddress, $cityName, $stateName, $pincodeValue
        ]);

        if ($isRegistered) {
            $newUserId = $database->lastInsertId();

            // Set up standardized default user preferences
            $defaultSettings = [
                ['invoice_prefix', 'INV'],
                ['invoice_start_number', '1001'],
                ['currency', 'INR'],
                ['timezone', 'Asia/Kolkata']
            ];

            $settingsStatement = $database->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
            foreach ($defaultSettings as $setting) {
                $settingsStatement->execute([$newUserId, $setting[0], $setting[1]]);
            }

            sendSuccessResponse(['user_id' => $newUserId], 'Welcome! Your account has been created successfully.');
        }
        else {
            sendFailureResponse('We encountered an issue during registration. Please try again.');
        }
    }
    catch (PDOException $pdoException) {
        error_log("Registration DB Error: " . $pdoException->getMessage());
        sendFailureResponse('System error during registration. Please contact support if the issue persists.', 500);
    }
}

/**
 * Handles user logout
 */
function handleUserLogout()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    ob_clean();
    sendSuccessResponse([], 'You have been logged out successfully.');
}

/**
 * Validates the current user session
 */
function validateCurrentSession($database)
{
    if (!isset($_SESSION['user_id'])) {
        sendFailureResponse('No active session found.', 401);
    }

    try {
        // Verify that the user account masih active and exists
        $validateQuery = "SELECT id, name, email, business_name FROM users WHERE id = ? AND status = 'active'";
        $statement = $database->prepare($validateQuery);
        $statement->execute([$_SESSION['user_id']]);
        $userRecord = $statement->fetch();

        if (!$userRecord) {
            handleUserLogout();
            sendFailureResponse('Your session is no longer valid.', 401);
        }

        sendSuccessResponse([
            'user_id' => $userRecord['id'],
            'name' => $userRecord['name'],
            'email' => $userRecord['email'],
            'business_name' => $userRecord['business_name']
        ], 'Session is active.');
    }
    catch (PDOException $pdoException) {
        error_log("Session Validation DB Error: " . $pdoException->getMessage());
        sendFailureResponse('Unable to verify session due to a system error.', 500);
    }
}

/**
 * Initiates the email verification process
 */
function initiateEmailVerification($database)
{
    if (!isset($_SESSION['user_id'])) {
        sendFailureResponse('Authenticaton required to send verification email.', 401);
    }

    try {
        $userId = $_SESSION['user_id'];
        $verificationToken = bin2hex(random_bytes(16));

        $updateQuery = "UPDATE users SET verification_token = ? WHERE id = ?";
        $database->prepare($updateQuery)->execute([$verificationToken, $userId]);

        // Logging the simulated verification link for administrative visibility
        error_log("Verification link for user ID $userId: http://localhost/copy/project%201/api/auth.php?action=verify_email&token=$verificationToken");

        sendSuccessResponse([], 'A verification message has been sent to your registered email address.');
    }
    catch (Exception $exception) {
        error_log("Email Verification Initiation Error: " . $exception->getMessage());
        sendFailureResponse('Something went wrong while initiating email verification.');
    }
}

/**
 * Finalizes the email verification process
 */
function finalizeEmailVerification($database)
{
    $tokenFromUrl = $_GET['token'] ?? '';
    if (empty($tokenFromUrl)) {
        die("<h2 style='color: #e74c3c;'>Invalid Link</h2><p>This verification link appears to be malformed.</p>");
    }

    try {
        $findUserQuery = "SELECT id FROM users WHERE verification_token = ?";
        $statement = $database->prepare($findUserQuery);
        $statement->execute([$tokenFromUrl]);
        $userRecord = $statement->fetch();

        if ($userRecord) {
            $activateQuery = "UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?";
            $database->prepare($activateQuery)->execute([$userRecord['id']]);

            echo "<div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px;'>";
            echo "<h1 style='color: #27ae60;'>Email Successfully Verified!</h1>";
            echo "<p>Thank you for verifying your email. You can now use all the features of your account.</p>";
            echo "<script>setTimeout(() => { if(window.opener) { window.close(); } }, 5000);</script>";
            echo "</div>";
        }
        else {
            die("<h2 style='color: #e74c3c;'>Expired Token</h2><p>This verification link is invalid or has already been used.</p>");
        }
    }
    catch (PDOException $pdoException) {
        error_log("Finalize Verification DB Error: " . $pdoException->getMessage());
        die("<h2>Service Unavailable</h2><p>A system error occurred during verification. Please try again later.</p>");
    }
}
