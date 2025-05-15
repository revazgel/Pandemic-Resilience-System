<?php
header("Content-Type: application/json");
session_start();

// Check if user is logged in and is a merchant
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Merchant') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

// Validate required parameters
if (!isset($_GET['purchase_id']) || !is_numeric($_GET['purchase_id'])) {
    echo json_encode(['error' => 'Valid purchase_id is required']);
    exit;
}

$purchase_id = (int)$_GET['purchase_id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get merchant ID for the logged-in user
    $stmt = $pdo->prepare("SELECT merchant_id FROM merchants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$merchant) {
        echo json_encode(['error' => 'Merchant profile not found']);
        exit;
    }
    
    // Get purchase details
    $stmt = $pdo->prepare("
        SELECT p.purchase_id, p.user_id, p.merchant_id, p.item_id, p.quantity, 
               p.purchase_date, p.verified_by,
               u.full_name as customer_name, u.prs_id, u.dob,
               ci.item_name, ci.item_description, ci.item_category, ci.unit_of_measure
        FROM purchases p
        JOIN Users u ON p.user_id = u.user_id
        JOIN critical_items ci ON p.item_id = ci.item_id
        WHERE p.purchase_id = ? AND p.merchant_id = ?
    ");
    $stmt->execute([$purchase_id, $merchant['merchant_id']]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$purchase) {
        echo json_encode(['error' => 'Purchase not found or not authorized to view']);
        exit;
    }
    
    // Check other purchases by this customer for this item (for context)
    $stmt = $pdo->prepare("
        SELECT purchase_id, quantity, purchase_date
        FROM purchases
        WHERE user_id = ? AND item_id = ? AND purchase_id != ?
        ORDER BY purchase_date DESC
        LIMIT 5
    ");
    $stmt->execute([$purchase['user_id'], $purchase['item_id'], $purchase_id]);
    $recent_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add recent purchases to the response
    $purchase['recent_purchases'] = $recent_purchases;
    
    // Calculate remaining purchase limits for the customer
    // 1. Get daily/weekly limits for the item
    $stmt = $pdo->prepare("
        SELECT max_quantity_per_day, max_quantity_per_week
        FROM critical_items
        WHERE item_id = ?
    ");
    $stmt->execute([$purchase['item_id']]);
    $limits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Calculate current usage
    $today_start = date('Y-m-d 00:00:00');
    $week_start = date('Y-m-d 00:00:00', strtotime('this week Monday'));
    
    // Daily usage
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as total
        FROM purchases
        WHERE user_id = ? AND item_id = ? AND purchase_date >= ?
    ");
    $stmt->execute([$purchase['user_id'], $purchase['item_id'], $today_start]);
    $daily_usage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Weekly usage
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as total
        FROM purchases
        WHERE user_id = ? AND item_id = ? AND purchase_date >= ?
    ");
    $stmt->execute([$purchase['user_id'], $purchase['item_id'], $week_start]);
    $weekly_usage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add limit information to the response
    $purchase['limits'] = [
        'daily_limit' => $limits['max_quantity_per_day'],
        'weekly_limit' => $limits['max_quantity_per_week'],
        'daily_used' => (int)$daily_usage['total'],
        'weekly_used' => (int)$weekly_usage['total'],
        'daily_remaining' => max(0, $limits['max_quantity_per_day'] - (int)$daily_usage['total']),
        'weekly_remaining' => max(0, $limits['max_quantity_per_week'] - (int)$weekly_usage['total'])
    ];
    
    echo json_encode($purchase);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>