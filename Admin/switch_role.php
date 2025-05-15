<?php
// switch_role.php
require_once '../Authentication/session_check.php';

// Check if user has the Admin role
if ($_SESSION['role'] !== 'Admin' && $_SESSION['original_role'] !== 'Admin') {
    header("Location: ../Authentication/login.html");
    exit();
}

// Check for form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['target_role'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get the target role
$target_role = $_POST['target_role'];

// Validate the target role
$valid_roles = ['Admin', 'Official', 'Merchant', 'Citizen'];
if (!in_array($target_role, $valid_roles)) {
    $_SESSION['error_message'] = "Invalid role specified";
    header("Location: admin_dashboard.php");
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
    
    // Store original role in session for switching back
    if (!isset($_SESSION['original_role'])) {
        $_SESSION['original_role'] = $_SESSION['role'];
    }
    
    // If switching back to Admin
    if ($target_role === 'Admin' && isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin') {
        // Log the role switch back
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                user_id, 
                ip_address, 
                action_type, 
                action_details, 
                entity_type, 
                entity_id
            ) VALUES (?, ?, 'role_switch', ?, 'user', ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            "Switched role from {$_SESSION['role']} back to Admin",
            $_SESSION['user_id']
        ]);
        
        // Restore original role and remove temp flag
        $_SESSION['role'] = 'Admin';
        unset($_SESSION['temp_role']);
        unset($_SESSION['original_role']);
        
        $_SESSION['success_message'] = "Returned to Admin view";
        header("Location: admin_dashboard.php");
        exit();
    }
    // If an admin is switching to another role
    else if ($_SESSION['role'] === 'Admin' || $_SESSION['original_role'] === 'Admin') {
        // Log the role switch
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                user_id, 
                ip_address, 
                action_type, 
                action_details, 
                entity_type, 
                entity_id
            ) VALUES (?, ?, 'role_switch', ?, 'user', ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            "Switched role from {$_SESSION['role']} to {$target_role}",
            $_SESSION['user_id']
        ]);
        
        // Update session role
        $_SESSION['role'] = $target_role;
        $_SESSION['temp_role'] = true;
        
        // Redirect based on new role
        switch ($target_role) {
            case 'Admin':
                $_SESSION['success_message'] = "Now viewing as Admin";
                header("Location: admin_dashboard.php");
                break;
            case 'Official':
                $_SESSION['success_message'] = "Now viewing as Official";
                header("Location: ../Official/dashboard_official.php");
                break;
            case 'Merchant':
                $_SESSION['success_message'] = "Now viewing as Merchant";
                header("Location: ../Merchant/dashboard_merchant.php");
                break;
            case 'Citizen':
                $_SESSION['success_message'] = "Now viewing as Citizen";
                header("Location: ../Citizen/dashboard_citizen.php");
                break;
            default:
                header("Location: admin_dashboard.php");
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: admin_dashboard.php");
}
?>