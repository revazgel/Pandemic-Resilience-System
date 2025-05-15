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
    
    // Get system statistics
    $stats = [];
    
    // Get critical items count
    $stmt = $pdo->query("SELECT COUNT(*) FROM critical_items");
    $stats['critical_items'] = $stmt->fetchColumn();
    
    // Get merchants count
    $stmt = $pdo->query("SELECT COUNT(*) FROM merchants");
    $stats['merchants'] = $stmt->fetchColumn();
    
    // Get total stock across all items
    $stmt = $pdo->query("SELECT SUM(current_quantity) FROM stock");
    $stats['total_stock'] = $stmt->fetchColumn() ?: 0;
    
    // Get total purchases
    $stmt = $pdo->query("SELECT COUNT(*) FROM purchases");
    $stats['purchases'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Overview - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin_styles.css">
    <style>
        .btn-system {
            font-size: 1.1rem;
            padding: 15px 25px;
            margin-bottom: 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-system:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-refresh {
            background: linear-gradient(45deg, #2196F3, #21CBF3);
        }
        .btn-export {
            background: linear-gradient(45deg, #FF9800, #FFE082);
        }
        .btn-reports {
            background: linear-gradient(45deg, #00BCD4, #4DD0E1);
        }
    </style>
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
                        <a class="nav-link active" href="admin_system.php">
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
        <h2><i class="fas fa-cogs"></i> System Overview</h2>
        
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
            <div class="col-md-3 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-boxes fa-3x text-primary mb-3"></i>
                        <h3><?php echo number_format($stats['critical_items']); ?></h3>
                        <p class="text-muted">Critical Items</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-store fa-3x text-info mb-3"></i>
                        <h3><?php echo number_format($stats['merchants']); ?></h3>
                        <p class="text-muted">Registered Merchants</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-warehouse fa-3x text-success mb-3"></i>
                        <h3><?php echo number_format($stats['total_stock']); ?></h3>
                        <p class="text-muted">Total Stock Units</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-3x text-warning mb-3"></i>
                        <h3><?php echo number_format($stats['purchases']); ?></h3>
                        <p class="text-muted">Total Purchases</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-database"></i> Database Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <tr>
                                    <td>System Status</td>
                                    <td><span class="badge bg-success">Running</span></td>
                                </tr>
                                <tr>
                                    <td>Database Version</td>
                                    <td>MySQL 8.0</td>
                                </tr>
                                <tr>
                                    <td>PHP Version</td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> System Controls</h5>
                    </div>
                    <div class="card-body">
                        <button onclick="refreshCache()" class="btn btn-primary btn-system btn-refresh mb-2 w-100">
                            <i class="fas fa-sync-alt"></i> Refresh System Cache
                        </button>
                        <button onclick="exportData()" class="btn btn-warning btn-system btn-export mb-2 w-100">
                            <i class="fas fa-download"></i> Export System Data
                        </button>
                        <button onclick="generateReports()" class="btn btn-info btn-system btn-reports mb-2 w-100">
                            <i class="fas fa-chart-bar"></i> Generate Reports
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3" id="loadingText">Processing request...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showLoading(message) {
            document.getElementById('loadingText').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('loadingModal'));
            modal.show();
        }
        
        function refreshCache() {
            if (confirm('Are you sure you want to refresh the system cache? This will clear all cached data.')) {
                showLoading('Refreshing system cache...');
                window.location.href = 'refresh_cache.php';
            }
        }
        
        function exportData() {
            if (confirm('This will generate a PDF export of system data. Continue?')) {
                showLoading('Generating export file...');
                window.open('export_system_data.php', '_blank');
                // Hide loading modal after delay
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('loadingModal')).hide();
                }, 2000);
            }
        }
        
        function generateReports() {
            if (confirm('This will generate comprehensive system reports. Continue?')) {
                showLoading('Generating detailed reports...');
                window.open('generate_reports.php', '_blank');
                // Hide loading modal after delay
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('loadingModal')).hide();
                }, 2000);
            }
        }
        
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