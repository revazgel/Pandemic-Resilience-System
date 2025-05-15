<?php
// purchase_controller.php - Update with category filtering

require_once '../Authentication/session_check.php';
if ($_SESSION['role'] !== 'Merchant') { 
    header("Location: ../Authentication/login.html");
    exit(); 
}

// Initialize response data
$data = [
    'error_message' => null,
    'customer_data' => null,
    'eligibility_status' => null,
    'items' => [],
    'merchant' => null
];

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
    $data['merchant'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data['merchant']) { 
        header("Location: dashboard_merchant.php"); 
        exit(); 
    }
    
    $merchant_id = $data['merchant']['merchant_id'];
    
    // Get merchant business type for filtering
    $merchant_business_type = $data['merchant']['business_type'];
    
    // Map business types to item categories
    $category_map = [
        'Pharmacy' => ['Medical'], 
        'Grocery' => ['Grocery'],
        'Supermarket' => ['Grocery', 'Medical'] // Supermarkets can have both
    ];
    
    // Determine which categories this merchant can manage
    $allowed_categories = isset($category_map[$merchant_business_type]) ? 
                         $category_map[$merchant_business_type] : 
                         ['Medical', 'Grocery']; // Fallback to all if type not found
    
    // Build the category filter part of the query
    $category_placeholders = str_repeat('?,', count($allowed_categories) - 1) . '?';
    
    // Load items for dropdown with category filtering
    $query = "SELECT ci.item_id, ci.item_name, s.current_quantity 
              FROM critical_items ci 
              JOIN stock s ON ci.item_id = s.item_id 
              WHERE s.merchant_id = ? 
              AND ci.item_category IN ($category_placeholders)
              AND s.current_quantity > 0 
              ORDER BY ci.item_name";
    
    // Prepare params array - merchant_id followed by allowed categories
    $params = array_merge([$merchant_id], $allowed_categories);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check eligibility
    if (isset($_POST['check_eligibility'])) {
        $prs_id = trim($_POST['prs_id']);
        $item_id = (int)$_POST['item_id'];
        
        // Get user info
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE prs_id = ?");
        $stmt->execute([$prs_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $data['error_message'] = "Customer not found";
        } else {
            // Get item info with category check
            $stmt = $pdo->prepare("
                SELECT ci.*, s.current_quantity 
                FROM critical_items ci
                JOIN stock s ON ci.item_id = s.item_id
                WHERE ci.item_id = ? 
                AND s.merchant_id = ?
                AND ci.item_category IN ($category_placeholders)
            ");
            
            // Parameters: item_id, merchant_id, followed by allowed categories
            $item_params = array_merge([$item_id, $merchant_id], $allowed_categories);
            $stmt->execute($item_params);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                $data['error_message'] = "Item not found or not allowed for this merchant type";
            } else {
                // The rest of the eligibility checking remains the same
                // DOB check, schedule check, purchase limits, etc.
                
                // ... (rest of eligibility code remains unchanged)
                
                // Simplified version for brevity - in the real implementation, 
                // you would keep all the existing eligibility logic below this point
                
                // Check DOB eligibility
                $dob_year = date('Y', strtotime($user['dob']));
                $last_digit = substr($dob_year, -1);
                $today_day = date('N'); // 1-7 (Mon-Sun)
                
                // Get schedules
                $stmt = $pdo->prepare("SELECT dob_year_ending FROM purchase_schedule
                                      WHERE item_id = ? AND day_of_week = ? 
                                      AND CURDATE() BETWEEN effective_from AND IFNULL(effective_to, '9999-12-31')");
                $stmt->execute([$item_id, $today_day]);
                $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Check eligibility
                $is_eligible = false;
                foreach ($schedules as $schedule) {
                    if (strpos($schedule['dob_year_ending'], $last_digit) !== false) {
                        $is_eligible = true;
                        break;
                    }
                }
                
                // Check purchase limits
                $today_start = date('Y-m-d 00:00:00');
                $week_start = date('Y-m-d 00:00:00', strtotime('this week Monday'));
                
                // Daily purchases
                $stmt = $pdo->prepare("SELECT SUM(quantity) as daily_total FROM purchases 
                                      WHERE user_id = ? AND item_id = ? AND purchase_date >= ?");
                $stmt->execute([$user['user_id'], $item_id, $today_start]);
                $daily_total = $stmt->fetchColumn() ?: 0;
                
                // Weekly purchases
                $stmt = $pdo->prepare("SELECT SUM(quantity) as weekly_total FROM purchases 
                                      WHERE user_id = ? AND item_id = ? AND purchase_date >= ?");
                $stmt->execute([$user['user_id'], $item_id, $week_start]);
                $weekly_total = $stmt->fetchColumn() ?: 0;
                
                // Calculate limits
                $daily_limit = $item['max_quantity_per_day'];
                $weekly_limit = $item['max_quantity_per_week'];
                $daily_remaining = max(0, $daily_limit - $daily_total);
                $weekly_remaining = max(0, $weekly_limit - $weekly_total);
                $max_quantity = min($daily_remaining, $weekly_remaining, $item['current_quantity']);
                
                // Prepare result data
                $data['customer_data'] = [
                    'user_id' => $user['user_id'],
                    'full_name' => $user['full_name'],
                    'prs_id' => $user['prs_id'],
                    'dob' => $user['dob'],
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'max_quantity' => $max_quantity,
                    'daily_limit' => $daily_limit,
                    'weekly_limit' => $weekly_limit,
                    'daily_used' => $daily_total,
                    'weekly_used' => $weekly_total
                ];
                
                $data['eligibility_status'] = [
                    'is_eligible' => $is_eligible && $max_quantity > 0,
                    'reason' => !$is_eligible ? 'Not eligible based on DOB' : 
                               ($max_quantity <= 0 ? 'Purchase limits reached' : '')
                ];
            }
        }
    }
    
    // Process purchase - also needs to check the category when updating
    if (isset($_POST['process_purchase'])) {
        $user_id = (int)$_POST['user_id'];
        $item_id = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        $verified_by = trim($_POST['verified_by']);
        
        try {
            // First verify the item is in the allowed category for this merchant
            $stmt = $pdo->prepare("
                SELECT ci.item_id 
                FROM critical_items ci
                WHERE ci.item_id = ? 
                AND ci.item_category IN ($category_placeholders)
            ");
            
            $item_check_params = array_merge([$item_id], $allowed_categories);
            $stmt->execute($item_check_params);
            $valid_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$valid_item) {
                $data['error_message'] = "Item not allowed for this merchant type";
            } else {
                $pdo->beginTransaction();
                
                // Insert purchase
                $stmt = $pdo->prepare("INSERT INTO purchases (user_id, merchant_id, item_id, quantity, verified_by)
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $merchant_id, $item_id, $quantity, $verified_by]);
                
                // Update stock
                $stmt = $pdo->prepare("UPDATE stock SET current_quantity = current_quantity - ?
                                      WHERE merchant_id = ? AND item_id = ?");
                $stmt->execute([$quantity, $merchant_id, $item_id]);
                
                $pdo->commit();
                $_SESSION['success_message'] = "Purchase processed successfully!";
                header("Location: merchant_purchases.php");
                exit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $data['error_message'] = "Error: " . $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    $data['error_message'] = "Database error: " . $e->getMessage();
}

return $data;