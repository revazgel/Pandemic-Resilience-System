<?php
// Get current page to highlight active nav item
$current_page = basename($_SERVER['PHP_SELF']);

// Only try to fetch merchant info if session is already started
$merchant_name = '';
$merchant_address = '';

if (isset($_SESSION['user_id'])) {
    try {
        global $pdo;
        // Check if $pdo exists, if not, create connection
        if (!isset($pdo)) {
            $host = 'localhost';
            $db = 'CovidSystem';
            $user = 'root';
            $pass = '';
            $port = 3307;
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        $stmt = $pdo->prepare("SELECT business_name, address FROM merchants WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $merchant_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($merchant_info) {
            $merchant_name = $merchant_info['business_name'];
            $merchant_address = $merchant_info['address'];
        }
    } catch (Exception $e) {
        // Silently fail - navbar will still work without the merchant info
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard_merchant.php">
            <i class="fas fa-shield-virus"></i> PRS Merchant
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" 
                aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard_merchant.php' ? 'active' : ''; ?>" 
                       href="dashboard_merchant.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                
                <!-- Inventory Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['merchant_stock.php', 'stockADD.php']) ? 'active' : ''; ?>" 
                       href="#" id="inventoryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-boxes"></i> Inventory
                    </a>
                    <div class="dropdown-menu" aria-labelledby="inventoryDropdown">
                        <a class="dropdown-item" href="merchant_stock.php">
                            <i class="fas fa-list"></i> View Inventory
                        </a>
                        <a class="dropdown-item" href="stockADD.php">
                            <i class="fas fa-plus-circle"></i> Add/Update Stock
                        </a>
                    </div>
                </li>
                
                <!-- Purchases Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['merchant_purchases.php', 'purchasesADD.php']) ? 'active' : ''; ?>" 
                       href="#" id="purchasesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-shopping-cart"></i> Purchases
                    </a>
                    <div class="dropdown-menu" aria-labelledby="purchasesDropdown">
                        <a class="dropdown-item" href="merchant_purchases.php">
                            <i class="fas fa-history"></i> Purchase History
                        </a>
                        <a class="dropdown-item" href="purchasesADD.php">
                            <i class="fas fa-cash-register"></i> Process New Purchase
                        </a>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'critical_items.php' ? 'active' : ''; ?>" 
                       href="critical_items.php">
                        <i class="fas fa-first-aid"></i> Critical Items
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'purchase_schedule.php' ? 'active' : ''; ?>" 
                       href="purchase_schedule.php">
                        <i class="fas fa-calendar-alt"></i> Schedule
                    </a>
                </li>
            </ul>
            
            <!-- Right side navbar items -->
            <div class="d-flex align-items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Business name display -->
                    <?php if (!empty($merchant_name)): ?>
                        <div class="navbar-text text-light d-none d-lg-block me-2">
                            <i class="fas fa-store"></i> <?php echo htmlspecialchars($merchant_name); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- User dropdown -->
                    <div class="dropdown">
                        <a class="btn btn-outline-light dropdown-toggle" 
                           href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="merchant_profile.php">
                                <i class="fas fa-id-card"></i> My Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="../Authentication/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                    
                    <!-- Return to Admin button (only if user is admin viewing as merchant) -->
                    <?php if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin'): ?>
                        <a href="../Admin/switch_role.php" class="btn btn-warning btn-sm ms-2" 
                           onclick="return switchBackToAdmin()">
                            <i class="fas fa-arrow-left"></i> Return to Admin
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Enhanced JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap dropdowns properly
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
    const dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl)
    })
});

// Function to handle switching back to admin
function switchBackToAdmin() {
    // Create a form to submit the switch back request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../Admin/switch_role.php';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'target_role';
    input.value = 'Admin';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    
    return false; // Prevent default link action
}
</script>

<!-- Additional CSS for better navbar functionality -->
<style>
/* Ensure dropdowns work properly */
.navbar-nav .dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-menu {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dropdown-item {
    padding: 8px 20px;
    transition: background-color 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

/* Fix dropdown z-index if needed */
.dropdown-menu {
    z-index: 1050;
}

/* Ensure proper spacing */
.navbar .btn {
    margin-left: 0.25rem;
}

/* Mobile responsiveness */
@media (max-width: 991.98px) {
    .navbar .d-flex {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
    }
    
    .navbar .d-flex > * {
        margin: 0.25rem 0;
    }
    
    .dropdown-menu {
        position: static !important;
        float: none;
        width: auto;
        margin-top: 0;
        background-color: transparent;
        border: 0;
        box-shadow: none;
    }
    
    .dropdown-item {
        padding: 6px 20px;
        color: rgba(255,255,255,.55);
    }
    
    .dropdown-item:hover,
    .dropdown-item:focus {
        background-color: rgba(255,255,255,.1);
        color: rgba(255,255,255,1);
    }
}
</style>