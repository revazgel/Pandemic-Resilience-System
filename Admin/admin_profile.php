<?php
// admin_profile.php
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
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update basic profile info
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, address = ?
                WHERE user_id = ?
            ");
            
            $stmt->execute([
                $_POST['full_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_SESSION['user_id']
            ]);
            
            // Update password if provided
            if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
                // Get current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $current_db_password = $stmt->fetchColumn();
                
                // Verify current password
                if ($_POST['current_password'] === $current_db_password) {
                    // Update password
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$_POST['new_password'], $_SESSION['user_id']]);
                    
                    $_SESSION['password_updated'] = true;
                } else {
                    throw new Exception("Current password is incorrect");
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
                ) VALUES (?, ?, 'profile_update', ?, 'user', ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'],
                "Updated admin profile",
                $_SESSION['user_id']
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Profile updated successfully!";
            if (isset($_SESSION['password_updated'])) {
                $_SESSION['success_message'] .= " Password has been changed.";
                unset($_SESSION['password_updated']);
            }
            
            header("Location: admin_profile.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
    
    // Get current user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        $_SESSION['error_message'] = "User data not found";
        header("Location: admin_dashboard.php");
        exit();
    }
    
    // Get recent activity for this user
    $stmt = $pdo->prepare("
        SELECT * FROM access_logs 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - PRS Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin_styles.css">
    <style>
        .profile-card {
            border-left: 5px solid #007bff;
        }
        .activity-item {
            border-left: 3px solid #28a745;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .profile-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Admin Navbar -->
    <nav class="navbar navbar-expand-lg admin-navbar">
        <div class="container">
            <a class="navbar-brand text-white" href="admin_dashboard.php">
                <i class="fas fa-shield-virus"></i> PRS Admin Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_approvals.php">
                            <i class="fas fa-user-check"></i> Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_system.php">
                            <i class="fas fa-cogs"></i> System
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_logs.php">
                            <i class="fas fa-clipboard-list"></i> Logs
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item active" href="admin_profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../Authentication/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-9">
                    <h2><i class="fas fa-user-shield"></i> Administrator Profile</h2>
                    <p class="mb-0">Manage your admin account settings and view recent activity</p>
                </div>
                <div class="col-md-3 text-end">
                    <div class="profile-icon">
                        <i class="fas fa-user-circle fa-5x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Profile Form -->
            <div class="col-md-8">
                <div class="card profile-card">
                    <div class="card-header">
                        <h5><i class="fas fa-edit"></i> Edit Profile</h5>
                    </div>
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
                                        <div class="form-text">Username cannot be changed</div>
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
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['role']); ?>" disabled>
                                        <div class="form-text">Role cannot be changed</div>
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
                            
                            <hr>
                            <h6><i class="fas fa-lock"></i> Change Password</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" id="current_password">
                                        <div class="form-text">Leave blank to keep current password</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" id="new_password">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="admin_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Account Info & Activity -->
            <div class="col-md-4">
                <!-- Account Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item mb-3">
                            <strong>Account Created:</strong><br>
                            <span class="text-muted"><?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></span>
                        </div>
                        <div class="info-item mb-3">
                            <strong>Last Updated:</strong><br>
                            <span class="text-muted"><?php echo $user_data['updated_at'] ? date('F j, Y', strtotime($user_data['updated_at'])) : 'Never'; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Role:</strong><br>
                            <span class="badge bg-primary"><?php echo $user_data['role']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activity)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <p>No recent activity</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <small class="text-muted float-end">
                                        <?php echo date('M j, H:i', strtotime($activity['timestamp'])); ?>
                                    </small>
                                    <strong><?php echo htmlspecialchars($activity['action_type']); ?></strong><br>
                                    <span class="text-muted small"><?php echo htmlspecialchars($activity['action_details']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="admin_logs.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list"></i> View All Activity
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password validation
        document.getElementById('new_password').addEventListener('input', function() {
            const currentPassword = document.getElementById('current_password');
            if (this.value && !currentPassword.value) {
                currentPassword.required = true;
                currentPassword.focus();
            } else if (!this.value) {
                currentPassword.required = false;
            }
        });
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>