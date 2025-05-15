<?php
require_once '../Authentication/session_check.php';

// Check if user has the correct role
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

// Initialize variables
$purchases = [];
$critical_items = [];
$total_purchases = 0;
$total_items = 0;
$category_totals = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get official info
    $stmt = $pdo->prepare("SELECT * FROM government_officials WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $official = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$official) {
        $_SESSION['error_message'] = "Official profile not found.";
        header("Location: dashboard_official.php");
        exit();
    }
    
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $item_id = $_GET['item_id'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build the base query
    $query = "
        SELECT 
            p.purchase_id,
            p.purchase_date,
            p.quantity,
            p.verified_by,
            u.full_name,
            u.prs_id,
            ci.item_name,
            ci.item_category,
            ci.unit_of_measure,
            m.business_name
        FROM purchases p
        JOIN users u ON p.user_id = u.user_id
        JOIN critical_items ci ON p.item_id = ci.item_id
        JOIN merchants m ON p.merchant_id = m.merchant_id
        WHERE 1=1
    ";
    $params = [];
    
    // Add search conditions
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.prs_id LIKE ? OR m.business_name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($item_id)) {
        $query .= " AND p.item_id = ?";
        $params[] = $item_id;
    }
    
    if (!empty($date_from)) {
        $query .= " AND p.purchase_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND p.purchase_date <= ?";
        $params[] = $date_to;
    }
    
    $query .= " ORDER BY p.purchase_date DESC";
    
    // Get all purchases
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all critical items for the filter dropdown
    $stmt = $pdo->query("SELECT * FROM critical_items WHERE is_restricted = 1 ORDER BY item_category, item_name");
    $critical_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_purchases = count($purchases);
    foreach ($purchases as $purchase) {
        $total_items += $purchase['quantity'];
        $category = $purchase['item_category'];
        $category_totals[$category] = ($category_totals[$category] ?? 0) + $purchase['quantity'];
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
    <title>Citizen Purchase History - PRS</title>
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
            <h2><i class="fas fa-shopping-cart"></i> Citizen Purchase History</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> 
                <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="panel mb-4 fade-in">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name, PRS ID, or merchant" 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label for="item_id" class="form-label">Critical Item</label>
                    <select class="form-select" id="item_id" name="item_id">
                        <option value="">All Items</option>
                        <?php foreach ($critical_items as $item): ?>
                            <option value="<?= $item['item_id'] ?>" 
                                    <?= $item_id == $item['item_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['item_name']) ?> 
                                (<?= htmlspecialchars($item['item_category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?= $date_from ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?= $date_to ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4 fade-in">
            <div class="col-md-4">
                <div class="stat-box">
                    <i class="fas fa-shopping-cart text-primary mb-1"></i>
                    <p class="stat-value"><?= $total_purchases ?></p>
                    <p class="stat-label">Total Purchases</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <i class="fas fa-box text-primary mb-1"></i>
                    <p class="stat-value"><?= $total_items ?></p>
                    <p class="stat-label">Total Items</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <i class="fas fa-chart-pie text-primary mb-1"></i>
                    <p class="stat-value"><?= count($category_totals) ?></p>
                    <p class="stat-label">Categories</p>
                </div>
            </div>
        </div>
        
        <!-- Purchase History -->
        <div class="panel fade-in">
            <?php if (empty($purchases)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No purchases found matching your criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Citizen</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Verified By</th>
                                <th>Merchant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($purchase['purchase_date'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($purchase['full_name']) ?>
                                        <br>
                                        <small class="text-muted">PRS-ID: <?= htmlspecialchars($purchase['prs_id']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($purchase['item_name']) ?>
                                        <br>
                                        <span class="badge <?= $purchase['item_category'] === 'Medical' ? 'bg-danger' : 'bg-success' ?>">
                                            <?= htmlspecialchars($purchase['item_category']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $purchase['quantity'] ?>
                                        <small class="text-muted"><?= htmlspecialchars($purchase['unit_of_measure']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($purchase['verified_by']) ?></td>
                                    <td><?= htmlspecialchars($purchase['business_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 