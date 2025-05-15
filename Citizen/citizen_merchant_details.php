<?php
require_once '../Authentication/session_check.php';
require_once '../Helper/item_eligibility.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Citizen') {
    header("Location: ../Authentication/login.html");
    exit();
}

// Check if merchant_id is provided
if (!isset($_GET['merchant_id']) || empty($_GET['merchant_id'])) {
    $_SESSION['error_message'] = "Merchant ID is required";
    header("Location: citizen_find_supplies.php");
    exit();
}

$merchant_id = (int)$_GET['merchant_id'];

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get merchant details
    $stmt = $pdo->prepare("
        SELECT * FROM merchants 
        WHERE merchant_id = ? AND is_active = 1
    ");
    $stmt->execute([$merchant_id]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$merchant) {
        $_SESSION['error_message'] = "Merchant not found or is inactive";
        header("Location: citizen_find_supplies.php");
        exit();
    }
    
    // Get merchant's stock
    $stmt = $pdo->prepare("
        SELECT s.stock_id, s.item_id, s.current_quantity, s.last_restock_date,
               ci.item_name, ci.item_description, ci.item_category, ci.unit_of_measure,
               ci.is_restricted, ci.max_quantity_per_day, ci.max_quantity_per_week
        FROM stock s
        JOIN critical_items ci ON s.item_id = ci.item_id
        WHERE s.merchant_id = ?
        ORDER BY ci.item_category, ci.item_name
    ");
    $stmt->execute([$merchant_id]);
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get business owner details (if needed)
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.email, u.phone
        FROM Users u
        JOIN merchants m ON u.user_id = m.user_id
        WHERE m.merchant_id = ?
    ");
    $stmt->execute([$merchant_id]);
    $business_owner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check eligibility for the user
    $eligibility = checkUserEligibility($pdo, $_SESSION['user_id']);
    $eligible_item_ids = array_column($eligibility['items'], 'item_id');
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: citizen_find_supplies.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Details - COVID Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    <div class="container">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-store"></i> Merchant Details</h2>
            <a href="citizen_find_supplies.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Supplies Search
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?php echo htmlspecialchars($merchant['business_name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Business Type:</strong> <?php echo htmlspecialchars($merchant['business_type']); ?></p>
                        <p><strong>Address:</strong><br>
                            <?php echo htmlspecialchars($merchant['address']); ?><br>
                            <?php echo htmlspecialchars($merchant['city']); ?>, <?php echo htmlspecialchars($merchant['postal_code']); ?>
                        </p>
                        
                        <?php if (!empty($merchant['latitude']) && !empty($merchant['longitude'])): ?>
                        <div class="mt-3">
                            <a href="https://maps.google.com/?q=<?php echo $merchant['latitude']; ?>,<?php echo $merchant['longitude']; ?>" 
                               target="_blank" class="btn btn-outline-primary w-100">
                                <i class="fas fa-map-marker-alt"></i> View on Map
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <h6>Contact Information</h6>
                        <?php if (isset($business_owner)): ?>
                        <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($business_owner['full_name']); ?></p>
                        <?php if (!empty($business_owner['email'])): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($business_owner['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business_owner['phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($business_owner['phone']); ?></p>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="text-muted">Contact information not available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Purchase Information</h5>
                    </div>
                    <div class="card-body">
                        <p><i class="fas fa-exclamation-triangle text-warning"></i> Remember that purchase limits are enforced by the Pandemic Resilience System.</p>
                        <p>Items you are eligible to purchase today are marked with a green badge.</p>
                        <p>Your PRS-ID will be required at checkout.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-box-open"></i> Available Stock</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($stock_items)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                                <p class="text-muted">This merchant doesn't have any items in stock.</p>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <input type="text" class="form-control" id="itemSearch" placeholder="Search items..." onkeyup="filterItems()">
                            </div>
                            
                            <?php
                            // Group items by category
                            $items_by_category = [];
                            foreach ($stock_items as $item) {
                                $category = $item['item_category'];
                                if (!isset($items_by_category[$category])) {
                                    $items_by_category[$category] = [];
                                }
                                $items_by_category[$category][] = $item;
                            }
                            
                            foreach ($items_by_category as $category => $items):
                            ?>
                                <div class="category-header">
                                    <i class="fas fa-tags"></i> <?php echo htmlspecialchars($category); ?>
                                </div>
                                
                                <?php foreach ($items as $item): 
                                    $has_stock = $item['current_quantity'] > 0;
                                    $is_eligible = in_array($item['item_id'], $eligible_item_ids);
                                    $card_class = $has_stock ? 'item-card' : 'item-card out-of-stock';
                                ?>
                                <div class="card <?php echo $card_class; ?> item-container" data-item-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <div class="item-image">
                                                    <?php 
                                                    // Choose icon based on category
                                                    $icon = 'box';
                                                    if ($category == 'Medical') $icon = 'medkit';
                                                    if ($category == 'Grocery') $icon = 'shopping-basket';
                                                    if ($category == 'Hygiene') $icon = 'pump-soap';
                                                    if ($category == 'Protective') $icon = 'shield-virus';
                                                    ?>
                                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-7">
                                                <h5 class="mb-1">
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                    <?php if ($item['is_restricted']): ?>
                                                        <?php if ($is_eligible): ?>
                                                            <span class="badge eligible-badge ms-2">Eligible Today</span>
                                                        <?php else: ?>
                                                            <span class="badge not-eligible-badge ms-2">Not Eligible Today</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="text-muted mb-1 small"><?php echo htmlspecialchars($item['item_description'] ?: 'No description available'); ?></p>
                                                <?php if ($item['is_restricted']): ?>
                                                <p class="mb-0 small">
                                                    <span class="badge bg-warning text-dark">Restricted Item</span>
                                                    <?php if ($item['max_quantity_per_day']): ?>
                                                    <span class="badge bg-secondary">Max <?php echo $item['max_quantity_per_day']; ?>/day</span>
                                                    <?php endif; ?>
                                                    <?php if ($item['max_quantity_per_week']): ?>
                                                    <span class="badge bg-secondary">Max <?php echo $item['max_quantity_per_week']; ?>/week</span>
                                                    <?php endif; ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <?php if ($has_stock): ?>
                                                <h5 class="mb-1"><?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?></h5>
                                                <p class="text-success mb-0"><i class="fas fa-check-circle"></i> In Stock</p>
                                                <?php else: ?>
                                                <p class="text-danger mb-0"><i class="fas fa-times-circle"></i> Out of Stock</p>
                                                <?php endif; ?>
                                                <?php if ($item['last_restock_date']): ?>
                                                <p class="text-muted small">Restocked: <?php echo date('M j, Y', strtotime($item['last_restock_date'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between mt-3">
            <a href="dashboard_citizen.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
            <a href="citizen_find_supplies.php" class="btn btn-secondary">
                <i class="fas fa-search"></i> Find More Supplies
            </a>
        </div>
    </div>
    
    <script>
        function filterItems() {
            const searchText = document.getElementById('itemSearch').value.toLowerCase();
            const items = document.getElementsByClassName('item-container');
            
            for (let i = 0; i < items.length; i++) {
                const itemName = items[i].getAttribute('data-item-name');
                if (itemName.includes(searchText)) {
                    items[i].style.display = "";
                } else {
                    items[i].style.display = "none";
                }
            }
            
            // Check if any items in a category are visible
            const categories = document.getElementsByClassName('category-header');
            for (let i = 0; i < categories.length; i++) {
                let categoryHasVisibleItems = false;
                let category = categories[i];
                let nextElement = category.nextElementSibling;
                
                while (nextElement && !nextElement.classList.contains('category-header')) {
                    if (nextElement.classList.contains('item-container') && nextElement.style.display !== "none") {
                        categoryHasVisibleItems = true;
                        break;
                    }
                    nextElement = nextElement.nextElementSibling;
                }
                
                category.style.display = categoryHasVisibleItems ? "" : "none";
            }
        }
    </script>
</body>
</html>