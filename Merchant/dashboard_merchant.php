<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Merchant') {
    header("Location: ../Authentication/login.html");
    exit();
}

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

// Initialize all variables with default values first
$merchant = null;
$merchant_id = 0;
$items_count = 0;
$purchases_count = 0;
$low_stock_count = 0;
$low_stock_items = [];
$today_item = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get merchant info
    $stmt = $pdo->prepare("SELECT * FROM merchants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($merchant && isset($merchant['merchant_id'])) {
        $merchant_id = $merchant['merchant_id'];
        
        // Get basic stats
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE merchant_id = ?");
            $stmt->execute([$merchant_id]);
            $items_count = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $items_count = 0;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE merchant_id = ?");
            $stmt->execute([$merchant_id]);
            $purchases_count = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $purchases_count = 0;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE merchant_id = ? AND current_quantity < 10");
            $stmt->execute([$merchant_id]);
            $low_stock_count = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $low_stock_count = 0;
        }
        
        // Get low stock items
        try {
            $stmt = $pdo->prepare("
                SELECT ci.item_name, s.current_quantity 
                FROM stock s
                JOIN critical_items ci ON s.item_id = ci.item_id
                WHERE s.merchant_id = ? AND s.current_quantity < 10
                ORDER BY s.current_quantity ASC
                LIMIT 3
            ");
            $stmt->execute([$merchant_id]);
            $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $low_stock_items = [];
        }
        
        // Get today's eligible item(s)
        try {
            $today_day = date('N'); // 1 (Monday) to 7 (Sunday)
            $stmt = $pdo->prepare("
                SELECT ci.item_name
                FROM purchase_schedule ps
                JOIN critical_items ci ON ps.item_id = ci.item_id
                WHERE ps.day_of_week = ?
                GROUP BY ci.item_id
                ORDER BY ci.item_name
                LIMIT 1
            ");
            $stmt->execute([$today_day]);
            $today_item = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $today_item = null;
        }
    } else {
        // Handle case where merchant doesn't exist (e.g., admin viewing as merchant)
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin') {
            // For admin viewing as merchant, create a mock profile
            $merchant = [
                'business_name' => 'Admin View (No Merchant Profile)',
                'business_type' => 'Administration',
                'address' => 'System Admin View'
            ];
        } else {
            $_SESSION['error_message'] = "Merchant profile not found. Please contact system administrator.";
        }
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    error_log("Dashboard error: " . $e->getMessage());
}

// Ensure all variables are properly set
$items_count = $items_count ?? 0;
$purchases_count = $purchases_count ?? 0;
$low_stock_count = $low_stock_count ?? 0;
$low_stock_items = $low_stock_items ?? [];
$today_item = $today_item ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Dashboard - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
    <!-- Custom CSS for better error display -->
    <style>
        .error-container {
            white-space: pre-line;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 14px;
        }
        .error-container br {
            display: block;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
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
        
        <!-- Merchant Info with Welcome Message -->
        <div class="merchant-info fade-in">
            <div>
                <h4 class="m-0">
                    Welcome, 
                    <?php 
                    if (is_array($merchant) && isset($merchant['business_name'])) {
                        echo htmlspecialchars($merchant['business_name']);
                    } else {
                        echo 'User';
                    }
                    ?>
                </h4>
                <div class="merchant-details">
                    <?php 
                    if (is_array($merchant)) {
                        $business_type = isset($merchant['business_type']) ? htmlspecialchars($merchant['business_type']) : 'N/A';
                        $address = isset($merchant['address']) ? htmlspecialchars($merchant['address']) : 'No address available';
                        echo $business_type . ' | ' . $address;
                    } else {
                        echo 'No merchant profile available';
                    }
                    ?>
                </div>
            </div>
            <a href="merchant_profile.php" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-user-circle"></i> View Profile
            </a>
        </div>
        
        <!-- Stats -->
        <div class="stats-row fade-in">
            <div class="stat-box">
                <i class="fas fa-boxes text-primary mb-1"></i>
                <p class="stat-value"><?php echo (int)$items_count; ?></p>
                <p class="stat-label">Items in Inventory</p>
            </div>
            
            <div class="stat-box">
                <i class="fas fa-shopping-cart text-primary mb-1"></i>
                <p class="stat-value"><?php echo (int)$purchases_count; ?></p>
                <p class="stat-label">Total Purchases</p>
            </div>
            
            <div class="stat-box">
                <i class="fas fa-exclamation-triangle text-warning mb-1"></i>
                <p class="stat-value"><?php echo (int)$low_stock_count; ?></p>
                <p class="stat-label">Items Low in Stock</p>
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
                            <a href="merchant_stock.php" class="action-link">
                                <i class="fas fa-boxes"></i> View Inventory
                            </a>
                            <a href="stockADD.php" class="action-link">
                                <i class="fas fa-plus-circle"></i> Update Inventory
                            </a>
                            <a href="merchant_purchases.php" class="action-link">
                                <i class="fas fa-shopping-cart"></i> View Purchases
                            </a>
                            <a href="purchasesADD.php" class="action-link">
                                <i class="fas fa-cash-register"></i> New Purchase
                            </a>
                            <a href="critical_items.php" class="action-link">
                                <i class="fas fa-first-aid"></i> Critical Items
                            </a>
                            <a href="purchase_schedule.php" class="action-link">
                                <i class="fas fa-calendar-alt"></i> Schedules
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="grid-right">
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-bell"></i> Inventory Alerts
                    </div>
                    <div class="panel-body p-2">
                        <?php if (!is_array($merchant) || $merchant_id === 0): ?>
                            <div class="alert-item alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No merchant profile found
                            </div>
                        <?php elseif (is_array($low_stock_items) && empty($low_stock_items)): ?>
                            <div class="alert-item alert-info">
                                <i class="fas fa-check-circle"></i> No low stock alerts
                            </div>
                        <?php elseif (is_array($low_stock_items)): ?>
                            <?php foreach($low_stock_items as $item): ?>
                                <?php if (is_array($item) && isset($item['item_name']) && isset($item['current_quantity'])): ?>
                                    <div class="alert-item <?php echo $item['current_quantity'] < 5 ? 'alert-critical' : 'alert-warning'; ?>">
                                        <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                                        <span class="float-end badge <?php echo $item['current_quantity'] < 5 ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                            <?php echo (int)$item['current_quantity']; ?> left
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($today_item)): ?>
                            <div class="alert-item alert-info mt-2">
                                <i class="fas fa-calendar-day"></i> Today's eligible: 
                                <strong><?php echo htmlspecialchars($today_item); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center text-muted mt-4">
            <small>Pandemic Resilience System © 2025</small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to elements
            document.querySelectorAll('.fade-in').forEach(function(element) {
                element.style.opacity = '0';
                setTimeout(function() {
                    element.style.opacity = '1';
                }, 100);
            });
        });
    </script>
</body>
</html>