<?php
require_once '../Authentication/session_check.php';  // This file is in the root directory
require_once '../Helper/item_eligibility.php';  

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
    
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all critical items
    $stmt = $pdo->query("SELECT * FROM critical_items ORDER BY item_name");
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check eligibility using helper function
    $eligibility = checkUserEligibility($pdo, $_SESSION['user_id']);
    $has_eligible_items = $eligibility['eligible'];
    $eligible_items = $eligibility['items'];
    $eligible_item_ids = array_column($eligible_items, 'item_id');
    
    // Get eligible days for each item
    $item_eligible_days = [];
    foreach ($all_items as $item) {
        $item_id = $item['item_id'];
        $eligible_days = getItemEligibleDays($pdo, $_SESSION['user_id'], $item_id);
        if (!empty($eligible_days)) {
            $item_eligible_days[$item_id] = [
                'item_name' => $item['item_name'],
                'days' => $eligible_days
            ];
        }
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
    <title>Find Available Supplies - COVID Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    <div class="container">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-search-location"></i> Find Available Supplies</h2>
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
                <h5 class="mb-3">Search for Available Supplies</h5>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="itemSelect" class="form-label">Select Item:</label>
                        <select id="itemSelect" class="form-select">
                            <option value="">All Items</option>
                            <?php foreach ($all_items as $item): ?>
                                <?php 
                                    $eligible = in_array($item['item_id'], $eligible_item_ids);
                                    $eligibility_class = $eligible ? 'eligible-option' : 'not-eligible-option';
                                ?>
                                <option value="<?php echo $item['item_id']; ?>" class="<?php echo $eligibility_class; ?>">
                                    <?php echo htmlspecialchars($item['item_name']); ?> 
                                    <?php if ($eligible): ?>
                                        (Eligible Today)
                                    <?php else: ?>
                                        (Not Eligible Today)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="merchantType" class="form-label">Merchant Type:</label>
                        <select id="merchantType" class="form-select">
                            <option value="">All Types</option>
                            <option value="Pharmacy">Pharmacy</option>
                            <option value="Grocery">Grocery</option>
                            <option value="Supermarket">Supermarket</option>
                            <option value="Medical Supply">Medical Supply</option>
                        </select>
                    </div>
                </div>
                
                <?php if ($has_eligible_items): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    Based on your date of birth (<?php echo date('Y-m-d', strtotime($user_details['dob'])); ?>), 
                    you are eligible to purchase specific items today. Items you are eligible to purchase are marked with a green badge.
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Based on your date of birth (<?php echo date('Y-m-d', strtotime($user_details['dob'])); ?>), 
                    you are not eligible to purchase any restricted items today.
                    
                    <?php if (!empty($item_eligible_days)): ?>
                    <div class="mt-2">
                        <strong>Your eligible days:</strong>
                        <ul class="mb-0">
                            <?php foreach ($item_eligible_days as $item_id => $data): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($data['item_name']); ?>:</strong> 
                                    <?php echo implode(', ', $data['days']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <button id="searchButton" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Find Supplies
                </button>
            </div>
        </div>
        
        <div id="resultsContainer">
            <div class="text-center py-5">
                <i class="fas fa-store fa-3x mb-3 text-muted"></i>
                <p class="text-muted">Select an item and click 'Find Supplies' to see available merchants.</p>
            </div>
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
        });
        
        document.addEventListener("DOMContentLoaded", function() {
            // Get DOM elements
            const itemSelect = document.getElementById("itemSelect");
            const merchantTypeSelect = document.getElementById("merchantType");
            const searchButton = document.getElementById("searchButton");
            const resultsContainer = document.getElementById("resultsContainer");
            
            // Store eligible item IDs
            const eligibleItemIds = [<?php echo implode(',', $eligible_item_ids); ?>];
            
            // Add event listener to search button
            searchButton.addEventListener("click", function() {
                const itemId = itemSelect.value;
                const merchantType = merchantTypeSelect.value;
                
                // Debugging output
                console.log("Searching for:", { itemId, merchantType });
                
                // Show loading indicator
                resultsContainer.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Searching for available supplies...</p>
                    </div>
                `;
                
                // Fetch available supplies from server
                fetch(`/APICRUDV2/API/find_available_supplies.php?item_id=${itemId}&merchant_type=${merchantType}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            resultsContainer.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i> ${data.error}
                                </div>
                            `;
                            return;
                        }
                        
                        // Handle empty results
                        if (data.length === 0) {
                            resultsContainer.innerHTML = `
                                <div class="text-center py-5">
                                    <i class="fas fa-exclamation-circle fa-3x mb-3 text-warning"></i>
                                    <p class="text-muted">No merchants found with the selected items in stock.</p>
                                </div>
                            `;
                            return;
                        }
                        
                        // Group results by merchant
                        const merchantGroups = {};
                        data.forEach(item => {
                            if (!merchantGroups[item.merchant_id]) {
                                merchantGroups[item.merchant_id] = {
                                    merchant_id: item.merchant_id,
                                    business_name: item.business_name,
                                    business_type: item.business_type,
                                    address: item.address,
                                    city: item.city,
                                    postal_code: item.postal_code,
                                    items: []
                                };
                            }
                            
                            merchantGroups[item.merchant_id].items.push({
                                item_id: item.item_id,
                                item_name: item.item_name,
                                current_quantity: item.current_quantity,
                                item_category: item.item_category,
                                unit_of_measure: item.unit_of_measure,
                                is_eligible: eligibleItemIds.includes(parseInt(item.item_id))
                            });
                        });
                        
                        // Generate HTML for results
                        let resultsHTML = `<h4 class="mb-3">Available Merchants</h4>`;
                        resultsHTML += `<div class="row">`;
                        
                        Object.values(merchantGroups).forEach(merchant => {
                            resultsHTML += `
                                <div class="col-md-6 mb-4">
                                    <div class="card merchant-card">
                                        <div class="card-header bg-primary text-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">${merchant.business_name}</h5>
                                                <span class="badge bg-secondary">${merchant.business_type}</span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p><i class="fas fa-map-marker-alt"></i> ${merchant.address}, ${merchant.city}, ${merchant.postal_code}</p>
                                            <h6>Available Items:</h6>
                                            <ul class="list-group list-group-flush">
                            `;
                            
                            merchant.items.forEach(item => {
                                const eligibilityBadge = item.is_eligible 
                                    ? '<span class="badge eligible-badge ms-2">Eligible Today</span>' 
                                    : '<span class="badge not-eligible-badge ms-2">Not Eligible Today</span>';
                                
                                resultsHTML += `
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${item.item_name}</strong> ${eligibilityBadge}
                                            <small class="d-block text-muted">${item.item_category} - ${item.unit_of_measure}</small>
                                        </div>
                                        <span class="badge bg-secondary rounded-pill">${item.current_quantity} in stock</span>
                                    </li>
                                `;
                            });
                            
                            resultsHTML += `
                                            </ul>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <a href="citizen_merchant_details.php?merchant_id=${merchant.merchant_id}" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-info-circle"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        resultsHTML += `</div>`;
                        resultsContainer.innerHTML = resultsHTML;
                    })
                    .catch(error => {
                        console.error("Error fetching supplies:", error);
                        resultsContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> An error occurred while searching for supplies. Please try again.
                            </div>
                        `;
                    });
            });
        });
    </script>
</body>
</html>