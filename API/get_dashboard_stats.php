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
    
    // Get stats
    $stats = [
        'users_count' => 0,
        'vaccinations_count' => 0,
        'merchants_count' => 0,
        'purchases_count' => 0
    ];
    
    // Count Users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Users");
    $stats['users_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count Vaccination Records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vaccination_records");
    $stats['vaccinations_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count Merchants
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM merchants");
    $stats['merchants_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count Purchases
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchases");
    $stats['purchases_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode($stats);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>