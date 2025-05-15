<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Official') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get item ID
$item_id = $_POST['item_id'] ?? null;
if (!$item_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
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
    
    // Get item name for logging
    $stmt = $pdo->prepare("SELECT item_name FROM critical_items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $item_name = $stmt->fetchColumn();
    
    if (!$item_name) {
        throw new Exception("Item not found");
    }
    
    // Delete the item
    $stmt = $pdo->prepare("DELETE FROM critical_items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    
    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (
            user_id, 
            ip_address, 
            action_type, 
            action_details, 
            entity_type, 
            entity_id
        ) VALUES (?, ?, 'delete_item', ?, 'critical_item', ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR'],
        "Deleted critical item: " . $item_name,
        $item_id
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting item: ' . $e->getMessage()]);
}
?> 