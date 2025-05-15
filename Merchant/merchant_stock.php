<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
if ($_SESSION['role'] !== 'Merchant') {
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
    
    // Get merchant info
    $stmt = $pdo->prepare("SELECT * FROM merchants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle missing merchant profile (admin view)
    if (!$merchant) {
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin') {
            // Create mock merchant for admin view
            $merchant = [
                'merchant_id' => 0,
                'business_name' => 'Admin View Mode',
                'business_type' => 'Administration',
                'address' => 'System Admin View'
            ];
            $_SESSION['info_message'] = "Admin viewing mode - Inventory operations are limited without a merchant profile.";
        } else {
            $_SESSION['error_message'] = "Merchant profile not found.";
            header("Location: dashboard_merchant.php");
            exit();
        }
    }
    
    $merchant_id = $merchant['merchant_id'];
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: dashboard_merchant.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inventory - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
    <style>
        .inventory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .inventory-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            flex: 1;
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .normal-stat {
            border-top: 4px solid #28a745;
        }
        
        .low-stat {
            border-top: 4px solid #ffc107;
        }
        
        .critical-stat {
            border-top: 4px solid #dc3545;
        }
        
        .table th {
            position: relative;
            cursor: pointer;
        }
        
        .table th i {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .stock-badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
        }
        
        .stock-badge i {
            margin-right: 5px;
        }
        
        .normal-stock {
            background-color: rgba(40, 167, 69, 0.15);
            color: #198754;
        }
        
        .low-stock {
            background-color: rgba(255, 193, 7, 0.15);
            color: #856404;
        }
        
        .critical-stock {
            background-color: rgba(220, 53, 69, 0.15);
            color: #721c24;
        }
        
        .empty-stock {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .update-btn {
            transition: all 0.2s;
        }
        
        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }
        
        .table-header {
            background-color: #4b6cb7;
            color: white;
        }
        
        .table-tools {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .search-wrapper {
            position: relative;
            flex-grow: 1;
            max-width: 500px;
        }
        
        .search-wrapper i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #6c757d;
        }
        
        .search-input {
            padding-left: 40px;
            border-radius: 50px;
            border: 1px solid #ced4da;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .stock-indicator {
            width: 12px;
            height: 12px;
            display: inline-block;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .normal-indicator {
            background-color: #28a745;
        }
        
        .low-indicator {
            background-color: #ffc107;
        }
        
        .critical-indicator {
            background-color: #dc3545;
        }
        
        .pagination-custom {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .page-info {
            background-color: #f8f9fa;
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            color: #495057;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="page-header fade-in">
            <h2><i class="fas fa-boxes me-2"></i> My Inventory</h2>
            <div>
                <span class="badge bg-primary p-2 me-2"><?php echo htmlspecialchars($merchant['business_name']); ?></span>
                <a href="dashboard_merchant.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info fade-in" role="alert">
                <i class="fas fa-info-circle me-2"></i> 
                <?php 
                    echo $_SESSION['info_message'];
                    unset($_SESSION['info_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i> 
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> 
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Inventory Statistics -->
        <div class="inventory-stats fade-in">
            <div class="stat-card normal-stat">
                <div><i class="fas fa-boxes fa-lg text-success"></i></div>
                <div class="stat-value" id="totalItems">-</div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card low-stat">
                <div><i class="fas fa-exclamation-triangle fa-lg text-warning"></i></div>
                <div class="stat-value" id="lowStockItems">-</div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-card critical-stat">
                <div><i class="fas fa-exclamation-circle fa-lg text-danger"></i></div>
                <div class="stat-value" id="criticalItems">-</div>
                <div class="stat-label">Critical Stock</div>
            </div>
        </div>
        
        <?php if ($merchant_id === 0): ?>
            <div class="alert alert-warning fade-in">
                <i class="fas fa-eye"></i> <strong>Admin View Mode:</strong> Inventory operations are not available. This is a demonstration view only.
            </div>
        <?php endif; ?>
        
        <div class="card fade-in">
            <div class="card-body">
                <div class="table-tools">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control search-input" placeholder="Search items...">
                    </div>
                    <?php if ($merchant_id > 0): ?>
                        <a href="stockADD.php" class="btn btn-success">
                            <i class="fas fa-plus-circle me-1"></i> Update Inventory
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-header">
                            <tr>
                                <th onclick="sortTable(0)">ID <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(1)">Item Name <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(2)">Category <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(3)">Unit <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(4)">Current Quantity <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(5)">Last Restock <i class="fas fa-sort"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="stockTable">
                            <?php if ($merchant_id === 0): ?>
                                <tr><td colspan="7" class="text-center py-4">No inventory data available in admin view mode</td></tr>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-4">Loading inventory data...</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center">
                        <span class="me-3 text-muted small">Stock Status:</span>
                        <span class="me-3 small"><span class="stock-indicator normal-indicator"></span> Normal</span>
                        <span class="me-3 small"><span class="stock-indicator low-indicator"></span> Low</span>
                        <span class="small"><span class="stock-indicator critical-indicator"></span> Critical</span>
                    </div>
                    
                    <div class="pagination-custom">
                        <button class="btn btn-sm btn-outline-secondary" id="prevBtn">
                            <i class="fas fa-chevron-left me-1"></i> Previous
                        </button>
                        <div class="page-info" id="paginationInfo">Page 1 of 1</div>
                        <button class="btn btn-sm btn-outline-secondary" id="nextBtn">
                            Next <i class="fas fa-chevron-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let stockData = [];
        let currentPage = 1;
        const rowsPerPage = 10;
        const merchantId = <?php echo $merchant_id; ?>;
        
        document.addEventListener("DOMContentLoaded", function () {
            // Only fetch data if we have a real merchant
            if (merchantId > 0) {
                fetchStockData();
            } else {
                // Admin view - show empty stats
                document.getElementById("totalItems").textContent = "0";
                document.getElementById("lowStockItems").textContent = "0";
                document.getElementById("criticalItems").textContent = "0";
            }
            
            // Event listeners for pagination
            document.getElementById("prevBtn").addEventListener("click", prevPage);
            document.getElementById("nextBtn").addEventListener("click", nextPage);
            
            // Search functionality
            document.getElementById("searchInput").addEventListener("input", function() {
                if (merchantId > 0) {
                    currentPage = 1;
                    filterTable();
                }
            });
        });
        
        function fetchStockData() {
            fetch(`/APICRUDV2/API/get_merchant_stock.php?merchant_id=${merchantId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error("Error:", data.error);
                        document.getElementById("stockTable").innerHTML = 
                            `<tr><td colspan="7" class="text-center text-danger py-4">Error loading data: ${data.error}</td></tr>`;
                        return;
                    }
                    
                    stockData = data;
                    updateStats(data);
                    renderTable();
                })
                .catch(error => {
                    console.error("Fetch error:", error);
                    document.getElementById("stockTable").innerHTML = 
                        `<tr><td colspan="7" class="text-center text-danger py-4">Failed to load data. Please try again.</td></tr>`;
                });
        }
        
        function updateStats(data) {
            const totalItems = data.length;
            const lowItems = data.filter(item => item.current_quantity < 10 && item.current_quantity >= 5).length;
            const criticalItems = data.filter(item => item.current_quantity < 5).length;
            
            document.getElementById("totalItems").textContent = totalItems;
            document.getElementById("lowStockItems").textContent = lowItems;
            document.getElementById("criticalItems").textContent = criticalItems;
        }
        
        function renderTable() {
            const tableBody = document.getElementById("stockTable");
            tableBody.innerHTML = "";
            
            if (stockData.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4">No items found in inventory</td></tr>`;
                document.getElementById("paginationInfo").textContent = `Page 0 of 0`;
                return;
            }
            
            // Calculate pagination
            const totalPages = Math.ceil(stockData.length / rowsPerPage);
            const start = (currentPage - 1) * rowsPerPage;
            const end = Math.min(start + rowsPerPage, stockData.length);
            const paginatedData = stockData.slice(start, end);
            
            // Update pagination info
            document.getElementById("paginationInfo").textContent = `Page ${currentPage} of ${totalPages}`;
            
            // Disable/enable pagination buttons
            document.getElementById("prevBtn").disabled = currentPage === 1;
            document.getElementById("nextBtn").disabled = currentPage === totalPages;
            
            // Render rows
            paginatedData.forEach(item => {
                const row = document.createElement("tr");
                
                // Determine stock status classes and badges
                let stockClass = "";
                let stockBadge = "";
                
                if (item.current_quantity === 0) {
                    stockBadge = `<span class="stock-badge empty-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>`;
                } else if (item.current_quantity < 5) {
                    stockClass = "table-danger";
                    stockBadge = `<span class="stock-badge critical-stock"><i class="fas fa-exclamation-circle"></i> Critical: ${item.current_quantity}</span>`;
                } else if (item.current_quantity < 10) {
                    stockClass = "table-warning";
                    stockBadge = `<span class="stock-badge low-stock"><i class="fas fa-exclamation-triangle"></i> Low: ${item.current_quantity}</span>`;
                } else {
                    stockBadge = `<span class="stock-badge normal-stock"><i class="fas fa-check-circle"></i> ${item.current_quantity}</span>`;
                }
                
                row.className = stockClass;
                
                // Format last restock date
                let restockDate = 'N/A';
                if (item.last_restock_date) {
                    const date = new Date(item.last_restock_date);
                    restockDate = date.toLocaleString();
                }
                
                row.innerHTML = `
                    <td>${item.item_id}</td>
                    <td><strong>${item.item_name}</strong></td>
                    <td>${item.item_category}</td>
                    <td>${item.unit_of_measure}</td>
                    <td>${stockBadge}</td>
                    <td>${restockDate}</td>
                    <td>
                        ${merchantId > 0 ? 
                            `<a href="stockADD.php?item_id=${item.item_id}&merchant_id=${merchantId}" class="btn btn-primary btn-sm update-btn">
                                <i class="fas fa-edit"></i> Update
                            </a>` : 
                            '<span class="text-muted small">Admin view</span>'
                        }
                    </td>
                `;
                
                tableBody.appendChild(row);
            });
        }
        
        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        }
        
        function nextPage() {
            const totalPages = Math.ceil(stockData.length / rowsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
            }
        }
        
        function sortTable(columnIndex) {
            // Get the column name based on index
            const columnNames = ["item_id", "item_name", "item_category", "unit_of_measure", "current_quantity", "last_restock_date"];
            const columnName = columnNames[columnIndex];
            
            // Sort the data
            stockData.sort((a, b) => {
                // For numbers
                if (typeof a[columnName] === 'number') {
                    return a[columnName] - b[columnName];
                }
                
                // For dates
                if (columnName === 'last_restock_date') {
                    const dateA = a[columnName] ? new Date(a[columnName]) : new Date(0);
                    const dateB = b[columnName] ? new Date(b[columnName]) : new Date(0);
                    return dateA - dateB;
                }
                
                // For strings
                return String(a[columnName]).localeCompare(String(b[columnName]));
            });
            
            // Reset to first page and render
            currentPage = 1;
            renderTable();
        }
        
        function filterTable() {
            const searchTerm = document.getElementById("searchInput").value.toLowerCase();
            
            // Load original data
            fetch(`/APICRUDV2/API/get_merchant_stock.php?merchant_id=${merchantId}`)
                .then(response => response.json())
                .then(data => {
                    // Filter by search term
                    stockData = data.filter(item => 
                        item.item_name.toLowerCase().includes(searchTerm) ||
                        item.item_category.toLowerCase().includes(searchTerm) ||
                        item.unit_of_measure.toLowerCase().includes(searchTerm)
                    );
                    
                    updateStats(stockData);
                    renderTable();
                })
                .catch(error => {
                    console.error("Error filtering data:", error);
                });
        }
    </script>
</body>
</html>