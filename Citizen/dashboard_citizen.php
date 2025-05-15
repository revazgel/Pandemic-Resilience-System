<?php
require_once '../Authentication/session_check.php';
if ($_SESSION['role'] !== 'Citizen') { 
    header("Location: ../Authentication/login.html"); 
    exit(); 
}

// DB config and data fetching
$pdo = new PDO("mysql:host=localhost;dbname=CovidSystem;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get essential data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM Users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get basic stats
$vaccination_count = $pdo->query("SELECT COUNT(*) FROM vaccination_records WHERE user_id = $user_id")->fetchColumn();
$purchase_count = $pdo->query("SELECT COUNT(*) FROM purchases WHERE user_id = $user_id")->fetchColumn();

// Check today's eligibility (simplified)
$today_day = date('N'); // 1-7 (Mon-Sun)
$dob_year = date('Y', strtotime($user['dob']));
$last_digit = substr($dob_year, -1);

$stmt = $pdo->prepare("
    SELECT ci.item_id, ci.item_name FROM purchase_schedule ps
    JOIN critical_items ci ON ps.item_id = ci.item_id
    WHERE ps.day_of_week = ? 
    AND FIND_IN_SET(?, ps.dob_year_ending) > 0
    AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')
");
$stmt->execute([$today_day, $last_digit]);
$eligible_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
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
        
        <!-- User Info -->
        <div class="merchant-info fade-in">
            <div>
                <h4 class="m-0"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <div class="text-muted">
                    PRS-ID: <?php echo $user['prs_id']; ?> | 
                    DOB: <?php echo date('Y-m-d', strtotime($user['dob'])); ?>
                </div>
            </div>
            <a href="../Authentication/logout.php" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <!-- Stats -->
        <div class="stats-row fade-in">
            <div class="stat-box">
                <i class="fas fa-syringe text-primary mb-1"></i>
                <p class="stat-value"><?php echo $vaccination_count; ?></p>
                <p class="stat-label">Vaccinations</p>
            </div>
            
            <div class="stat-box">
                <i class="fas fa-shopping-cart text-primary mb-1"></i>
                <p class="stat-value"><?php echo $purchase_count; ?></p>
                <p class="stat-label">Purchases</p>
            </div>
            
            <div class="stat-box">
                <i class="fas fa-calendar-day text-primary mb-1"></i>
                <p class="stat-value"><?php echo count($eligible_items); ?></p>
                <p class="stat-label">Eligible Items Today</p>
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
                            <a href="citizen_find_supplies.php" class="action-link">
                                <i class="fas fa-search"></i> Find Supplies
                            </a>
                            <a href="citizen_purchase_history.php" class="action-link">
                                <i class="fas fa-history"></i> Purchase History
                            </a>
                            <a href="citizen_vaccinations.php" class="action-link">
                                <i class="fas fa-syringe"></i> My Vaccinations
                            </a>
                            <a href="citizen_upload_vaccination.php" class="action-link">
                                <i class="fas fa-upload"></i> Upload Vaccination
                            </a>
                            <a href="citizen_purchase_schedule.php" class="action-link">
                                <i class="fas fa-calendar-alt"></i> Purchase Schedule
                            </a>
                            <a href="citizen_vaccines.php" class="action-link">
                                <i class="fas fa-shield-virus"></i> Vaccines Info
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="grid-right">
                <div class="panel">
                    <div class="panel-header">
                        <i class="fas fa-bell"></i> Today's Eligible Items
                    </div>
                    <div class="panel-body p-2">
                        <?php if (empty($eligible_items)): ?>
                            <div class="alert-item alert-warning">
                                <i class="fas fa-exclamation-circle"></i> No eligible items today
                            </div>
                        <?php else: ?>
                            <?php foreach($eligible_items as $item): ?>
                                <div class="alert-item alert-info">
                                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($item['item_name']); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center text-muted">
            <small>COVID Resilience System © 2025</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns if any exist
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
            
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