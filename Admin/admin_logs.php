<?php
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
    
    // Get access logs
    $stmt = $pdo->query("
        SELECT al.*, u.username, u.role
        FROM access_logs al
        JOIN users u ON al.user_id = u.user_id
        ORDER BY al.timestamp DESC
        LIMIT 100
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Logs - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin_styles.css">
</head>
<body>
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
                        <a class="nav-link active" href="admin_logs.php">
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
                            <li><a class="dropdown-item" href="admin_profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../Authentication/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
        <h2><i class="fas fa-clipboard-list"></i> Access Logs</h2>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['username']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($log['role']) {
                                                'Admin' => 'primary',
                                                'Official' => 'warning',
                                                'Merchant' => 'info',
                                                'Citizen' => 'success',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo $log['role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                                    <td><?php echo htmlspecialchars($log['action_details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>