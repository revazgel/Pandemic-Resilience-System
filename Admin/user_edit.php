<?php
// user_edit.php
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
    
    // Get user ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['error_message'] = "Invalid user ID";
        header("Location: admin_users.php");
        exit();
    }
    
    $user_id = (int)$_GET['id'];
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update user data
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, address = ?, role = ?
                WHERE user_id = ?
            ");
            
            $stmt->execute([
                $_POST['full_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['role'],
                $user_id
            ]);
            
            // Update password if provided
            if (!empty($_POST['new_password'])) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$_POST['new_password'], $user_id]); // Note: In production, use password_hash()
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
                ) VALUES (?, ?, 'update_user', ?, 'user', ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'],
                "Updated user profile for " . $_POST['full_name'],
                $user_id
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "User updated successfully!";
            header("Location: admin_users.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            throw $e;
        }
    }
    
    // Get user data for display
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        $_SESSION['error_message'] = "User not found";
        header("Location: admin_users.php");
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: admin_users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - PRS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin_styles.css">
</head>
<body>
    <!-- Admin Navbar -->
    <nav class="navbar navbar-expand-lg admin-navbar">
        <div class="container">
            <a class="navbar-brand text-white" href="admin_dashboard.php">
                <i class="fas fa-shield-virus"></i> PRS Admin Portal
            </a>
            <div class="navbar-nav ms-auto">
                <a href="admin_users.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
        <h2><i class="fas fa-edit"></i> Edit User</h2>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" name="role" required>
                                    <option value="Admin" <?php echo $user_data['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="Official" <?php echo $user_data['role'] === 'Official' ? 'selected' : ''; ?>>Official</option>
                                    <option value="Merchant" <?php echo $user_data['role'] === 'Merchant' ? 'selected' : ''; ?>>Merchant</option>
                                    <option value="Citizen" <?php echo $user_data['role'] === 'Citizen' ? 'selected' : ''; ?>>Citizen</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prs_id" class="form-label">PRS ID</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['prs_id']); ?>" disabled>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="new_password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="admin_users.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>