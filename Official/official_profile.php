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
    
    // Get official info
    $stmt = $pdo->prepare("
        SELECT u.*, go.* 
        FROM users u
        JOIN government_officials go ON u.user_id = go.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $official = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$official) {
        $_SESSION['error_message'] = "Official profile not found";
        header("Location: dashboard_official.php");
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Profile - PRS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/official_styles.css">
</head>
<body>
<nav class="navbar navbar-expand-lg" style="background: linear-gradient(90deg, #8e2de2 0%, #ff6a00 100%);">
<?php include 'navbar.php'; ?>
</nav>
    
    <div class="container py-4">
        <div class="page-header">
            <h2><i class="fas fa-user-circle"></i> Official Profile</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i> 
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="row fade-in">
            <!-- Personal Information -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-id-card"></i> Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted">Full Name</label>
                            <p class="mb-0"><?php echo htmlspecialchars($official['full_name']); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Email</label>
                            <p class="mb-0"><?php echo htmlspecialchars($official['email']); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Department</label>
                            <p class="mb-0"><?php echo htmlspecialchars($official['department']); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Role</label>
                            <p class="mb-0"><?php echo htmlspecialchars($official['role']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-shield"></i> Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted">Username</label>
                            <p class="mb-0"><?php echo htmlspecialchars($official['username']); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Account Created</label>
                            <p class="mb-0"><?php echo date('F j, Y', strtotime($official['created_at'])); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Last Login</label>
                            <p class="mb-0"><?php echo date('F j, Y g:i A', strtotime($official['last_login'] ?? 'Never')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="change_password.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 