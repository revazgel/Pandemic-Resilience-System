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
            $_SESSION['info_message'] = "Admin viewing mode - Purchase data is not available without a merchant profile.";
        } else {
            $_SESSION['error_message'] = "Merchant profile not found.";
            header("Location: dashboard_merchant.php");
            exit();
        }
    }
    
    $merchant_id = $merchant['merchant_id'];
    $purchases = [];
    
    // Get purchases for this merchant (only if we have a real merchant)
    if ($merchant_id > 0) {
        $stmt = $pdo->prepare("
            SELECT p.purchase_id, p.user_id, p.item_id, p.quantity, p.purchase_date, p.verified_by,
                   ci.item_name, ci.item_category, ci.unit_of_measure,
                   u.full_name as customer_name, u.prs_id
            FROM purchases p
            JOIN critical_items ci ON p.item_id = ci.item_id
            JOIN Users u ON p.user_id = u.user_id
            WHERE p.merchant_id = ?
            ORDER BY p.purchase_date DESC
            LIMIT 100
        ");
        $stmt->execute([$merchant_id]);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
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
    <title>Purchase Records - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header fade-in">
            <h2><i class="fas fa-shopping-cart"></i> Purchase Records</h2>
            <div>
                <span class="badge bg-primary"><?= htmlspecialchars($merchant['business_name']) ?></span>
            </div>
        </div>
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info fade-in">
                <i class="fas fa-info-circle"></i>
                <?= $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($merchant_id === 0): ?>
            <div class="alert alert-warning fade-in">
                <i class="fas fa-eye"></i> <strong>Admin View Mode:</strong> Purchase records are not available. This is a demonstration view only.
            </div>
        <?php endif; ?>
        
        <div class="card fade-in">
            <div class="card-body">
                <div class="merchant-info">
                    <h5 class="card-title"><i class="fas fa-receipt"></i> Recent Purchases</h5>
                    <?php if ($merchant_id > 0): ?>
                        <a href="purchasesADD.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle"></i> New Purchase
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($merchant_id > 0): ?>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="search-container">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" class="form-control search-input" placeholder="Search by customer name, item, or ID...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select id="dateFilter" class="form-select">
                                <option value="">All Dates</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)">ID <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(1)">Customer <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(2)">Item <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(3)">Quantity <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(4)">Date <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(5)">Verified By <i class="fas fa-sort"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($merchant_id === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No purchase records available in admin view mode</td>
                                </tr>
                            <?php elseif (empty($purchases)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No purchase records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($purchases as $purchase): ?>
                                    <tr>
                                        <td><?= $purchase['purchase_id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($purchase['customer_name']) ?>
                                            <div class="small text-muted">PRS-ID: <?= htmlspecialchars($purchase['prs_id']) ?></div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($purchase['item_name']) ?>
                                            <div class="small text-muted"><?= htmlspecialchars($purchase['item_category']) ?></div>
                                        </td>
                                        <td><?= $purchase['quantity'] ?> <?= htmlspecialchars($purchase['unit_of_measure']) ?></td>
                                        <td>
                                            <?= date('M d, Y', strtotime($purchase['purchase_date'])) ?>
                                            <div class="small text-muted"><?= date('h:i A', strtotime($purchase['purchase_date'])) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($purchase['verified_by']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewPurchaseDetails(<?= $purchase['purchase_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="footer-nav fade-in">
            <a href="dashboard_merchant.php" class="btn btn-outline-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <?php if ($merchant_id > 0): ?>
                <a href="purchasesADD.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Process New Purchase
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Purchase Details Modal -->
    <div class="modal fade" id="purchaseDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Purchase Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="purchaseDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p>Loading purchase details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const merchantId = <?= $merchant_id ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to elements
            document.querySelectorAll('.fade-in').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.style.opacity = '1', 100);
            });
            
            // Only set up event listeners if we have a real merchant
            if (merchantId > 0) {
                // Set up search functionality
                document.getElementById('searchInput').addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    document.querySelectorAll('tbody tr').forEach(row => {
                        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
                    });
                });
                
                // Set up date filter
                document.getElementById('dateFilter').addEventListener('change', function() {
                    const filterValue = this.value;
                    const rows = document.querySelectorAll('tbody tr');
                    
                    if (!filterValue) {
                        rows.forEach(row => row.style.display = '');
                        return;
                    }
                    
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    
                    const thisWeekStart = new Date(today);
                    thisWeekStart.setDate(thisWeekStart.getDate() - thisWeekStart.getDay());
                    
                    const thisMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                    
                    rows.forEach(row => {
                        const dateCell = row.querySelector('td:nth-child(5)');
                        if (!dateCell) return;
                        
                        const purchaseDateStr = dateCell.textContent.trim();
                        const purchaseDate = new Date(purchaseDateStr);
                        
                        let show = false;
                        
                        switch (filterValue) {
                            case 'today': show = purchaseDate >= today; break;
                            case 'yesterday': show = purchaseDate >= yesterday && purchaseDate < today; break;
                            case 'week': show = purchaseDate >= thisWeekStart; break;
                            case 'month': show = purchaseDate >= thisMonthStart; break;
                        }
                        
                        row.style.display = show ? '' : 'none';
                    });
                });
            }
        });
        
        // Table sorting function
        function sortTable(columnIndex) {
            const table = document.querySelector('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                const cellA = a.querySelectorAll('td')[columnIndex]?.textContent.trim().toLowerCase() || '';
                const cellB = b.querySelectorAll('td')[columnIndex]?.textContent.trim().toLowerCase() || '';
                return cellA.localeCompare(cellB);
            });
            
            rows.forEach(row => tbody.appendChild(row));
        }
        
        // View purchase details function
        function viewPurchaseDetails(purchaseId) {
            if (merchantId === 0) {
                alert('Purchase details are not available in admin view mode.');
                return;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('purchaseDetailsModal'));
            const modalContent = document.getElementById('purchaseDetailsContent');
            
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p>Loading purchase details...</p>
                </div>
            `;
            modal.show();
            
            fetch(`/APICRUDV2/API/get_purchase_details.php?purchase_id=${purchaseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalContent.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> ${data.error}
                            </div>
                        `;
                        return;
                    }
                    
                    const purchaseDate = new Date(data.purchase_date);
                    const formattedDate = purchaseDate.toLocaleDateString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    });
                    
                    modalContent.innerHTML = `
                        <div class="customer-details mb-3">
                            <h5>Customer Information</h5>
                            <p><i class="fas fa-user"></i> <strong>Name:</strong> ${data.customer_name}</p>
                            <p><i class="fas fa-id-card"></i> <strong>PRS-ID:</strong> ${data.prs_id}</p>
                        </div>
                        
                        <div class="customer-details mb-3">
                            <h5>Item Details</h5>
                            <p><i class="fas fa-box"></i> <strong>Item:</strong> ${data.item_name}</p>
                            <p><i class="fas fa-tag"></i> <strong>Category:</strong> ${data.item_category}</p>
                            <p><i class="fas fa-hashtag"></i> <strong>Quantity:</strong> ${data.quantity} ${data.unit_of_measure}</p>
                        </div>
                        
                        <div class="customer-details">
                            <h5>Transaction Details</h5>
                            <p><i class="fas fa-calendar-alt"></i> <strong>Date & Time:</strong> ${formattedDate}</p>
                            <p><i class="fas fa-user-check"></i> <strong>Verified By:</strong> ${data.verified_by}</p>
                            <p><i class="fas fa-receipt"></i> <strong>Purchase ID:</strong> ${data.purchase_id}</p>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error("Error fetching purchase details:", error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> An error occurred while loading purchase details.
                        </div>
                    `;
                });
        }
    </script>
</body>
</html>