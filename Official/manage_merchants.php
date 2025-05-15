<?php
require_once '../Authentication/session_check.php';

if ($_SESSION['role'] !== 'Official') {
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
    
    
    $stmt = $pdo->prepare("
        SELECT m.merchant_id, m.business_name, m.business_type, m.address, 
               m.business_license_number, m.is_active, 
               u.full_name, u.email, u.phone, u.username, u.created_at
        FROM merchants m
        JOIN users u ON m.user_id = u.user_id
        ORDER BY m.business_name ASC
    ");
    $stmt->execute();
    $merchants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Merchants - PRS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/official_styles.css">
</head>
<body>
<nav class="navbar navbar-expand-lg" style="background: linear-gradient(90deg, #8e2de2 0%, #ff6a00 100%);">
<?php include 'navbar.php'; ?>
</nav>
    
    <div class="container py-4">
        <div class="page-header">
            <h2><i class="fas fa-store"></i> Manage Merchants</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMerchantModal">
                    <i class="fas fa-plus"></i> Add New Merchant
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i> 
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search merchants...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="businessTypeFilter" class="form-select">
                            <option value="">All Business Types</option>
                            <option value="Pharmacy">Pharmacy</option>
                            <option value="Grocery">Grocery</option>
                            <option value="Supermarket">Supermarket</option>
                            <option value="Medical Supply">Medical Supply</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Business Name</th>
                        <th>Owner</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($merchants)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="fas fa-store text-muted"></i>
                                <p class="text-muted mb-0">No merchants found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($merchants as $merchant): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($merchant['business_name']); ?></strong>
                                    <div class="small text-muted">Reg: <?php echo date('M d, Y', strtotime($merchant['created_at'])); ?></div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($merchant['full_name']); ?>
                                    <div class="small text-muted">@<?php echo htmlspecialchars($merchant['username']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($merchant['business_type']); ?></td>
                                <td>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($merchant['email']); ?><br>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($merchant['phone']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $merchant['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $merchant['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editMerchant(<?php echo $merchant['merchant_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMerchant(<?php echo $merchant['merchant_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="modal fade" id="addMerchantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Merchant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="merchant_add.php" method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Name</label>
                                <input type="text" name="business_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Type</label>
                                <select name="business_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="Pharmacy">Pharmacy</option>
                                    <option value="Grocery">Grocery</option>
                                    <option value="Supermarket">Supermarket</option>
                                    <option value="Medical Supply">Medical Supply</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Business Address</label>
                                <textarea name="address" class="form-control" rows="2" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">License Number</label>
                                <input type="text" name="business_license_number" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="is_active" class="form-select" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Merchant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
        
        function applyFilters() {
            const businessType = document.getElementById('businessTypeFilter').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const rowBusinessType = row.children[2].textContent.toLowerCase();
                const statusBadge = row.querySelector('.badge');
                const rowStatus = statusBadge ? statusBadge.textContent.toLowerCase().trim() : '';
                
                const matchesBusinessType = !businessType || rowBusinessType === businessType;
                const matchesStatus = !status || rowStatus === status;
                
                row.style.display = (matchesBusinessType && matchesStatus) ? '' : 'none';
            });
        }
        
        document.getElementById('businessTypeFilter').addEventListener('change', applyFilters);
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
        function editMerchant(id) {
            window.location.href = `merchant_edit.php?id=${id}`;
        }
        function deleteMerchant(id) {
            if (confirm('Are you sure you want to delete this merchant? This action cannot be undone.')) {
                window.location.href = `merchant_delete.php?id=${id}`;
            }
        }
    </script>
</body>
</html> 






