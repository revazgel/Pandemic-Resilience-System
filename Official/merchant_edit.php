<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Official') {
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle form submission
        $merchant_id = $_POST['merchant_id'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update merchants table
        $stmt = $pdo->prepare("
            UPDATE merchants 
            SET business_name = ?, business_type = ?, address = ?,
                business_license_number = ?, is_active = ?
            WHERE merchant_id = ?
        ");
        
        $stmt->execute([
            $_POST['business_name'],
            $_POST['business_type'],
            $_POST['address'],
            $_POST['business_license_number'],
            $_POST['is_active'],
            $merchant_id
        ]);
        
        // Get user_id for the merchant
        $stmt = $pdo->prepare("SELECT user_id FROM merchants WHERE merchant_id = ?");
        $stmt->execute([$merchant_id]);
        $user_id = $stmt->fetchColumn();
        
        // Update users table
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, phone = ?
            WHERE user_id = ?
        ");
        
        $stmt->execute([
            $_POST['full_name'],
            $_POST['email'],
            $_POST['phone'],
            $user_id
        ]);
        
        // Update password if provided
        if (!empty($_POST['new_password'])) {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$_POST['new_password'], $user_id]);  // Note: In production, use password_hash()
        }
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                user_id, ip_address, action_type, action_details, 
                entity_type, entity_id
            ) VALUES (?, ?, 'update', 'Updated merchant account', 'merchant', ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            $merchant_id
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "Merchant account updated successfully!";
        header("Location: manage_merchants.php");
        exit();
        
    } else {
        // Display edit form
        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            header("Location: manage_merchants.php");
            exit();
        }
        
        $merchant_id = $_GET['id'];
        
        // Get merchant info
        $stmt = $pdo->prepare("
            SELECT m.merchant_id, m.business_name, m.business_type, m.address, 
                   m.business_license_number, m.is_active,
                   u.full_name, u.email, u.phone, u.username
            FROM merchants m
            JOIN users u ON m.user_id = u.user_id
            WHERE m.merchant_id = ?
        ");
        $stmt->execute([$merchant_id]);
        $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$merchant) {
            $_SESSION['error_message'] = "Merchant not found";
            header("Location: manage_merchants.php");
            exit();
        }
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: manage_merchants.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Merchant - PRS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/official_styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <div class="page-header">
            <h2><i class="fas fa-edit"></i> Edit Merchant</h2>
            <a href="manage_merchants.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Merchants
            </a>
        </div>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="merchant_id" value="<?php echo $merchant['merchant_id']; ?>">
                    
                    <div class="row g-3">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($merchant['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($merchant['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($merchant['phone']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($merchant['username']); ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" name="new_password" class="form-control">
                        </div>
                        
                        <!-- Business Information -->
                        <div class="col-md-6">
                            <label class="form-label">Business Name</label>
                            <input type="text" name="business_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($merchant['business_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Type</label>
                            <select name="business_type" class="form-select" required>
                                <option value="Pharmacy" <?php echo $merchant['business_type'] === 'Pharmacy' ? 'selected' : ''; ?>>Pharmacy</option>
                                <option value="Grocery" <?php echo $merchant['business_type'] === 'Grocery' ? 'selected' : ''; ?>>Grocery</option>
                                <option value="Supermarket" <?php echo $merchant['business_type'] === 'Supermarket' ? 'selected' : ''; ?>>Supermarket</option>
                                <option value="Medical Supply" <?php echo $merchant['business_type'] === 'Medical Supply' ? 'selected' : ''; ?>>Medical Supply</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Business Address</label>
                            <textarea name="address" class="form-control" rows="2" required><?php echo htmlspecialchars($merchant['address']); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">License Number</label>
                            <input type="text" name="business_license_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($merchant['business_license_number']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select" required>
                                <option value="1" <?php echo $merchant['is_active'] ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo !$merchant['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="manage_merchants.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html> 