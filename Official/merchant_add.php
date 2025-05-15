<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Official') {
    header("Location: ../Authentication/login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_merchants.php");
    exit();
}

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    if ($stmt->fetch()) {
        throw new Exception("Username already exists");
    }
    
    // Insert into users table
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, phone, username, password, role)
        VALUES (?, ?, ?, ?, ?, 'Merchant')
    ");
    
    $stmt->execute([
        $_POST['full_name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['username'],
        $_POST['password']  // Note: In production, use password_hash()
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    // Insert into merchants table
    $stmt = $pdo->prepare("
        INSERT INTO merchants (user_id, business_name, business_type, address, 
                             business_license_number, is_active)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $_POST['business_name'],
        $_POST['business_type'],
        $_POST['address'],
        $_POST['business_license_number'],
        $_POST['is_active']
    ]);
    
    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (
            user_id, ip_address, action_type, action_details, 
            entity_type, entity_id
        ) VALUES (?, ?, 'create', 'Created new merchant account', 'merchant', ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR'],
        $pdo->lastInsertId()
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = "Merchant account created successfully!";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Error creating merchant account: " . $e->getMessage();
}

header("Location: manage_merchants.php");
exit();
?>