<?php
/**
 * User Profile & Bank Details API
 * Responsibility: Manages personal business information, branding (logos), and banking configurations.
 */

require_once __DIR__ . '/../config/database.php';

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

// Global error handling configuration
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit(0);
}

/**
 * Requirement: User must be authenticated to manage profile data
 */
$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId || !is_numeric($currentUserId) || $currentUserId <= 0) {
  sendFailureResponse('Authentication required to access profile settings.', 401);
}

$requestedAction = $_GET['action'] ?? 'get_profile';

try {
  $database = getDatabaseConnection();

  // Ensure critical profile tables exist (Self-healing infrastructure)
  initializeProfileInfrastructure($database);

  // Direct the request to the appropriate configuration routine
  switch ($requestedAction) {
    case 'get_profile':
      fetchUserProfileData($database, (int)$currentUserId);
      break;

    case 'upload_logo':
      handleBusinessLogoUpload($database, (int)$currentUserId);
      break;

    case 'update_bank':
      updateBankingInformation($database, (int)$currentUserId);
      break;

    default:
      sendFailureResponse('The requested profile action is not supported.', 405);
  }
}
catch (Exception $exception) {
  error_log("Profile API Core Failure: " . $exception->getMessage());
  sendFailureResponse('An unexpected error occurred while managing your profile settings.', 500);
}

/**
 * Retrieves the complete profile and banking dataset for the user
 */
function fetchUserProfileData($database, $currentUserId)
{
  try {
    $profileSql = "
            SELECT profile.logo_path, bank.account_holder, bank.bank_name, 
                   bank.account_number, bank.ifsc, bank.upi_id, bank.qr_code 
            FROM user_profiles profile 
            LEFT JOIN user_bank_details bank ON profile.user_id = bank.user_id 
            WHERE profile.user_id = ?
        ";

    $profileStmt = $database->prepare($profileSql);
    $profileStmt->execute([$currentUserId]);
    $profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

    // Lazy initialization: Create profile entry if it doesn't exist yet
    if (!$profileData) {
      $database->prepare('INSERT IGNORE INTO user_profiles (user_id) VALUES (?)')->execute([$currentUserId]);
      $profileData = [
        'logo_path' => null,
        'account_holder' => null,
        'bank_name' => null,
        'account_number' => null,
        'ifsc' => null,
        'upi_id' => null,
        'qr_code' => null
      ];
    }

    sendSuccessResponse($profileData, 'Profile details retrieved successfully.');

  }
  catch (PDOException $pdoException) {
    error_log("Profile Retrieval DB Error: " . $pdoException->getMessage());
    sendFailureResponse('Unable to fetch profile details due to a system error.', 500);
  }
}

/**
 * Processes and stores a new business logo for the user
 */
function handleBusinessLogoUpload($database, $currentUserId)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendFailureResponse('Logo upload requires a POST request.', 405);
  }

  if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    sendFailureResponse('No logo file was provided or an upload error occurred.');
  }

  $uploadFile = $_FILES['logo'];
  $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

  if (!isset($allowedMimeTypes[$uploadFile['type']])) {
    sendFailureResponse('Only standard image formats (JPG, PNG, WEBP) are supported for logos.');
  }

  try {
    $logoDirectory = __DIR__ . '/../uploads/logos';
    if (!is_dir($logoDirectory)) {
      @mkdir($logoDirectory, 0775, true);
    }

    $extension = $allowedMimeTypes[$uploadFile['type']];
    $uniqueFileName = 'logo_u' . $currentUserId . '_' . time() . '.' . $extension;
    $physicalPath = $logoDirectory . DIRECTORY_SEPARATOR . $uniqueFileName;

    if (!move_uploaded_file($uploadFile['tmp_name'], $physicalPath)) {
      sendFailureResponse('Failed to save the logo file to disk.', 500);
    }

    $webRelativePath = '../uploads/logos/' . $uniqueFileName;

    // Update both existing record or insert if missing
    $updateSql = "
            INSERT INTO user_profiles (user_id, logo_path) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE logo_path = VALUES(logo_path)
        ";

    $database->prepare($updateSql)->execute([$currentUserId, $webRelativePath]);

    sendSuccessResponse(['logo_path' => $webRelativePath], 'Business logo updated successfully.');

  }
  catch (Exception $exception) {
    error_log("Logo Upload System Failure: " . $exception->getMessage());
    sendFailureResponse('A system error occurred while updating the business logo.', 500);
  }
}

/**
 * Updates banking information and QR codes for payment processing
 */
function updateBankingInformation($database, $currentUserId)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendFailureResponse('Banking updates require a POST request.', 405);
  }

  try {
    $qrCodeWebPath = null;

    // Process optional payment QR code upload
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
      $qrFile = $_FILES['qr_code'];
      $qrDirectory = __DIR__ . '/../uploads/qrcodes';

      if (!is_dir($qrDirectory)) {
        @mkdir($qrDirectory, 0775, true);
      }

      $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
      if (isset($allowedMimes[$qrFile['type']])) {
        $ext = $allowedMimes[$qrFile['type']];
        $uniqueQrName = 'qr_u' . $currentUserId . '_' . time() . '.' . $ext;
        $targetPath = $qrDirectory . DIRECTORY_SEPARATOR . $uniqueQrName;

        if (move_uploaded_file($qrFile['tmp_name'], $targetPath)) {
          $qrCodeWebPath = '../uploads/qrcodes/' . $uniqueQrName;
        }
      }
    }

    $accountHolder = trim($_POST['account_holder'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $ifscCode = trim($_POST['ifsc'] ?? '');
    $upiIdentifier = trim($_POST['upi_id'] ?? '');

    // Construct update logic based on whether a new QR code was provided
    if ($qrCodeWebPath) {
      $upsertSql = "
                INSERT INTO user_bank_details (user_id, account_holder, bank_name, account_number, ifsc, upi_id, qr_code)
                VALUES (?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    account_holder = VALUES(account_holder), bank_name = VALUES(bank_name), 
                    account_number = VALUES(account_number), ifsc = VALUES(ifsc), 
                    upi_id = VALUES(upi_id), qr_code = VALUES(qr_code)
            ";
      $params = [$currentUserId, $accountHolder, $bankName, $accountNumber, $ifscCode, $upiIdentifier, $qrCodeWebPath];
    }
    else {
      $upsertSql = "
                INSERT INTO user_bank_details (user_id, account_holder, bank_name, account_number, ifsc, upi_id)
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    account_holder = VALUES(account_holder), bank_name = VALUES(bank_name), 
                    account_number = VALUES(account_number), ifsc = VALUES(ifsc), 
                    upi_id = VALUES(upi_id)
            ";
      $params = [$currentUserId, $accountHolder, $bankName, $accountNumber, $ifscCode, $upiIdentifier];
    }

    $database->prepare($upsertSql)->execute($params);
    sendSuccessResponse(['qr_code' => $qrCodeWebPath], 'Banking information updated successfully.');

  }
  catch (PDOException $pdoException) {
    error_log("Banking Update DB Error: " . $pdoException->getMessage());
    sendFailureResponse('System error occurred while saving banking details.', 500);
  }
}

/**
 * Ensures required database tables for profiles are present
 */
function initializeProfileInfrastructure($database)
{
  $database->exec("CREATE TABLE IF NOT EXISTS user_profiles (
        user_id INT PRIMARY KEY,
        logo_path VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

  $database->exec("CREATE TABLE IF NOT EXISTS user_bank_details (
        user_id INT PRIMARY KEY,
        account_holder VARCHAR(100) DEFAULT NULL,
        bank_name VARCHAR(100) DEFAULT NULL,
        account_number VARCHAR(50) DEFAULT NULL,
        ifsc VARCHAR(20) DEFAULT NULL,
        upi_id VARCHAR(100) DEFAULT NULL,
        qr_code VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

  // Ensure migration: add qr_code if missing from legacy versions
  try {
    $database->exec("ALTER TABLE user_bank_details ADD COLUMN qr_code VARCHAR(255) DEFAULT NULL AFTER upi_id");
  }
  catch (PDOException $e) { /* Column likely exists */
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
