<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Official') {
    header("Location: ../Authentication/login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // DB config
    $host = 'localhost';
    $db = 'CovidSystem';
    $user = 'root';
    $pass = '';
    $port = 3307;
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Insert new item
        $stmt = $pdo->prepare("
            INSERT INTO critical_items (
                item_name, 
                item_description, 
                item_category, 
                unit_of_measure, 
                is_restricted, 
                max_quantity_per_day, 
                max_quantity_per_week
            ) VALUES (?, ?, ?, ?, 1, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['item_name'],
            $_POST['item_description'],
            $_POST['item_category'],
            $_POST['unit_of_measure'],
            $_POST['max_quantity_per_day'],
            $_POST['max_quantity_per_week']
        ]);
        
        // Log the action
        $item_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                user_id, 
                ip_address, 
                action_type, 
                action_details, 
                entity_type, 
                entity_id
            ) VALUES (?, ?, 'add_item', ?, 'critical_item', ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            "Added new critical item: " . $_POST['item_name'],
            $item_id
        ]);
        
        $_SESSION['success_message'] = "Critical item added successfully!";
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error adding item: " . $e->getMessage();
    }
}

// Redirect back to manage items page
header("Location: manage_items.php");
exit(); 