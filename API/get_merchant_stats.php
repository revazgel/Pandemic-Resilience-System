<?php
header("Content-Type: application/json");

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

if (!isset($_GET['merchant_id']) || !is_numeric($_GET['merchant_id'])) {
    echo json_encode(['error' => 'Valid merchant_id is required']);
    exit;
}

$merchant_id = (int)$_GET['merchant_id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get stats
    $stats = [
        'items_count' => 0,
        'purchases_count' => 0,
        'low_stock_count' => 0,
        'low_stock_items' => []
    ];
    
    // Count items in stock
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM stock WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    $stats['items_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count purchases
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchases WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    $stats['purchases_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get low stock items (less than 10 items)
    $stmt = $pdo->prepare("
        SELECT s.stock_id, s.item_id, s.current_quantity, ci.item_name, ci.item_category
        FROM stock s
        JOIN critical_items ci ON s.item_id = ci.item_id
        WHERE s.merchant_id = ? AND s.current_quantity < 10
        ORDER BY s.current_quantity ASC
    ");
    $stmt->execute([$merchant_id]);
    $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['low_stock_count'] = count($low_stock_items);
    $stats['low_stock_items'] = $low_stock_items;
    
    echo json_encode($stats);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>