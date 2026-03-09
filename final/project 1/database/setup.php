<?php
/**
 * Database Setup Script for GoInvoice
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db   = 'goinvoice_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    // Connect without database first
    $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected to MySQL server successfully.\n";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET $charset COLLATE {$charset}_unicode_ci");
    echo "Database '$db' created or already exists.\n";
    
    // Now reconnect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected to database '$db'.\n";
    
    // Read schema file
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        die("Schema file not found: $schemaFile\n");
    }
    
    $schema = file_get_contents($schemaFile);
    
    // Remove SQL comments
    $schema = preg_replace('/--.*$/m', '', $schema);
    $schema = preg_replace('!/\*.*?\*/!s', '', $schema);
    
    // Split by semicolon
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($s) { return !empty($s); }
    );
    
    // Execute statements
    $count = 0;
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $count++;
        } catch (PDOException $e) {
            // Only show errors that aren't "already exists"
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), "doesn't exist") === false) {
                echo "Error executing statement: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
            }
        }
    }
    
    echo "Database schema executed successfully!\n";
    echo "Executed $count SQL statements.\n\n";
    
    // Insert default test user if no users exist
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            echo "Creating default test user...\n";
            $password = password_hash('test@123', PASSWORD_BCRYPT);
            $pdo->prepare(
                "INSERT INTO users (name, email, mobile, password, business_name, status) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                'Test User',
                'test@example.com',
                '9999999999',
                $password,
                'Test Business',
                'active'
            ]);
            echo "✓ Default test user created (email: test@example.com, password: test@123)\n\n";
        }
    } catch (Exception $e) {
        echo "Warning: Could not create test user: " . $e->getMessage() . "\n\n";
    }
    
    // Validate tables
    validateDatabaseStructure($pdo);
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage() . "\n");
}

function validateDatabaseStructure($pdo) {
    $requiredTables = [
        'users', 'customers', 'products', 'sales_invoices',
        'sales_invoice_items', 'purchase_invoices',
        'purchase_invoice_items', 'payments', 'user_settings'
    ];
    
    echo "Validating database structure...\n";
    
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' missing\n";
        }
    }
}
?>
