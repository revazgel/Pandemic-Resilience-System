<?php
header("Content-Type: application/json");

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Validate required parameters
    if (!isset($_GET['merchant_id']) || !is_numeric($_GET['merchant_id'])) {
        echo json_encode(['error' => 'Valid merchant_id is required']);
        exit;
    }
    
    $merchant_id = (int)$_GET['merchant_id'];
    
    // Join stock and critical_items tables to get all item information
    $query = "
        SELECT s.stock_id, s.merchant_id, s.item_id, s.current_quantity, 
               ci.item_name, ci.item_description, ci.item_category, ci.unit_of_measure,
               ci.max_quantity_per_day, ci.max_quantity_per_week
        FROM stock s
        JOIN critical_items ci ON s.item_id = ci.item_id
        WHERE s.merchant_id = ? AND s.current_quantity > 0
        ORDER BY ci.item_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$merchant_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($data);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>