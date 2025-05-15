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
    
    // Get parameters
    $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    $merchant_type = isset($_GET['merchant_type']) ? trim($_GET['merchant_type']) : '';
    
    // Base query joining stock, critical_items, and merchants
    $query = "
        SELECT s.stock_id, s.merchant_id, s.item_id, s.current_quantity,
               ci.item_name, ci.item_category, ci.unit_of_measure,
               m.business_name, m.business_type, m.address, m.city, m.postal_code
        FROM stock s
        JOIN critical_items ci ON s.item_id = ci.item_id
        JOIN merchants m ON s.merchant_id = m.merchant_id
        WHERE s.current_quantity > 0
        AND m.is_active = 1
    ";
    
    $params = [];
    
    // Add item filter if specified
    if ($item_id > 0) {
        $query .= " AND s.item_id = ?";
        $params[] = $item_id;
    }
    
    // Add merchant type filter if specified
    if (!empty($merchant_type)) {
        $query .= " AND LOWER(m.business_type) = LOWER(?)";
        $params[] = $merchant_type;
    }
    
    $query .= " ORDER BY m.business_name, ci.item_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>