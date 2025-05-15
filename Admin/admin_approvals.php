<?php
// admin_approvals.php - Basic version if missing
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
    
    // Get all pending approvals
    $stmt = $pdo->query("
        SELECT oa.*, u.full_name, u.email, u.username, u.created_at
        FROM official_approvals oa
        JOIN users u ON oa.user_id = u.user_id
        WHERE oa.status = 'Pending'
        ORDER BY oa.created_at DESC
    ");
    $pending_approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Approvals - PRS Admin</title>
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
                        <a class="nav-link active" href="admin_approvals.php">
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
        <h2><i class="fas fa-user-check"></i> Official Approvals</h2>
        
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
        
        <div class="card">
            <div class="card-body">
                <?php if (empty($pending_approvals)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                        <p class="text-muted">No pending approvals at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Badge Number</th>
                                    <th>Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_approvals as $approval): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($approval['full_name']); ?>
                                            <div class="small text-muted">@<?php echo htmlspecialchars($approval['username']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($approval['department']); ?></td>
                                        <td><?php echo htmlspecialchars($approval['role']); ?></td>
                                        <td><?php echo htmlspecialchars($approval['badge_number']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($approval['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="approve_official.php?id=<?php echo $approval['approval_id']; ?>&action=approve&from=approvals" 
                                                   class="btn btn-success">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="approve_official.php?id=<?php echo $approval['approval_id']; ?>&action=reject&from=approvals" 
                                                   class="btn btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>