<?php
require_once '../Authentication/session_check.php';

// Check role and redirect if needed
if ($_SESSION['role'] !== 'Merchant') {
    header("Location: ../Authentication/login.html");
    exit();
}

// DB connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=CovidSystem;charset=utf8mb4", 'root', '');
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
            $_SESSION['info_message'] = "Admin viewing mode - Critical items display is limited without a merchant profile.";
        } else {
            $_SESSION['error_message'] = "Merchant profile not found.";
            header("Location: dashboard_merchant.php");
            exit();
        }
    }
    
    // Get allowed categories based on business type
    $merchant_id = $merchant['merchant_id'];
    $business_type = $merchant['business_type'];
    
    $allowed_categories = match($business_type) {
        'Pharmacy' => ['Medical'],
        'Grocery' => ['Grocery'],
        'Supermarket', 'Administration' => ['Medical', 'Grocery'],
        default => ['Medical', 'Grocery']
    };
    
    // Get critical items
    $placeholders = str_repeat('?,', count($allowed_categories) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM critical_items 
                         WHERE item_category IN ($placeholders)
                         ORDER BY item_category, item_name");
    $stmt->execute($allowed_categories);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stock data (only if we have a real merchant)
    $item_ids = array_column($items, 'item_id');
    $stock_data = [];
    
    if (!empty($item_ids) && $merchant_id > 0) {
        $item_placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT item_id, current_quantity FROM stock 
                             WHERE merchant_id = ? AND item_id IN ($item_placeholders)");
        $params = array_merge([$merchant_id], $item_ids);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stock_data[$row['item_id']] = $row['current_quantity'];
        }
    }
    
    // Group by category
    $categories = [];
    foreach ($items as $item) {
        $cat = $item['item_category'];
        if (!isset($categories[$cat])) $categories[$cat] = [];
        $categories[$cat][] = $item;
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: dashboard_merchant.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Critical Items - PRS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
    <style>
        .category-header {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .stock-badge { margin-bottom: 5px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header fade-in">
            <h2><i class="fas fa-first-aid"></i> Critical Items</h2>
            <div>
                <span class="badge bg-primary"><?= htmlspecialchars($merchant['business_name']) ?></span>
                <a href="dashboard_merchant.php" class="btn btn-outline-light btn-sm ms-2">
                    <i class="fas fa-arrow-left"></i> Back
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
        
        <div class="card fade-in mb-3">
            <div class="card-body">
                <h5><i class="fas fa-info-circle me-2"></i> About Critical Items</h5>
                <p>These are essential items authorized for your business type (<?= htmlspecialchars($business_type) ?>) during pandemic situations. They have specific purchase restrictions:</p>
                <ul class="mb-0">
                    <li><strong>Daily and weekly purchase limits</strong> per customer</li>
                    <li><strong>Scheduled purchase days</strong> based on customer's date of birth</li>
                </ul>
            </div>
        </div>
        
        <div class="row g-3 mb-3 fade-in">
            <div class="col-md-8">
                <div class="search-container w-100">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search critical items...">
                </div>
            </div>
            <div class="col-md-4">
                <select id="categoryFilter" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach (array_keys($categories) as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if (empty($categories)): ?>
            <div class="alert alert-info fade-in">
                <i class="fas fa-info-circle me-2"></i> No critical items found for your business type.
            </div>
        <?php else: 
            foreach ($categories as $category => $items):
                $icon = ($category === 'Medical') ? 'medkit' : 'shopping-basket';
        ?>
            <div class="category-section fade-in" data-category="<?= htmlspecialchars($category) ?>">
                <div class="category-header">
                    <i class="fas fa-<?= $icon ?> me-2"></i> <?= htmlspecialchars($category) ?>
                </div>
                
                <div class="row g-3">
                    <?php foreach ($items as $item): 
                        $quantity = isset($stock_data[$item['item_id']]) ? $stock_data[$item['item_id']] : 0;
                        
                        // Set stock display properties
                        if ($quantity <= 0) {
                            $badge_class = 'bg-danger';
                            $stock_text = 'Out of Stock';
                            $icon = 'times-circle';
                            $progress_class = '';
                            $progress_width = '0%';
                        } elseif ($quantity < 5) {
                            $badge_class = 'bg-danger';
                            $stock_text = "Critical Stock";
                            $icon = 'exclamation-circle';
                            $progress_class = 'bg-danger';
                            $progress_width = '20%';
                        } elseif ($quantity < 10) {
                            $badge_class = 'bg-warning text-dark';
                            $stock_text = "Low Stock";
                            $icon = 'exclamation-triangle';
                            $progress_class = 'bg-warning';
                            $progress_width = '50%';
                        } else {
                            $badge_class = 'bg-success';
                            $stock_text = "In Stock";
                            $icon = 'check-circle';
                            $progress_class = 'bg-success';
                            $progress_width = '80%';
                        }
                    ?>
                        <div class="col-md-6 item-container" 
                             data-item-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>"
                             data-item-category="<?= strtolower(htmlspecialchars($category)) ?>">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-<?= $icon ?> me-2"></i>
                                        <?= htmlspecialchars($item['item_name']) ?>
                                    </h5>
                                    <?php if ($item['is_restricted']): ?>
                                        <span class="badge bg-warning text-dark">Restricted</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <p><?= $item['item_description'] ? htmlspecialchars($item['item_description']) : 'No description available.' ?></p>
                                    
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-ruler me-1"></i> <?= htmlspecialchars($item['unit_of_measure']) ?>
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-calendar-day me-1"></i> Max <?= $item['max_quantity_per_day'] ?>/day
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-calendar-week me-1"></i> Max <?= $item['max_quantity_per_week'] ?>/week
                                        </span>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <div class="stock-badge">
                                            <span class="badge <?= $badge_class ?>">
                                                <i class="fas fa-<?= $icon ?> me-1"></i> <?= $stock_text ?>
                                                <?php if ($quantity > 0): ?>: <?= $quantity ?><?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($quantity > 0): ?>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar <?= $progress_class ?>" 
                                                     style="width: <?= $progress_width ?>" 
                                                     aria-valuenow="<?= $quantity ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between">
                                        <?php if ($merchant_id > 0): ?>
                                            <?php if ($quantity > 0): ?>
                                                <a href="purchasesADD.php?item_id=<?= $item['item_id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-shopping-cart me-1"></i> Process Sale
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="fas fa-shopping-cart me-1"></i> Out of Stock
                                                </button>
                                            <?php endif; ?>
                                            <a href="stockADD.php?item_id=<?= $item['item_id'] ?>&merchant_id=<?= $merchant_id ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-plus-circle me-1"></i> Update
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Admin view - Operations disabled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
        
        <div class="footer-nav fade-in">
            <a href="dashboard_merchant.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-1"></i> Dashboard
            </a>
            <?php if ($merchant_id > 0): ?>
                <a href="stockADD.php" class="btn btn-success">
                    <i class="fas fa-plus-circle me-1"></i> Add Stock
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animations
            document.querySelectorAll('.fade-in').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.style.opacity = '1', 100);
            });
            
            // Search and filter
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            
            function filterItems() {
                const term = searchInput.value.toLowerCase();
                const category = categoryFilter.value.toLowerCase();
                
                document.querySelectorAll('.item-container').forEach(item => {
                    const name = item.getAttribute('data-item-name');
                    const cat = item.getAttribute('data-item-category');
                    
                    const matchesSearch = name.includes(term);
                    const matchesCategory = category === '' || cat === category;
                    
                    item.style.display = matchesSearch && matchesCategory ? '' : 'none';
                });
                
                document.querySelectorAll('.category-section').forEach(section => {
                    const cat = section.getAttribute('data-category').toLowerCase();
                    
                    if (category !== '' && category !== cat) {
                        section.style.display = 'none';
                        return;
                    }
                    
                    const hasVisibleItems = Array.from(section.querySelectorAll('.item-container'))
                        .some(item => item.style.display !== 'none');
                    
                    section.style.display = hasVisibleItems ? '' : 'none';
                });
            }
            
            searchInput.addEventListener('input', filterItems);
            categoryFilter.addEventListener('change', filterItems);
        });
    </script>
</body>
</html>