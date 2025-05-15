<?php
session_start();

// Log the logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    // DB config
    $host = 'localhost';
    $db = 'CovidSystem';
    $user = 'root';
    $pass = '';
    $port = 3307;

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                user_id, 
                ip_address, 
                action_type, 
                action_details, 
                entity_type, 
                entity_id
            ) VALUES (?, ?, 'logout', 'User logged out', 'user', ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SESSION['user_id']
        ]);
        
    } catch (PDOException $e) {
        // Log error but continue with logout
        error_log("Error logging logout: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../Authentication/login.html");
exit();
?>