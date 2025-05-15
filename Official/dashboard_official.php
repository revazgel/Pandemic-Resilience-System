<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Official') {
    header("Location: ../Authentication/login.html");
    exit();
}

// Initialize variables
$merchants_count = 0;
$citizens_count = 0;
$vaccinations_count = 0;
$low_stock_alerts = [];
$official = null;

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
    $stmt = $pdo->prepare("SELECT * FROM government_officials WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $official = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($official) {
        // Get system-wide stats
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM merchants");
        $merchants_count = $stmt->execute() ? $stmt->fetchColumn() : 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'Citizen'");
        $citizens_count = $stmt->execute() ? $stmt->fetchColumn() : 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vaccination_records");
        $vaccinations_count = $stmt->execute() ? $stmt->fetchColumn() : 0;
        
        // Get low stock alerts
        $stmt = $pdo->prepare("
            SELECT m.business_name, COUNT(*) as low_stock_count
            FROM stock s
            JOIN merchants m ON s.merchant_id = m.merchant_id
            WHERE s.current_quantity < 10
            GROUP BY m.merchant_id
            ORDER BY low_stock_count DESC
            LIMIT 3
        ");
        $stmt->execute();
        $low_stock_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

// Get user info for display
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Official Dashboard - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/official_styles.css">
</head>
<body>
<nav class="navbar navbar-expand-lg" style="background: linear-gradient(90deg, #8e2de2 0%, #ff6a00 100%);">
<?php include 'navbar.php'; ?>
</nav>
    
    <div class="container py-3">
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
        
        <!-- Official Info with Welcome Message -->
        <div class="official-info fade-in">
            <div>
                <h4 class="m-0">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h4>
                <div class="official-details">
                    <?php 
                    if ($official) {
                        echo htmlspecialchars($official['department']) . ' | ' . 
                             htmlspecialchars($official['role']);
                    }
                    ?>
                </div>
            </div>
            <a href="official_profile.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-user-circle"></i> View Profile
            </a>
        </div>
        
        <!-- Stats -->
        <div class="stats-row fade-in">
            <div class="stat-box">
                <i class="fas fa-store text-primary mb-1"></i>
                <p class="stat-value"><?php echo $merchants_count; ?></p>
                <p class="stat-label">Registered Merchants</p>
            </div>
            
            <div class="stat-box">
                <i class="fas fa-users text-primary mb-1"></i>
                <p class="stat-value"><?php echo $citizens_count; ?></p>
                <p class="stat-label">Registered Citizens</p>
            </div>
            
            <div class="stat-box">
                <i class="fas fa-syringe text-primary mb-1"></i>
                <p class="stat-value"><?php echo $vaccinations_count; ?></p>
                <p class="stat-label">Vaccination Records</p>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="grid-container fade-in">
            <!-- Left Column -->
            <div class="grid-left">
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </div>
                    <div class="panel-body">
                        <div class="action-links">
                            <a href="manage_merchants.php" class="action-link">
                                <i class="fas fa-store"></i> Manage Merchants
                            </a>
                            <a href="manage_citizens.php" class="action-link">
                                <i class="fas fa-users"></i> Manage Citizens
                            </a>
                            <a href="manage_vaccinations.php" class="action-link">
                                <i class="fas fa-syringe"></i> Manage Vaccinations
                            </a>
                            <a href="manage_items.php" class="action-link">
                                <i class="fas fa-boxes"></i> Manage Critical Items
                            </a>
                            <a href="manage_schedules.php" class="action-link">
                                <i class="fas fa-calendar-alt"></i> Manage Schedules
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="grid-right">
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-bell"></i> System Alerts
                    </div>
                    <div class="panel-body p-2">
                        <!-- Low Stock Alerts -->
                        <div class="alert-section">
                            <h6 class="alert-title">Low Stock Alerts</h6>
                            <?php if (empty($low_stock_alerts)): ?>
                                <div class="alert-item alert-info">
                                    <i class="fas fa-check-circle"></i> No low stock alerts
                                </div>
                            <?php else: ?>
                                <?php foreach($low_stock_alerts as $alert): ?>
                                    <div class="alert-item alert-warning">
                                        <span><?php echo htmlspecialchars($alert['business_name']); ?></span>
                                        <span class="float-end badge bg-warning text-dark">
                                            <?php echo $alert['low_stock_count']; ?> items low
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 