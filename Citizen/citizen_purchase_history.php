<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Citizen') {
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
    
    // Get user's purchase history
    $query = "
        SELECT p.purchase_id, p.quantity, p.purchase_date, p.verified_by,
               ci.item_id, ci.item_name, ci.item_category, ci.unit_of_measure,
               m.merchant_id, m.business_name, m.business_type
        FROM purchases p
        JOIN critical_items ci ON p.item_id = ci.item_id
        JOIN merchants m ON p.merchant_id = m.merchant_id
        WHERE p.user_id = ?
        ORDER BY p.purchase_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Purchase History - COVID Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    <div class="container">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-history"></i> My Purchase History</h2>
            <a href="dashboard_citizen.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Filter Options</h5>
                <div class="row">
                    <div class="col-md-4">
                        <label for="itemFilter" class="form-label">Filter by Item:</label>
                        <select id="itemFilter" class="form-select">
                            <option value="">All Items</option>
                            <?php 
                                $uniqueItems = [];
                                foreach ($purchases as $purchase) {
                                    $itemId = $purchase['item_id'];
                                    if (!isset($uniqueItems[$itemId])) {
                                        $uniqueItems[$itemId] = $purchase['item_name'];
                                        echo '<option value="' . $itemId . '">' . htmlspecialchars($purchase['item_name']) . '</option>';
                                    }
                                }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="merchantFilter" class="form-label">Filter by Merchant:</label>
                        <select id="merchantFilter" class="form-select">
                            <option value="">All Merchants</option>
                            <?php 
                                $uniqueMerchants = [];
                                foreach ($purchases as $purchase) {
                                    $merchantId = $purchase['merchant_id'];
                                    if (!isset($uniqueMerchants[$merchantId])) {
                                        $uniqueMerchants[$merchantId] = $purchase['business_name'];
                                        echo '<option value="' . $merchantId . '">' . htmlspecialchars($purchase['business_name']) . '</option>';
                                    }
                                }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="dateFilter" class="form-label">Filter by Date Range:</label>
                        <select id="dateFilter" class="form-select">
                            <option value="">All Time</option>
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 3 Months</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="purchasesContainer">
            <?php if (empty($purchases)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x mb-3 text-muted"></i>
                    <p class="text-muted">You haven't made any purchases yet.</p>
                </div>
            <?php else: ?>
                <h4 class="mb-3">Purchase Records</h4>
                
                <?php foreach ($purchases as $purchase): ?>
                    <div class="card purchase-card" 
                         data-item-id="<?php echo $purchase['item_id']; ?>" 
                         data-merchant-id="<?php echo $purchase['merchant_id']; ?>"
                         data-purchase-date="<?php echo strtotime($purchase['purchase_date']); ?>">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><?php echo htmlspecialchars($purchase['item_name']); ?></h5>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($purchase['item_category']); ?> • <?php echo htmlspecialchars($purchase['unit_of_measure']); ?></p>
                                    <p class="mb-1">
                                        <strong>Quantity:</strong> <?php echo $purchase['quantity']; ?>
                                    </p>
                                    <p class="purchase-date">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <?php echo date('F j, Y, g:i a', strtotime($purchase['purchase_date'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <strong>Merchant:</strong> <?php echo htmlspecialchars($purchase['business_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Business Type:</strong> <?php echo htmlspecialchars($purchase['business_type']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Verified By:</strong> <?php echo htmlspecialchars($purchase['verified_by']); ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Purchase ID:</strong> <?php echo $purchase['purchase_id']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
            
            // Get filter elements
            const itemFilter = document.getElementById("itemFilter");
            const merchantFilter = document.getElementById("merchantFilter");
            const dateFilter = document.getElementById("dateFilter");
            const purchaseCards = document.querySelectorAll(".purchase-card");
            
            // Apply filters when any filter changes
            [itemFilter, merchantFilter, dateFilter].forEach(filter => {
                filter.addEventListener("change", applyFilters);
            });
            
            function applyFilters() {
                const selectedItemId = itemFilter.value;
                const selectedMerchantId = merchantFilter.value;
                const selectedDateRange = dateFilter.value;
                
                // Calculate date threshold if date filter is selected
                let dateThreshold = 0;
                if (selectedDateRange) {
                    const daysAgo = parseInt(selectedDateRange);
                    const now = new Date();
                    dateThreshold = Math.floor(now.getTime() / 1000) - (daysAgo * 24 * 60 * 60);
                }
                
                // Filter each purchase card
                purchaseCards.forEach(card => {
                    const cardItemId = card.dataset.itemId;
                    const cardMerchantId = card.dataset.merchantId;
                    const cardPurchaseDate = parseInt(card.dataset.purchaseDate);
                    
                    // Check if card matches all selected filters
                    let showCard = true;
                    
                    if (selectedItemId && cardItemId !== selectedItemId) {
                        showCard = false;
                    }
                    
                    if (selectedMerchantId && cardMerchantId !== selectedMerchantId) {
                        showCard = false;
                    }
                    
                    if (dateThreshold > 0 && cardPurchaseDate < dateThreshold) {
                        showCard = false;
                    }
                    
                    // Show or hide the card
                    card.style.display = showCard ? "" : "none";
                });
                
                // Check if any cards are visible after filtering
                const visibleCards = Array.from(purchaseCards).filter(card => card.style.display !== "none");
                const purchasesContainer = document.getElementById("purchasesContainer");
                
                if (visibleCards.length === 0 && purchaseCards.length > 0) {
                    // No matching records found
                    const noResultsMsg = document.createElement("div");
                    noResultsMsg.className = "text-center py-4 no-results-message";
                    noResultsMsg.innerHTML = `
                        <i class="fas fa-search fa-2x mb-3 text-muted"></i>
                        <p class="text-muted">No purchase records match your selected filters.</p>
                    `;
                    
                    // Remove any existing "no results" message
                    const existingNoResults = purchasesContainer.querySelector(".no-results-message");
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                    
                    // Add the message after the heading
                    const heading = purchasesContainer.querySelector("h4");
                    if (heading) {
                        heading.after(noResultsMsg);
                    }
                } else {
                    // Remove any existing "no results" message
                    const existingNoResults = purchasesContainer.querySelector(".no-results-message");
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                }
            }
        });
    </script>
</body>
</html>