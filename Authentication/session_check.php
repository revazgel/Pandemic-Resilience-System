<?php
// Modified session_check.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../Authentication/login.html");
    exit();
}

// For Admin role switching, check if viewing as a different role
$is_admin_viewing = false;
if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin' && isset($_SESSION['temp_role'])) {
    $is_admin_viewing = true;
    
    // Add a "Return to Admin" button at the top of pages - THIS NEEDS TO BE VISIBLE ON ALL PAGES
    echo '
    <div style="position: fixed; top: 10px; right: 10px; z-index: 9999;">
        <form action="../Admin/switch_role.php" method="post">
            <input type="hidden" name="target_role" value="Admin">
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="fas fa-user-shield"></i> Return to Admin
            </button>
        </form>
    </div>
    ';
}

// Skip role-specific checks if admin is viewing as another role
if (!$is_admin_viewing) {
    // Role-specific redirects for pages that shouldn't be accessed by certain roles
    $currentPage = basename($_SERVER['PHP_SELF']);

    // Pages only accessible to Officials
    $officialOnlyPages = [
        'government_officials.php', 
        'government_officialsADD.php',
        'dashboard_official.php'
    ];

    // Pages only accessible to Merchants
    $merchantOnlyPages = [
        'merchant_stock.php',
        'dashboard_merchant.php'
    ];

    // Pages only accessible to Citizens
    $citizenOnlyPages = [
        'citizen_find_supplies.php',
        'citizen_purchase_history.php',
        'citizen_vaccinations.php',
        'citizen_upload_vaccination.php',
        'dashboard_citizen.php'
    ];
    
    // Pages only accessible to Admins
    $adminOnlyPages = [
        'admin_dashboard.php',
        'admin_users.php',
        'admin_approvals.php',
        'admin_system.php',
        'admin_logs.php',
        'admin_profile.php'
    ];

    // Check role-specific access
    if (in_array($currentPage, $officialOnlyPages) && $_SESSION['role'] !== 'Official') {
        header("Location: ../Authentication/login.html");
        exit();
    }

    if (in_array($currentPage, $merchantOnlyPages) && $_SESSION['role'] !== 'Merchant') {
        header("Location: ../Authentication/login.html");
        exit();
    }

    if (in_array($currentPage, $citizenOnlyPages) && $_SESSION['role'] !== 'Citizen') {
        header("Location: ../Authentication/login.html");
        exit();
    }
    
    if (in_array($currentPage, $adminOnlyPages) && $_SESSION['role'] !== 'Admin') {
        header("Location: ../Authentication/login.html");
        exit();
    }
}
?>