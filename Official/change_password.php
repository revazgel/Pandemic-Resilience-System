<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Official') {
    header("Location: ../Authentication/login.html");
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: official_profile.php");
    exit();
}

// Validate input
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['error_message'] = "All fields are required";
    header("Location: official_profile.php");
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error_message'] = "New passwords do not match";
    header("Location: official_profile.php");
    exit();
}

if (strlen($new_password) < 8) {
    $_SESSION['error_message'] = "New password must be at least 8 characters long";
    header("Location: official_profile.php");
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
    
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify current password using direct comparison since passwords are stored in plain text
    if ($current_password !== $user['password']) {
        throw new Exception("Current password is incorrect");
    }
    
    // Update password (storing in plain text)
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([$new_password, $_SESSION['user_id']]);
    
    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (
            user_id, 
            ip_address, 
            action_type, 
            action_details, 
            entity_type, 
            entity_id
        ) VALUES (?, ?, 'change_password', 'Password changed', 'user', ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR'],
        $_SESSION['user_id']
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = "Password changed successfully!";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Error changing password: " . $e->getMessage();
}

header("Location: official_profile.php");
exit();
?> 