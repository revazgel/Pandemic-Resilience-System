<?php
require_once '../Authentication/session_check.php';

// Check for citizen role
if ($_SESSION['role'] !== 'Citizen') {
    header("Location: ../Authentication/login.html");
    exit();
}

// DB connection
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get form data
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM Users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stored_password = $stmt->fetchColumn();
    
    if ($stored_password === $current_password) { // In production, use password_verify()
        // Prepare update query
        $update_fields = [];
        $params = [];
        
        if (!empty($email)) {
            $update_fields[] = "email = ?";
            $params[] = $email;
        }
        
        if (!empty($phone)) {
            $update_fields[] = "phone = ?";
            $params[] = $phone;
        }
        
        if (!empty($address)) {
            $update_fields[] = "address = ?";
            $params[] = $address;
        }
        
        if (!empty($new_password)) {
            $update_fields[] = "password = ?";
            $params[] = $new_password; // In production, use password_hash()
        }
        
        if (!empty($update_fields)) {
            $sql = "UPDATE Users SET " . implode(", ", $update_fields) . " WHERE user_id = ?";
            $params[] = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['success_message'] = "Profile updated successfully.";
        }
    } else {
        $_SESSION['error_message'] = "Incorrect current password. Please try again.";
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

header("Location: citizen_profile.php");
exit();
?>