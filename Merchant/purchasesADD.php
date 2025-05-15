<?php
// purchasesADD.php - View file that handles UI presentation
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Merchant') {
    header("Location: ../Authentication/login.html");
    exit();
}

// Initialize variables
$error_message = '';
$success_message = '';
$items = [];
$customer_data = null;
$eligibility_status = null;

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
    $stmt = $pdo->prepare("SELECT merchant_id, business_name FROM merchants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle missing merchant profile (admin view)
    if (!$merchant) {
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin') {
            // Create mock merchant for admin view
            $merchant = [
                'merchant_id' => 0,
                'business_name' => 'Admin View Mode'
            ];
            $_SESSION['info_message'] = "Admin viewing mode - Purchase processing is not available without a merchant profile.";
        } else {
            $error_message = "Merchant profile not found.";
        }
    }
    
    $merchant_id = $merchant['merchant_id'];
    
    // Get available items for this merchant (only if we have a real merchant)
    if ($merchant_id > 0) {
        $stmt = $pdo->prepare("
            SELECT ci.item_id, ci.item_name, s.current_quantity 
            FROM critical_items ci 
            JOIN stock s ON ci.item_id = s.item_id 
            WHERE s.merchant_id = ? AND s.current_quantity > 0
            ORDER BY ci.item_name
        ");
        $stmt->execute([$merchant_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Process eligibility check (only if we have a real merchant)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_eligibility']) && $merchant_id > 0) {
        if (!empty($_POST['prs_id']) && !empty($_POST['item_id'])) {
            $prs_id = $_POST['prs_id'];
            $item_id = (int)$_POST['item_id'];
            
            // Get user info
            $stmt = $pdo->prepare("SELECT user_id, full_name, prs_id, dob FROM Users WHERE prs_id = ?");
            $stmt->execute([$prs_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error_message = "Customer with PRS-ID '".htmlspecialchars($prs_id)."' not found.";
            } else {
                // Get item info
                $stmt = $pdo->prepare("SELECT * FROM critical_items WHERE item_id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item) {
                    $error_message = "Item not found.";
                } else {
                    // Check DOB-based eligibility
                    $dob_year = date('Y', strtotime($user['dob']));
                    $last_digit = substr($dob_year, -1);
                    $today_day = date('N'); // 1-7 for Monday-Sunday
                    
                    // Check purchase schedule
                    $stmt = $pdo->prepare("
                        SELECT * FROM purchase_schedule 
                        WHERE item_id = ? AND day_of_week = ?
                        AND FIND_IN_SET(?, dob_year_ending) > 0
                        AND CURDATE() BETWEEN effective_from AND IFNULL(effective_to, '9999-12-31')
                    ");
                    $stmt->execute([$item_id, $today_day, $last_digit]);
                    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check purchase history
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(quantity), 0) as daily_total
                        FROM purchases
                        WHERE user_id = ? AND item_id = ? AND DATE(purchase_date) = CURDATE()
                    ");
                    $stmt->execute([$user['user_id'], $item_id]);
                    $daily_total = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(quantity), 0) as weekly_total
                        FROM purchases
                        WHERE user_id = ? AND item_id = ? 
                        AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    ");
                    $stmt->execute([$user['user_id'], $item_id]);
                    $weekly_total = $stmt->fetchColumn();
                    
                    // Determine eligibility
                    $daily_remaining = max(0, $item['max_quantity_per_day'] - $daily_total);
                    $weekly_remaining = max(0, $item['max_quantity_per_week'] - $weekly_total);
                    $max_quantity = min($daily_remaining, $weekly_remaining);
                    
                    $is_eligible = ($schedule && $max_quantity > 0);
                    
                    // If not eligible, determine reason
                    $reason = '';
                    if (!$schedule) {
                        // Get eligible days for this birth year ending
                        $stmt = $pdo->prepare("
                            SELECT day_of_week FROM purchase_schedule
                            WHERE item_id = ? AND FIND_IN_SET(?, dob_year_ending) > 0
                            AND CURDATE() BETWEEN effective_from AND IFNULL(effective_to, '9999-12-31')
                            GROUP BY day_of_week
                        ");
                        $stmt->execute([$item_id, $last_digit]);
                        $eligible_days = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        $eligible_day_names = [];
                        foreach ($eligible_days as $day) {
                            $eligible_day_names[] = $day_names[$day-1];
                        }
                        
                        $reason = "Not scheduled for today. Birth year ending in $last_digit can purchase on: ";
                        $reason .= empty($eligible_day_names) ? "no days available" : implode(', ', $eligible_day_names);
                    } elseif ($daily_total >= $item['max_quantity_per_day']) {
                        $reason = "Daily purchase limit reached ({$item['max_quantity_per_day']} items).";
                    } elseif ($weekly_total >= $item['max_quantity_per_week']) {
                        $reason = "Weekly purchase limit reached ({$item['max_quantity_per_week']} items).";
                    }
                    
                    // Set customer data
                    $customer_data = [
                        'user_id' => $user['user_id'],
                        'full_name' => $user['full_name'],
                        'prs_id' => $user['prs_id'],
                        'item_id' => $item['item_id'],
                        'item_name' => $item['item_name'],
                        'max_quantity' => $max_quantity
                    ];
                    
                    // Set eligibility status
                    $eligibility_status = [
                        'is_eligible' => $is_eligible,
                        'reason' => $reason
                    ];
                }
            }
        } else {
            $error_message = "Please enter PRS-ID and select an item.";
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_eligibility']) && $merchant_id === 0) {
        $error_message = "Cannot check eligibility in admin view mode. Please use a real merchant account.";
    }
    
    // Process purchase form submission (only if we have a real merchant)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_purchase']) && $merchant_id > 0) {
        if (isset($_POST['user_id']) && isset($_POST['item_id']) && isset($_POST['quantity']) && isset($_POST['verified_by'])) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Get info about the purchase for confirmation
                $stmt = $pdo->prepare("
                    SELECT u.full_name, ci.item_name 
                    FROM Users u, critical_items ci 
                    WHERE u.user_id = ? AND ci.item_id = ?
                ");
                $stmt->execute([$_POST['user_id'], $_POST['item_id']]);
                $info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Insert purchase record
                $stmt = $pdo->prepare("
                    INSERT INTO purchases (user_id, merchant_id, item_id, quantity, verified_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['user_id'], 
                    $merchant_id, 
                    $_POST['item_id'], 
                    $_POST['quantity'], 
                    $_POST['verified_by']
                ]);
                
                // Update stock
                $stmt = $pdo->prepare("
                    UPDATE stock 
                    SET current_quantity = current_quantity - ? 
                    WHERE merchant_id = ? AND item_id = ?
                ");
                $stmt->execute([$_POST['quantity'], $merchant_id, $_POST['item_id']]);
                
                // Commit transaction
                $pdo->commit();
                
                // Set success message
                $success_message = "Purchase processed successfully! " . 
                                  htmlspecialchars($info['full_name']) . " purchased " . 
                                  $_POST['quantity'] . " " . htmlspecialchars($info['item_name']) . ".";
                
                // Reset form data
                $customer_data = null;
                $eligibility_status = null;
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $error_message = "Failed to process purchase: " . $e->getMessage();
            }
        } else {
            $error_message = "Missing required data for purchase.";
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_purchase']) && $merchant_id === 0) {
        $error_message = "Cannot process purchases in admin view mode. Please use a real merchant account.";
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Purchase</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-cash-register me-2"></i> Process Purchase</h2>
            <a href="dashboard_merchant.php" class="btn btn-light btn-sm">
                <i class="fas fa-home me-1"></i> Dashboard
            </a>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info fade-in" role="alert">
                <i class="fas fa-info-circle me-2"></i> <?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($merchant_id === 0): ?>
            <div class="alert alert-warning fade-in">
                <i class="fas fa-eye"></i> <strong>Admin View Mode:</strong> Purchase processing is not available. This is a demonstration view only.
            </div>
        <?php endif; ?>
        
        <div class="card fade-in">
            <div class="card-body">
                <h5 class="card-title mb-3">Check Customer Eligibility</h5>
                
                <?php if ($merchant_id > 0): ?>
                    <form method="post">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="prs_id" class="form-label">Customer PRS-ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" name="prs_id" id="prs_id" class="form-control" required 
                                           value="<?php echo isset($_POST['prs_id']) ? htmlspecialchars($_POST['prs_id']) : ''; ?>"
                                           placeholder="Enter PRS-ID">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="item_id" class="form-label">Select Item</label>
                                <select name="item_id" id="item_id" class="form-select" required>
                                    <option value="">-- Select Item --</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['item_id']; ?>" 
                                                <?php echo (isset($_POST['item_id']) && $_POST['item_id'] == $item['item_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['item_name']); ?> 
                                            (<?php echo $item['current_quantity']; ?> in stock)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="check_eligibility" class="btn btn-primary">
                            <i class="fas fa-check-circle me-1"></i> Check Eligibility
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Customer eligibility checking is not available in admin view mode.
                    </div>
                    
                    <!-- Show mock form for demonstration -->
                    <form>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="prs_id_demo" class="form-label">Customer PRS-ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" id="prs_id_demo" class="form-control" disabled placeholder="Not available in admin view">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="item_id_demo" class="form-label">Select Item</label>
                                <select id="item_id_demo" class="form-select" disabled>
                                    <option value="">-- Not available in admin view --</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" disabled>
                            <i class="fas fa-check-circle me-1"></i> Check Eligibility (Disabled)
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($customer_data && $eligibility_status): ?>
                    <div class="customer-details mt-4 fade-in">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-2"><?php echo htmlspecialchars($customer_data['full_name']); ?></h5>
                                <p class="mb-1"><i class="fas fa-fingerprint me-2"></i> PRS-ID: <?php echo htmlspecialchars($customer_data['prs_id']); ?></p>
                                <p class="mb-0"><i class="fas fa-box me-2"></i> Item: <?php echo htmlspecialchars($customer_data['item_name']); ?></p>
                            </div>
                            <div>
                                <?php if ($eligibility_status['is_eligible']): ?>
                                    <div class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i> Eligible
                                    </div>
                                <?php else: ?>
                                    <div class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i> Not Eligible
                                    </div>
                                    <p class="small text-danger mt-2"><?php echo $eligibility_status['reason']; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($eligibility_status['is_eligible']): ?>
                        <div class="mt-3 pt-3 border-top fade-in">
                            <h5 class="mb-3"><i class="fas fa-shopping-cart me-2"></i> Complete Purchase</h5>
                            <form method="post">
                                <input type="hidden" name="user_id" value="<?php echo $customer_data['user_id']; ?>">
                                <input type="hidden" name="item_id" value="<?php echo $customer_data['item_id']; ?>">
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="quantity" class="form-label">Quantity</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span>
                                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" 
                                                max="<?php echo $customer_data['max_quantity']; ?>" value="1" required>
                                        </div>
                                        <div class="form-text text-muted">Maximum allowed: <?php echo $customer_data['max_quantity']; ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="verified_by" class="form-label">Verified By</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-check"></i></span>
                                            <input type="text" name="verified_by" id="verified_by" class="form-control" 
                                                value="<?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : ''; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="process_purchase" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i> Process Purchase
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-nav">
            <a href="merchant_stock.php" class="btn btn-outline-secondary">
                <i class="fas fa-boxes me-1"></i> Inventory
            </a>
            <a href="merchant_purchases.php" class="btn btn-outline-secondary">
                <i class="fas fa-history me-1"></i> Purchase History
            </a>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const prsIdField = document.getElementById('prs_id');
        const quantityField = document.getElementById('quantity');
        
        if (prsIdField && !prsIdField.value) prsIdField.focus();
        if (quantityField) quantityField.focus();
    });
    </script>
</body>
</html>