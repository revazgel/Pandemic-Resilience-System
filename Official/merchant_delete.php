<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Official') {
    header("Location: ../Authentication/login.html");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
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
    
    $merchant_id = $_GET['id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get user_id for the merchant
    $stmt = $pdo->prepare("SELECT user_id FROM merchants WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    $user_id = $stmt->fetchColumn();
    
    if (!$user_id) {
        throw new Exception("Merchant not found");
    }
    
    // Delete related records first
    // Delete from stock
    $stmt = $pdo->prepare("DELETE FROM stock WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    
    // Delete from purchases
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    
    // Delete from merchants
    $stmt = $pdo->prepare("DELETE FROM merchants WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    
    // Delete from users
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (
            user_id, ip_address, action_type, action_details, 
            entity_type, entity_id
        ) VALUES (?, ?, 'delete', 'Deleted merchant account', 'merchant', ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR'],
        $merchant_id
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = "Merchant account deleted successfully!";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Error deleting merchant account: " . $e->getMessage();
}

header("Location: manage_merchants.php");
exit();
?>