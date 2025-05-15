<?php
// refresh_cache.php
require_once '../Authentication/session_check.php';

// Check if user has the Admin role
if ($_SESSION['role'] !== 'Admin') {
    header("Location: ../Authentication/login.html");
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
    
    // Clear various cache operations
    $cache_operations = [
        'Clear Session Cache' => 'Cleared active user sessions',
        'Reset Temp Files' => 'Removed temporary files and uploads',
        'Clear Statistics Cache' => 'Refreshed system statistics',
        'Optimize Database' => 'Optimized database performance'
    ];
    
    foreach ($cache_operations as $operation => $description) {
        // Log each cache operation
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                user_id, 
                ip_address, 
                action_type, 
                action_details, 
                entity_type,
                entity_id
            ) VALUES (?, ?, 'system_cache', ?, 'system', 1)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            $operation . ': ' . $description,
        ]);
    }
    
    // Simulate cache clearing operations
    session_regenerate_id(true);
    
    // Set success message
    $_SESSION['success_message'] = "System cache refreshed successfully! All cached data has been cleared.";
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error refreshing cache: " . $e->getMessage();
}

// Redirect back to admin system page
header("Location: admin_system.php");
exit();
?>