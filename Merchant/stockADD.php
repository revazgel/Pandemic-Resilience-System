<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Merchant') {
    header("Location: ../Authentication/login.html");
    exit();
}

// DB connection
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
    
    // Handle case where merchant doesn't exist (e.g., admin viewing as merchant)
    if (!$merchant) {
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin') {
            // Create a mock merchant for admin viewing
            $merchant = [
                'merchant_id' => 0,
                'business_name' => 'Admin View Mode',
                'business_type' => 'Administration',
                'address' => 'System Admin View'
            ];
            $_SESSION['info_message'] = "Admin viewing mode - Some features may be limited without a merchant profile.";
        } else {
            $_SESSION['error_message'] = "Merchant profile not found.";
            header("Location: dashboard_merchant.php");
            exit();
        }
    }
    
    $merchant_id = $merchant['merchant_id'];
    
    // Edit mode & item details
    $edit_mode = isset($_GET['item_id']) && is_numeric($_GET['item_id']);
    $selected_item = null;
    
    if ($edit_mode && $merchant_id > 0) {
        $stmt = $pdo->prepare("SELECT s.*, ci.item_name, ci.item_category, ci.unit_of_measure 
                             FROM stock s JOIN critical_items ci ON s.item_id = ci.item_id 
                             WHERE s.merchant_id = ? AND s.item_id = ?");
        $stmt->execute([$merchant_id, $_GET['item_id']]);
        $selected_item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get allowed items by business type
    $category_map = [
        'Pharmacy' => ['Medical'], 
        'Grocery' => ['Grocery'],
        'Supermarket' => ['Grocery', 'Medical'],
        'Administration' => ['Medical', 'Grocery'] // For admin view
    ];
    
    $business_type = $merchant['business_type'];
    $allowed_categories = isset($category_map[$business_type]) ? $category_map[$business_type] : ['Medical', 'Grocery'];
    
    $placeholders = str_repeat('?,', count($allowed_categories) - 1) . '?';
    $stmt = $pdo->prepare("SELECT item_id, item_name, item_category, unit_of_measure 
                          FROM critical_items 
                          WHERE item_category IN ($placeholders)
                          ORDER BY item_category, item_name");
    $stmt->execute($allowed_categories);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $merchant_id > 0) {
        try {
            $item_id = (int)$_POST['item_id'];
            $quantity = (int)$_POST['quantity'];
            $stock_action = $_POST['stock_action'];
            $last_restock_date = !empty($_POST['last_restock_date']) ? $_POST['last_restock_date'] : date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("SELECT stock_id, current_quantity FROM stock WHERE merchant_id = ? AND item_id = ?");
            $stmt->execute([$merchant_id, $item_id]);
            $existing_stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_stock) {
                $new_quantity = ($stock_action === 'add') ? $existing_stock['current_quantity'] + $quantity : $quantity;
                $stmt = $pdo->prepare("UPDATE stock SET current_quantity = ?, last_restock_date = ? WHERE stock_id = ?");
                $stmt->execute([$new_quantity, $last_restock_date, $existing_stock['stock_id']]);
                $action_text = ($stock_action === 'add') ? "Added $quantity to" : "Set";
                $success_message = "Stock updated! $action_text stock level to $new_quantity.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO stock (merchant_id, item_id, current_quantity, last_restock_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$merchant_id, $item_id, $quantity, $last_restock_date]);
                $success_message = "Item added to inventory with quantity $quantity!";
            }
            
            header("Location: merchant_stock.php?success=" . urlencode($success_message));
            exit();
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error updating stock: " . $e->getMessage();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $merchant_id === 0) {
        $_SESSION['error_message'] = "Cannot update stock in admin view mode. Please use a real merchant account.";
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? 'Update' : 'Add' ?> Stock - PRS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="page-header fade-in">
            <h2><i class="fas fa-plus-circle"></i> <?= $edit_mode ? 'Update' : 'Add' ?> Stock</h2>
            <a href="merchant_stock.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>

        <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert alert-info fade-in">
            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($_SESSION['info_message']); unset($_SESSION['info_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger fade-in">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger fade-in">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
        </div>
        <?php endif; ?>

        <div class="card fade-in">
            <div class="card-body">
                <h5 class="card-title"><?= $edit_mode ? 'Update Item Stock' : 'Add New Item to Inventory' ?></h5>
                
                <?php if ($merchant_id === 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> You are in admin view mode. Stock operations are not available.
                    </div>
                <?php else: ?>
                    <form method="POST" action="stockADD.php">
                        <!-- Item Selection -->
                        <div class="mb-3">
                            <label class="form-label">Item</label>
                            <?php if ($edit_mode && $selected_item): ?>
                                <select class="form-select" disabled>
                                    <option><?= htmlspecialchars($selected_item['item_name']) ?> (<?= htmlspecialchars($selected_item['item_category']) ?>)</option>
                                </select>
                                <input type="hidden" name="item_id" value="<?= $selected_item['item_id'] ?>">
                                
                                <div class="customer-details mt-2">
                                    <h5>Item Details</h5>
                                    <p><i class="fas fa-tag"></i> <strong>Category:</strong> <?= htmlspecialchars($selected_item['item_category']) ?></p>
                                    <p><i class="fas fa-ruler-combined"></i> <strong>Unit:</strong> <?= htmlspecialchars($selected_item['unit_of_measure']) ?></p>
                                    <p><i class="fas fa-boxes"></i> <strong>Current Quantity:</strong> <?= $selected_item['current_quantity'] ?></p>
                                </div>
                            <?php else: ?>
                                <select id="item_id" name="item_id" class="form-select" required>
                                    <option value="" disabled selected>-- Select an Item --</option>
                                    <?php foreach ($all_items as $item): ?>
                                        <option value="<?= $item['item_id'] ?>" 
                                                data-category="<?= htmlspecialchars($item['item_category']) ?>"
                                                data-unit="<?= htmlspecialchars($item['unit_of_measure']) ?>">
                                            <?= htmlspecialchars($item['item_name']) ?> 
                                            (<?= htmlspecialchars($item['item_category']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <div id="itemDetails" class="customer-details" style="display: none;">
                                    <h5>Item Details</h5>
                                    <p><i class="fas fa-tag"></i> <strong>Category:</strong> <span id="itemCategory">-</span></p>
                                    <p><i class="fas fa-ruler-combined"></i> <strong>Unit:</strong> <span id="itemUnit">-</span></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stock Action Options -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="mb-3">Stock Action</h6>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="stock_action" id="actionAdd" value="add" checked>
                                    <label class="form-check-label" for="actionAdd">
                                        <strong>Add to existing stock</strong>
                                        <div class="form-text">
                                            <?php if ($edit_mode && $selected_item): ?>
                                                Add to current stock of <?= $selected_item['current_quantity'] ?>
                                            <?php else: ?>
                                                Add to whatever is currently in stock
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="stock_action" id="actionSet" value="set">
                                    <label class="form-check-label" for="actionSet">
                                        <strong>Set total stock</strong>
                                        <div class="form-text">Set to exactly this amount</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quantity and Date -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="number" id="quantity" name="quantity" class="form-control" min="0" required>
                                </div>
                                <?php if ($edit_mode && $selected_item): ?>
                                    <div class="form-text" id="quantityHelp">
                                        <span id="addHelp">Will be added to current stock of <?= $selected_item['current_quantity'] ?></span>
                                        <span id="setHelp" style="display: none;">Will replace current stock of <?= $selected_item['current_quantity'] ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_restock_date" class="form-label">Last Restock Date</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="datetime-local" id="last_restock_date" name="last_restock_date" class="form-control">
                                </div>
                                <div class="form-text">Leave empty for current date/time</div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-save"></i> <?= $edit_mode ? 'Update' : 'Add' ?> Stock
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-nav fade-in">
            <a href="merchant_stock.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <a href="dashboard_merchant.php" class="btn btn-outline-primary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Item selection handler
            const itemSelect = document.getElementById('item_id');
            if (itemSelect) {
                itemSelect.addEventListener('change', function() {
                    const details = document.getElementById('itemDetails');
                    if (this.value) {
                        const option = this.options[this.selectedIndex];
                        document.getElementById('itemCategory').textContent = option.dataset.category;
                        document.getElementById('itemUnit').textContent = option.dataset.unit;
                        details.style.display = 'block';
                    } else {
                        details.style.display = 'none';
                    }
                });
            }
            
            // Stock action change handler
            const addAction = document.getElementById('actionAdd');
            const setAction = document.getElementById('actionSet');
            const addHelp = document.getElementById('addHelp');
            const setHelp = document.getElementById('setHelp');
            
            if (addAction && setAction && addHelp && setHelp) {
                function updateHelpText() {
                    addHelp.style.display = addAction.checked ? 'inline' : 'none';
                    setHelp.style.display = setAction.checked ? 'inline' : 'none';
                }
                addAction.addEventListener('change', updateHelpText);
                setAction.addEventListener('change', updateHelpText);
            }
            
            // Set current date as default
            const dateInput = document.getElementById('last_restock_date');
            if (dateInput) dateInput.value = new Date().toISOString().slice(0, 16);
            
            // Fade-in animation
            document.querySelectorAll('.fade-in').forEach(function(element) {
                element.style.opacity = '0';
                setTimeout(() => element.style.opacity = '1', 100);
            });
        });
    </script>
</body>
</html>