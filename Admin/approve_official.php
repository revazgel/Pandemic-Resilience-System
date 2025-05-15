<?php
// approve_official.php - Combined and improved version
require_once '../Authentication/session_check.php';

// Check if user has the Admin role
if ($_SESSION['role'] !== 'Admin') {
    header("Location: ../Authentication/login.html");
    exit();
}

// Check for required parameters
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['action'])) {
    $_SESSION['error_message'] = "Invalid request parameters";
    header("Location: admin_dashboard.php");
    exit();
}

$approval_id = (int)$_GET['id'];
$action = $_GET['action'];

// Validate action
if ($action !== 'approve' && $action !== 'reject') {
    $_SESSION['error_message'] = "Invalid action specified";
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
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get approval details with user information
    $stmt = $pdo->prepare("
        SELECT oa.*, u.email, u.full_name
        FROM official_approvals oa
        JOIN users u ON oa.user_id = u.user_id
        WHERE oa.approval_id = ?
    ");
    $stmt->execute([$approval_id]);
    $approval = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$approval) {
        throw new Exception("Approval request not found");
    }
    
    if ($approval['status'] !== 'Pending') {
        throw new Exception("This approval request has already been processed");
    }
    
    // Update approval status
    $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
    $stmt = $pdo->prepare("
        UPDATE official_approvals 
        SET status = ?, processed_by = ?, processed_at = NOW() 
        WHERE approval_id = ?
    ");
    $stmt->execute([$new_status, $_SESSION['user_id'], $approval_id]);
    
    // If approved, update user role and create official record
    if ($action === 'approve') {
        // Update user role
        $stmt = $pdo->prepare("UPDATE users SET role = 'Official' WHERE user_id = ?");
        $stmt->execute([$approval['user_id']]);
        
        // Create or update official record
        // First check if official record already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM government_officials WHERE user_id = ?");
        $stmt->execute([$approval['user_id']]);
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            // Create new official record
            $stmt = $pdo->prepare("
                INSERT INTO government_officials 
                (user_id, department, role, badge_number) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $approval['user_id'],
                $approval['department'],
                $approval['role'],
                $approval['badge_number']
            ]);
        } else {
            // Update existing official record
            $stmt = $pdo->prepare("
                UPDATE government_officials 
                SET department = ?, role = ?, badge_number = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $approval['department'],
                $approval['role'],
                $approval['badge_number'],
                $approval['user_id']
            ]);
        }
    }
    
    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (
            user_id, 
            ip_address, 
            action_type, 
            action_details, 
            entity_type, 
            entity_id
        ) VALUES (?, ?, ?, ?, 'official_approval', ?)
    ");
    
    $action_details = sprintf(
        "%s official registration request for %s (%s)", 
        ucfirst($action . 'd'),
        $approval['full_name'],
        $approval['department']
    );
    
    $stmt->execute([
        $_SESSION['user_id'],
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        "approval_$action",
        $action_details,
        $approval_id
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Set success message
    $message = sprintf(
        "%s registration %s successfully",
        $approval['full_name'],
        $action === 'approve' ? 'approved' : 'rejected'
    );
    $_SESSION['success_message'] = $message;
    
    // Check if request came from dashboard or approvals page
    $redirect_page = isset($_GET['from']) && $_GET['from'] === 'approvals' 
        ? 'admin_approvals.php' 
        : 'admin_dashboard.php';
    
    // Redirect without JavaScript for better compatibility
    header("Location: $redirect_page");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log("Approval process error: " . $e->getMessage());
    
    // Set error message
    $_SESSION['error_message'] = "Error processing approval: " . $e->getMessage();
    
    // Redirect back with error
    $redirect_page = isset($_GET['from']) && $_GET['from'] === 'approvals' 
        ? 'admin_approvals.php' 
        : 'admin_dashboard.php';
    
    header("Location: $redirect_page");
    exit();
}
?>