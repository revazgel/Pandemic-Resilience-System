<?php
header("Content-Type: application/json");

// Check if session is active
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Merchant') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

// Validate required parameters
if (!isset($_GET['prs_id']) || empty($_GET['prs_id'])) {
    echo json_encode(['error' => 'PRS-ID is required']);
    exit;
}

if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    echo json_encode(['error' => 'Valid item ID is required']);
    exit;
}

$prs_id = trim($_GET['prs_id']);
$item_id = (int)$_GET['item_id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user by PRS-ID
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE prs_id = ?");
    $stmt->execute([$prs_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found with the provided PRS-ID']);
        exit;
    }
    
    // Get item details
    $stmt = $pdo->prepare("SELECT * FROM critical_items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['error' => 'Item not found']);
        exit;
    }
    
    // Get today's day of week (1-7, Monday-Sunday)
    $today_day = date('N');
    
    // Get birth year last digit
    $dob_year = date('Y', strtotime($user['dob']));
    $last_digit = substr($dob_year, -1);
    
    // Check if user is eligible to purchase today
    $stmt = $pdo->prepare("
        SELECT ps.day_of_week, ps.dob_year_ending
        FROM purchase_schedule ps
        WHERE ps.item_id = ?
        AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')
    ");
    $stmt->execute([$item_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Determine eligibility
    $is_eligible = false;
    $eligible_days = [];
    $eligible_days_text = '';
    
    // Map day numbers to names
    $day_names = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];
    
    foreach ($schedules as $schedule) {
        // Add to eligible days list
        if (!in_array($day_names[$schedule['day_of_week']], $eligible_days)) {
            $eligible_days[] = $day_names[$schedule['day_of_week']];
        }
        
        // Check if today matches scheduled day and DOB year ending matches
        if ($schedule['day_of_week'] == $today_day && 
            strpos($schedule['dob_year_ending'], $last_digit) !== false) {
            $is_eligible = true;
        }
    }
    
    // Format eligible days text
    if (count($eligible_days) > 0) {
        if (count($eligible_days) == 1) {
            $eligible_days_text = $eligible_days[0];
        } else {
            $last_day = array_pop($eligible_days);
            $eligible_days_text = implode(', ', $eligible_days) . ' or ' . $last_day;
        }
    } else {
        $eligible_days_text = 'No scheduled days found';
    }
    
    // Check purchase history for daily/weekly limits
    $today_start = date('Y-m-d 00:00:00');
    $week_start = date('Y-m-d 00:00:00', strtotime('this week Monday'));
    
    // Check daily purchases
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as daily_total 
        FROM purchases 
        WHERE user_id = ? AND item_id = ? AND purchase_date >= ?
    ");
    $stmt->execute([$user['user_id'], $item_id, $today_start]);
    $daily_purchases = $stmt->fetch(PDO::FETCH_ASSOC);
    $daily_total = $daily_purchases['daily_total'] ?: 0;
    
    // Check weekly purchases
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as weekly_total 
        FROM purchases 
        WHERE user_id = ? AND item_id = ? AND purchase_date >= ?
    ");
    $stmt->execute([$user['user_id'], $item_id, $week_start]);
    $weekly_purchases = $stmt->fetch(PDO::FETCH_ASSOC);
    $weekly_total = $weekly_purchases['weekly_total'] ?: 0;
    
    // Calculate remaining purchase limits
    $daily_limit = $item['max_quantity_per_day'];
    $weekly_limit = $item['max_quantity_per_week'];
    
    $daily_remaining = max(0, $daily_limit - $daily_total);
    $weekly_remaining = max(0, $weekly_limit - $weekly_total);
    
    $max_quantity = min($daily_remaining, $weekly_remaining);
    
    // If user has already reached their limit, they're not eligible
    if ($max_quantity <= 0) {
        $is_eligible = false;
    }
    
    // Prepare response
    $response = [
        'prs_id' => $user['prs_id'],
        'full_name' => $user['full_name'],
        'dob' => date('Y-m-d', strtotime($user['dob'])),
        'item_id' => $item['item_id'],
        'item_name' => $item['item_name'],
        'is_eligible' => $is_eligible,
        'eligible_days' => $eligible_days_text,
        'max_quantity' => $max_quantity,
        'daily_limit' => $daily_limit,
        'weekly_limit' => $weekly_limit,
        'daily_used' => $daily_total,
        'weekly_used' => $weekly_total
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>