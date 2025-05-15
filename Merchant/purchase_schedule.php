<?php
require_once '../Authentication/session_check.php';

// Redirect non-merchants
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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get merchant info
    $stmt = $pdo->prepare("SELECT * FROM merchants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle missing merchant profile (admin view)
    if (!$merchant) {
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin') {
            // Create mock merchant for admin view
            $merchant = [
                'merchant_id' => 0,
                'business_name' => 'Admin View Mode',
                'business_type' => 'Administration',
                'address' => 'System Admin View'
            ];
            $_SESSION['info_message'] = "Admin viewing mode - Purchase schedule display is limited without a merchant profile.";
        } else {
            $_SESSION['error_message'] = "Merchant profile not found.";
            header("Location: dashboard_merchant.php");
            exit();
        }
    }
    
    // Set up business type filtering
    $merchant_id = $merchant['merchant_id'];
    $business_type = $merchant['business_type'];
    $allowed_categories = [];
    
    switch($business_type) {
        case 'Pharmacy': $allowed_categories = ['Medical']; break;
        case 'Grocery': $allowed_categories = ['Grocery']; break;
        case 'Supermarket': 
        case 'Administration': $allowed_categories = ['Medical', 'Grocery']; break;
        default: $allowed_categories = ['Medical', 'Grocery'];
    }
    
    $placeholders = str_repeat('?,', count($allowed_categories) - 1) . '?';
    $today_day = date('N'); // 1-7 (Mon-Sun)
    
    // Get distinct items first to avoid duplication
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            ci.item_id, ci.item_name, ci.item_category, ci.unit_of_measure, 
            ci.max_quantity_per_day, ci.max_quantity_per_week
        FROM critical_items ci
        JOIN purchase_schedule ps ON ci.item_id = ps.item_id
        WHERE ci.item_category IN ($placeholders)
        ORDER BY ci.item_category, ci.item_name
    ");
    $stmt->execute($allowed_categories);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get schedules and stock for each item
    $items_data = [];
    foreach ($items as $item) {
        // Get schedules
        $stmt = $pdo->prepare("
            SELECT day_of_week, dob_year_ending
            FROM purchase_schedule
            WHERE item_id = ? AND effective_from <= CURRENT_DATE 
            AND (effective_to IS NULL OR effective_to >= CURRENT_DATE)
        ");
        $stmt->execute([$item['item_id']]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Skip if item has no schedules
        if (empty($schedules)) continue;
        
        // Get stock status (only if we have a real merchant)
        $stock = null;
        if ($merchant_id > 0) {
            $stmt = $pdo->prepare("
                SELECT current_quantity FROM stock 
                WHERE merchant_id = ? AND item_id = ?
            ");
            $stmt->execute([$merchant_id, $item['item_id']]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $item['schedules'] = $schedules;
        $item['in_stock'] = $stock ? true : false;
        $item['current_quantity'] = $stock ? $stock['current_quantity'] : 0;
        
        $items_data[] = $item;
    }
    
    // Get today's schedule
    $stmt = $pdo->prepare("
        SELECT DISTINCT ci.item_id, ci.item_name, ci.item_category, ps.dob_year_ending
        FROM purchase_schedule ps
        JOIN critical_items ci ON ps.item_id = ci.item_id
        WHERE ps.day_of_week = ? AND ci.item_category IN ($placeholders)
        AND ps.effective_from <= CURRENT_DATE 
        AND (ps.effective_to IS NULL OR ps.effective_to >= CURRENT_DATE)
    ");
    $params = array_merge([$today_day], $allowed_categories);
    $stmt->execute($params);
    $today_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group items by category for display
    $categories = [];
    foreach ($items_data as $item) {
        $category = $item['item_category'];
        if (!isset($categories[$category])) {
            $categories[$category] = [];
        }
        $categories[$category][] = $item;
    }
    
    // Get purchase data for chart (only if we have a real merchant)
    $chart_data = array_fill(0, 7, 0);
    if ($merchant_id > 0) {
        $stmt = $pdo->prepare("
            SELECT DAYOFWEEK(purchase_date) as day_num, COUNT(*) as count
            FROM purchases WHERE merchant_id = ? 
            AND purchase_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            GROUP BY DAYOFWEEK(purchase_date)
        ");
        $stmt->execute([$merchant_id]);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($purchases as $p) {
            $chart_data[($p['day_num'] - 1)] = (int)$p['count'];
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: dashboard_merchant.php");
    exit();
}

// Day names lookup
$day_names = [
    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 
    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Schedules - PRS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
    <style>
        .today-schedule { background-color: #f0f7ff; border-radius: 10px; border-left: 5px solid #0d6efd; }
        .day-badge { display: inline-block; padding: 5px 10px; margin: 3px; border-radius: 20px; font-size: 0.8rem; }
        .day-active { background-color: #0d6efd; color: white; }
        .day-inactive { background-color: #e9ecef; color: #495057; }
        .dob-badge { display: inline-block; padding: 3px 8px; margin: 2px; border-radius: 4px; font-size: 0.75rem; background-color: #f8f9fa; border: 1px solid #dee2e6; }
        .schedule-card { border-left-width: 4px; }
        .has-stock { border-left-color: #198754; }
        .no-stock { border-left-color: #dc3545; opacity: 0.8; }
        .chart-container { height: 250px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <!-- Header -->
        <div class="page-header fade-in">
            <h2><i class="fas fa-calendar-alt"></i> Purchase Schedules</h2>
            <div>
                <span class="badge bg-primary"><?= htmlspecialchars($merchant['business_name']) ?></span>
                <a href="dashboard_merchant.php" class="btn btn-outline-light btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info fade-in">
                <i class="fas fa-info-circle"></i>
                <?= $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Today's Schedule -->
        <div class="card today-schedule fade-in mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="fas fa-calendar-day me-2"></i> Today's Schedule (<?= $day_names[$today_day] ?>)
                </h5>
                <p class="text-muted">The following items can be purchased today by customers with birth years ending in:</p>
                
                <?php if (empty($today_items)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> No scheduled purchases for today.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($today_items as $item): ?>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body py-3">
                                        <h6 class="mb-2"><?= htmlspecialchars($item['item_name']) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge <?= $item['item_category'] === 'Medical' ? 'bg-info' : 'bg-success' ?>">
                                                <?= htmlspecialchars($item['item_category']) ?>
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                Years ending: <?= $item['dob_year_ending'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Purchase Trend Chart -->
        <div class="card fade-in mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Purchase Trends</h5>
            </div>
            <div class="card-body">
                <?php if ($merchant_id > 0): ?>
                    <div class="chart-container">
                        <canvas id="purchaseChart"></canvas>
                    </div>
                    <p class="text-muted small text-center">30-day purchase history</p>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Chart not available in admin view mode</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Search and filter -->
        <div class="row g-3 mb-4 fade-in">
            <div class="col-md-8">
                <div class="search-container w-100">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search for items...">
                </div>
            </div>
            <div class="col-md-4">
                <select id="categoryFilter" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach (array_keys($categories) as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Schedule Items by Category -->
        <div id="scheduleList" class="fade-in">
            <?php if (empty($categories)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No purchase schedules found for your business type.
                </div>
            <?php else: ?>
                <?php foreach ($categories as $category => $items): ?>
                    <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                        <div class="category-header">
                            <i class="fas fa-<?= $category === 'Medical' ? 'medkit' : 'shopping-basket' ?> me-2"></i>
                            <?= htmlspecialchars($category) ?>
                        </div>
                        
                        <?php foreach ($items as $item): ?>
                            <div class="card schedule-card mb-3 fade-in <?= $item['in_stock'] ? 'has-stock' : 'no-stock' ?>" 
                                 data-item-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>"
                                 data-category="<?= strtolower(htmlspecialchars($item['item_category'])) ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h5 class="card-title"><?= htmlspecialchars($item['item_name']) ?></h5>
                                            <p class="small text-muted mb-2">
                                                <?= htmlspecialchars($item['unit_of_measure']) ?> | 
                                                Max: <?= $item['max_quantity_per_day'] ?>/day, 
                                                <?= $item['max_quantity_per_week'] ?>/week
                                            </p>
                                            
                                            <?php if ($merchant_id > 0): ?>
                                                <?php if ($item['in_stock']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i> In Stock: <?= $item['current_quantity'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times-circle me-1"></i> Out of Stock
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-eye me-1"></i> Admin View
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-8">
                                            <h6><i class="fas fa-calendar-alt me-2"></i> Purchase Schedule:</h6>
                                            
                                            <!-- Day badges -->
                                            <div class="mb-3">
                                                <?php foreach ($day_names as $day_num => $day_name): 
                                                    $is_active = false;
                                                    foreach ($item['schedules'] as $schedule) {
                                                        if ($schedule['day_of_week'] == $day_num) {
                                                            $is_active = true;
                                                            break;
                                                        }
                                                    }
                                                    $class = $is_active ? 'day-active' : 'day-inactive';
                                                    if ($is_active && $day_num == $today_day) {
                                                        $class .= ' border border-primary';
                                                    }
                                                ?>
                                                    <span class="day-badge <?= $class ?>">
                                                        <?= $day_name ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <!-- DOB schedules -->
                                            <div>
                                                <p class="small mb-2">For DOB years ending in:</p>
                                                <?php 
                                                // Group schedules by day
                                                $day_schedule = [];
                                                foreach ($item['schedules'] as $schedule) {
                                                    $day = $schedule['day_of_week'];
                                                    if (!isset($day_schedule[$day])) {
                                                        $day_schedule[$day] = [];
                                                    }
                                                    $day_schedule[$day][] = $schedule['dob_year_ending'];
                                                }
                                                
                                                foreach ($day_schedule as $day => $endings): 
                                                    $is_today = ($day == $today_day);
                                                ?>
                                                    <div class="small mb-1 <?= $is_today ? 'fw-bold' : '' ?>">
                                                        <strong><?= $day_names[$day] ?>:</strong> 
                                                        <?php foreach ($endings as $ending): ?>
                                                            <span class="dob-badge">
                                                                <?= $ending ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between">
                                        <?php if ($merchant_id > 0): ?>
                                            <?php if ($item['in_stock']): ?>
                                                <a href="purchasesADD.php?item_id=<?= $item['item_id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-cash-register me-1"></i> Process Purchase
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="fas fa-cash-register me-1"></i> Out of Stock
                                                </button>
                                            <?php endif; ?>
                                            <a href="stockADD.php?item_id=<?= $item['item_id'] ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit me-1"></i> Update Stock
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Admin view - Operations disabled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer-nav fade-in">
            <a href="dashboard_merchant.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-1"></i> Dashboard
            </a>
            <a href="critical_items.php" class="btn btn-outline-primary">
                <i class="fas fa-first-aid me-1"></i> View Critical Items
            </a>
        </div>
    </div>
    
    <?php if ($merchant_id > 0): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animations
            document.querySelectorAll('.fade-in').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.style.opacity = '1', 100);
            });
            
            // Chart
            const chartData = <?= json_encode($chart_data) ?>;
            const ctx = document.getElementById('purchaseChart').getContext('2d');
            
            const purchaseChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                    datasets: [{
                        label: 'Purchases',
                        data: chartData,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.5)', 'rgba(255, 99, 132, 0.5)', 
                            'rgba(255, 159, 64, 0.5)', 'rgba(255, 205, 86, 0.5)', 
                            'rgba(75, 192, 192, 0.5)', 'rgba(153, 102, 255, 0.5)',
                            'rgba(201, 203, 207, 0.5)'
                        ],
                        borderColor: [
                            'rgb(54, 162, 235)', 'rgb(255, 99, 132)', 'rgb(255, 159, 64)', 
                            'rgb(255, 205, 86)', 'rgb(75, 192, 192)', 'rgb(153, 102, 255)', 
                            'rgb(201, 203, 207)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
            
            // Highlight today
            const todayIndex = <?= date('w') ?>;
            purchaseChart.data.datasets[0].backgroundColor[todayIndex] = 'rgba(40, 167, 69, 0.7)';
            purchaseChart.data.datasets[0].borderColor[todayIndex] = 'rgb(40, 167, 69)';
            purchaseChart.update();
        });
    </script>
    <?php else: ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animations
            document.querySelectorAll('.fade-in').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.style.opacity = '1', 100);
            });
        });
    </script>
    <?php endif; ?>
    
    <script>
        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        
        function filterItems() {
            const searchTerm = searchInput.value.toLowerCase();
            const categoryValue = categoryFilter.value.toLowerCase();
            
            document.querySelectorAll('.schedule-card').forEach(card => {
                const itemName = card.getAttribute('data-item-name');
                const category = card.getAttribute('data-category');
                
                const matchesSearch = itemName.includes(searchTerm);
                const matchesCategory = categoryValue === '' || category === categoryValue;
                
                card.style.display = matchesSearch && matchesCategory ? '' : 'none';
            });
            
            document.querySelectorAll('.category-section').forEach(section => {
                const category = section.getAttribute('data-category').toLowerCase();
                const matchesCategory = categoryValue === '' || category === categoryValue;
                
                if (!matchesCategory) {
                    section.style.display = 'none';
                    return;
                }
                
                const hasVisibleItems = Array.from(section.querySelectorAll('.schedule-card'))
                    .some(card => card.style.display !== 'none');
                
                section.style.display = hasVisibleItems ? '' : 'none';
            });
        }
        
        searchInput.addEventListener('input', filterItems);
        categoryFilter.addEventListener('change', filterItems);
    </script>
</body>
</html>