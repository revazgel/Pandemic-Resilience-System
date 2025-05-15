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
    
    // First get the merchant's business type
    $stmt = $pdo->prepare("SELECT business_type FROM merchants WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$merchant) {
        echo json_encode(['error' => 'Merchant not found']);
        exit;
    }
    
    // Map business types to item categories
    $category_map = [
        'Pharmacy' => ['Medical'], 
        'Grocery' => ['Grocery'],
        'Supermarket' => ['Grocery', 'Medical'] // Supermarkets can have both
    ];
    
    // Determine which categories this merchant can manage
    $allowed_categories = isset($category_map[$merchant['business_type']]) ? 
                         $category_map[$merchant['business_type']] : 
                         ['Medical', 'Grocery']; // Fallback to all if type not found
    
    // Build the category filter part of the query
    $category_placeholders = str_repeat('?,', count($allowed_categories) - 1) . '?';
    
    // Join stock and critical_items tables with category filter
    $query = "
        SELECT s.stock_id, s.merchant_id, s.item_id, s.current_quantity, s.last_restock_date, s.last_updated_at,
               ci.item_name, ci.item_description, ci.item_category, ci.unit_of_measure,
               ci.max_quantity_per_day, ci.max_quantity_per_week
        FROM stock s
        JOIN critical_items ci ON s.item_id = ci.item_id
        WHERE s.merchant_id = ?
        AND ci.item_category IN ($category_placeholders)
        ORDER BY ci.item_name
    ";
    
    // Prepare params array - merchant_id followed by allowed categories
    $params = array_merge([$merchant_id], $allowed_categories);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($items);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>