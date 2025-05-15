<?php
/**
 * Helper functions for checking item eligibility
 */

/**
 * Check if a user is eligible to purchase an item today based on their DOB
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID to check
 * @param int $item_id Item ID to check (optional - if not provided, checks any item eligibility)
 * @return array Array with eligibility information
 */
function checkUserEligibility($pdo, $user_id, $item_id = null) {
    // Get user's DOB
    $stmt = $pdo->prepare("SELECT dob FROM Users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return [
            'eligible' => false,
            'items' => [],
            'message' => 'User not found'
        ];
    }
    
    // Get birth year last digit
    $dob_year = date('Y', strtotime($user['dob']));
    $last_digit = substr($dob_year, -1);
    
    // Get today's day of week (1-7, Monday-Sunday)
    $today_day = date('N');
    
    // Base query for eligible items
    $query = "
        SELECT ci.item_id, ci.item_name
        FROM purchase_schedule ps
        JOIN critical_items ci ON ps.item_id = ci.item_id
        WHERE ps.day_of_week = ?
        AND ps.dob_year_ending LIKE ?
        AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')
    ";
    $params = [$today_day, "%$last_digit%"];
    
    // Add item filter if specified
    if ($item_id !== null) {
        $query .= " AND ps.item_id = ?";
        $params[] = $item_id;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $eligible_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return eligibility data
    return [
        'eligible' => count($eligible_items) > 0,
        'items' => $eligible_items,
        'birth_year' => $dob_year,
        'last_digit' => $last_digit,
        'day_of_week' => $today_day
    ];
}

/**
 * Get eligible days for an item based on user's DOB
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID to check
 * @param int $item_id Item ID to check
 * @return array Eligible days for this item
 */
function getItemEligibleDays($pdo, $user_id, $item_id) {
    // Get user's DOB
    $stmt = $pdo->prepare("SELECT dob FROM Users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return [];
    }
    
    // Get birth year last digit
    $dob_year = date('Y', strtotime($user['dob']));
    $last_digit = substr($dob_year, -1);
    
    // Get eligible days
    $query = "
        SELECT ps.day_of_week
        FROM purchase_schedule ps
        WHERE ps.item_id = ?
        AND ps.dob_year_ending LIKE ?
        AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')
        ORDER BY ps.day_of_week
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$item_id, "%$last_digit%"]);
    $eligible_days = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
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
    
    $day_name_array = [];
    foreach ($eligible_days as $day) {
        $day_name_array[] = $day_names[$day];
    }
    
    return $day_name_array;
}
?>