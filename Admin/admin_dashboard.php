<?php
// admin_dashboard.php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
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

// Initialize stats variables
$admin_count = 0;
$officials_count = 0;
$officials_pending = 0;
$merchants_count = 0;
$citizens_count = 0;
$recent_activity = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get system-wide stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'");
    $admin_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Official'");
    $officials_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM official_approvals WHERE status = 'Pending'");
    $officials_pending = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Merchant'");
    $merchants_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Citizen'");
    $citizens_count = $stmt->fetchColumn();
    
    // Get recent activity
    $stmt = $pdo->query("
        SELECT al.*, u.username, u.role
        FROM access_logs al
        JOIN users u ON al.user_id = u.user_id
        ORDER BY al.timestamp DESC
        LIMIT 5
    ");
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending official approvals
    $stmt = $pdo->query("
        SELECT oa.*, u.full_name, u.email, u.username, u.created_at
        FROM official_approvals oa
        JOIN users u ON oa.user_id = u.user_id
        WHERE oa.status = 'Pending'
        ORDER BY oa.created_at DESC
        LIMIT 5
    ");
    $pending_approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

// Get current role
$current_role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/official_styles.css">
    <link rel="stylesheet" href="../css/admin_styles.css">
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
                        <a class="nav-link active" href="admin_dashboard.php">
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
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success modern-alert fade-in">
                <i class="fas fa-check-circle"></i> 
                <div><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger modern-alert fade-in">
                <i class="fas fa-exclamation-circle"></i> 
                <div><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Role Switcher -->
        <div class="role-switcher fade-in">
            <h5 class="text-white mb-3">
                <i class="fas fa-exchange-alt me-2"></i>Role View Switcher
            </h5>
            <form action="switch_role.php" method="post" id="roleSwitchForm">
                <input type="hidden" name="target_role" id="targetRole">
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <button type="button" class="role-badge <?php echo $current_role === 'Admin' ? 'active' : ''; ?>" 
                            onclick="switchRole('Admin')">
                        <i class="fas fa-user-shield"></i> Admin
                    </button>
                    <button type="button" class="role-badge <?php echo $current_role === 'Official' ? 'active' : ''; ?>" 
                            onclick="switchRole('Official')">
                        <i class="fas fa-user-tie"></i> Official
                    </button>
                    <button type="button" class="role-badge <?php echo $current_role === 'Merchant' ? 'active' : ''; ?>" 
                            onclick="switchRole('Merchant')">
                        <i class="fas fa-store"></i> Merchant
                    </button>
                    <button type="button" class="role-badge <?php echo $current_role === 'Citizen' ? 'active' : ''; ?>" 
                            onclick="switchRole('Citizen')">
                        <i class="fas fa-user"></i> Citizen
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Stats Row -->
        <div class="row fade-in">
            <div class="col-md-3">
                <div class="stat-card admin">
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-value"><?php echo $admin_count; ?></div>
                    <div class="stat-label">Admins</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-value"><?php echo $officials_count; ?></div>
                    <div class="stat-label">Officials</div>
                    <?php if ($officials_pending > 0): ?>
                        <div class="mt-2">
                            <span class="status-badge pending">
                                <?php echo $officials_pending; ?> pending approval
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-value"><?php echo $merchants_count; ?></div>
                    <div class="stat-label">Merchants</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $citizens_count; ?></div>
                    <div class="stat-label">Citizens</div>
                </div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="row fade-in mt-4">
            <!-- Pending Approvals -->
            <div class="col-md-7">
                <div class="card modern-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-check"></i> Pending Official Approvals
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_approvals)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                <p class="text-muted">No pending approvals at this time.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover modern-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Role</th>
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
                                                <td><?php echo date('M d, Y', strtotime($approval['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-success" onclick="approveOfficial(<?php echo $approval['approval_id']; ?>)">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger" onclick="rejectOfficial(<?php echo $approval['approval_id']; ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <a href="admin_approvals.php" class="btn btn-sm btn-admin">
                                    <i class="fas fa-list"></i> View All Approvals
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-md-5">
                <div class="card modern-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line"></i> Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activity)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history text-muted fa-3x mb-3"></i>
                                <p class="text-muted">No recent activity to display.</p>
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recent_activity as $activity): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start border-0 px-0">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($activity['action_type']); ?>
                                                <span class="badge bg-<?php 
                                                    echo match($activity['role']) {
                                                        'Admin' => 'primary',
                                                        'Official' => 'warning',
                                                        'Merchant' => 'info',
                                                        'Citizen' => 'success',
                                                        default => 'secondary'
                                                    };
                                                ?> rounded-pill">
                                                    <?php echo htmlspecialchars($activity['role']); ?>
                                                </span>
                                            </div>
                                            <div><?php echo htmlspecialchars($activity['action_details']); ?></div>
                                            <small class="text-muted">By <?php echo htmlspecialchars($activity['username']); ?></small>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="text-end mt-3">
                                <a href="admin_logs.php" class="btn btn-sm btn-admin">
                                    <i class="fas fa-list"></i> View All Logs
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
        function switchRole(role) {
            document.getElementById('targetRole').value = role;
            document.getElementById('roleSwitchForm').submit();
        }
        
        function approveOfficial(approvalId) {
            if (confirm('Are you sure you want to approve this official?')) {
                window.location.href = `approve_official.php?id=${approvalId}&action=approve&from=dashboard`;
            }
        }

        function rejectOfficial(approvalId) {
            if (confirm('Are you sure you want to reject this official?')) {
                window.location.href = `approve_official.php?id=${approvalId}&action=reject&from=dashboard`;
            }
        }

        // Fade-in effect for alerts
        document.addEventListener('DOMContentLoaded', function() {
            // Handle alert fade-in
            document.querySelectorAll('.fade-in').forEach(function(element) {
                element.classList.add('show');
            });
            
            // Auto-hide alerts after 5 seconds
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