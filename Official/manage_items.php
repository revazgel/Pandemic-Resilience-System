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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
   
    $stmt = $pdo->prepare("
        SELECT 
            ci.*,
            COUNT(DISTINCT s.merchant_id) as stocked_by_merchants,
            COALESCE(SUM(s.current_quantity), 0) as total_stock,
            COALESCE(AVG(s.current_quantity), 0) as avg_stock_per_merchant
        FROM critical_items ci
        LEFT JOIN stock s ON ci.item_id = s.item_id
        GROUP BY ci.item_id
        ORDER BY ci.item_name
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

// Prepare data for pie chart
$category_totals = [];
foreach ($items as $item) {
    $cat = $item['item_category'];
    $category_totals[$cat] = ($category_totals[$cat] ?? 0) + (int)$item['total_stock'];
}
// Prepare top 5 by total stock
$topTotalStock = $items;
usort($topTotalStock, function($a, $b) { return $b['total_stock'] <=> $a['total_stock']; });
$topTotalStock = array_slice($topTotalStock, 0, 5);
// Prepare top 5 by avg per merchant
$topAvgStock = $items;
usort($topAvgStock, function($a, $b) { return $b['avg_stock_per_merchant'] <=> $a['avg_stock_per_merchant']; });
$topAvgStock = array_slice($topAvgStock, 0, 5);

function renderItemCard($item) {
    ?>
    <div class="item-card-container mb-4" data-item-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>" data-item-category="<?php echo strtolower(htmlspecialchars($item['item_category'])); ?>">
        <div class="card h-100 item-visual-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                <div class="dropdown">
                    <button class="btn btn-link" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="#" onclick="editItem(<?php echo $item['item_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="#" onclick="deleteItem(<?php echo $item['item_id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2"><?php echo htmlspecialchars($item['item_description'] ?? 'No description'); ?></p>
                <div class="item-stats">
                    <div class="stat-item">
                        <span class="stat-label">Category</span>
                        <span class="stat-value">
                            <span class="badge bg-info">
                                <?php echo htmlspecialchars($item['item_category']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Unit</span>
                        <span class="stat-value"><?php echo htmlspecialchars($item['unit_of_measure']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Daily Limit</span>
                        <span class="stat-value"><?php echo $item['max_quantity_per_day']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Weekly Limit</span>
                        <span class="stat-value"><?php echo $item['max_quantity_per_week']; ?></span>
                    </div>
                </div>
                <hr>
                <div class="stock-summary">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Stock:</span>
                        <strong><?php echo number_format($item['total_stock']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Stocked by:</span>
                        <strong><?php echo $item['stocked_by_merchants']; ?> merchants</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Avg. per Merchant:</span>
                        <strong><?php echo number_format($item['avg_stock_per_merchant'], 1); ?></strong>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <small class="text-muted">
                    <i class="fas fa-clock"></i> Added: 
                    <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                </small>
            </div>
        </div>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Critical Items - PRS</title>
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
            <h2><i class="fas fa-boxes"></i> Manage Critical Items</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
            </div>
        </div>
        <!-- Search and Filter Bar (together, above items) -->
        <div class="row mb-3">
          <div class="col-md-8">
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="text" id="itemSearch" class="form-control" placeholder="Search items...">
            </div>
          </div>
          <div class="col-md-4">
            <select id="categoryFilter" class="form-select">
              <option value="">All Categories</option>
              <option value="Medical">Medical</option>
              <option value="Grocery">Grocery</option>
            </select>
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
        
        <!-- Items and Graphs Grid -->
        <div class="row g-4 fade-in">
            <div class="col-xl-4 col-md-12">
                <?php foreach ($items as $i => $item) if ($i % 2 === 0) renderItemCard($item); ?>
            </div>
            <div class="col-xl-4 col-md-12">
                <?php foreach ($items as $i => $item) if ($i % 2 === 1) renderItemCard($item); ?>
            </div>
            <div class="col-xl-4 col-md-12">
                <div class="card p-2 text-center mb-3 graph-card" style="height:220px;">
                    <canvas id="categoryPieChart" width="80" height="80"></canvas>
                </div>
                <div class="row mb-2"><div class="col-12"><div id="pieLegend" class="text-center"></div></div></div>
                <div class="card p-2 text-center mb-3 graph-card" style="height:220px;">
                    <h6 class="mb-2" style="font-size:1em;">Top 5 by Total Stock</h6>
                    <canvas id="barTotalStock" height="120"></canvas>
                </div>
                <div class="card p-2 text-center mb-3 graph-card" style="height:220px;">
                    <h6 class="mb-2" style="font-size:1em;">Top 5 by Avg per Merchant</h6>
                    <canvas id="barAvgStock" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Critical Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="item_add.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="item_description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="item_category" required>
                                    <option value="Medical">Medical</option>
                                    <option value="Grocery">Grocery</option>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label">Unit of Measure</label>
                                <input type="text" class="form-control" name="unit_of_measure" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Daily Limit</label>
                                <input type="number" class="form-control" name="max_quantity_per_day" required>
                            </div>
                            <div class="col">
                                <label class="form-label">Weekly Limit</label>
                                <input type="number" class="form-control" name="max_quantity_per_week" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Item management functions
        function editItem(itemId) {
            // Redirect to edit page with item ID
            window.location.href = `item_edit.php?id=${itemId}`;
        }
        
        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                // Send delete request
                fetch('item_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `item_id=${itemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting item: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting item: ' + error);
                });
            }
        }

        // Search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('itemSearch');
            const categoryFilter = document.getElementById('categoryFilter');
            function filterItems() {
              const searchTerm = searchInput.value.toLowerCase();
              const category = categoryFilter.value.toLowerCase();
              document.querySelectorAll('.item-card-container').forEach(card => {
                const name = card.getAttribute('data-item-name');
                const cat = card.getAttribute('data-item-category');
                card.style.display = (name.includes(searchTerm) && (category === '' || cat === category)) ? '' : 'none';
              });
            }
            searchInput.addEventListener('input', filterItems);
            categoryFilter.addEventListener('change', filterItems);
        });

        // Pie Chart Data
        const pieLabels = <?php echo json_encode(array_keys($category_totals)); ?>;
        const pieData = <?php echo json_encode(array_values($category_totals)); ?>;
        const pieColors = ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f'];
        // Bar Chart Data (Top 5 by total stock)
        const barTotalLabels = <?php echo json_encode(array_map(function($i){return $i['item_name'];}, $topTotalStock)); ?>;
        const barTotalData = <?php echo json_encode(array_map(function($i){return (int)$i['total_stock'];}, $topTotalStock)); ?>;
        // Bar Chart Data (Top 5 by avg per merchant)
        const barAvgLabels = <?php echo json_encode(array_map(function($i){return $i['item_name'];}, $topAvgStock)); ?>;
        const barAvgData = <?php echo json_encode(array_map(function($i){return (float)$i['avg_stock_per_merchant'];}, $topAvgStock)); ?>;
        document.addEventListener('DOMContentLoaded', function() {
            // Pie Chart
            new Chart(document.getElementById('categoryPieChart').getContext('2d'), {
                type: 'doughnut',
                data: { labels: pieLabels, datasets: [{ data: pieData, backgroundColor: pieColors }] },
                options: { cutout: '65%', plugins: { legend: { display: false } } }
            });
            document.getElementById('pieLegend').innerHTML = pieLabels.map((l,i) =>
                `<span style='display:inline-block;margin-right:8px;'>\
                  <span style='display:inline-block;width:9px;height:9px;background:${pieColors[i]};border-radius:50%;margin-right:3px;'></span>${l}
                </span>`
            ).join('');
            // Bar Chart: Top 5 by Total Stock
            new Chart(document.getElementById('barTotalStock').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: barTotalLabels,
                    datasets: [{
                        data: barTotalData,
                        backgroundColor: '#4e79a7',
                        borderRadius: 6,
                        maxBarThickness: 18
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { font: { size: 11 } } }, y: { ticks: { font: { size: 11 } } } }
                }
            });
            // Bar Chart: Top 5 by Avg per Merchant
            new Chart(document.getElementById('barAvgStock').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: barAvgLabels,
                    datasets: [{
                        data: barAvgData,
                        backgroundColor: '#f28e2b',
                        borderRadius: 6,
                        maxBarThickness: 18
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { font: { size: 11 } } }, y: { ticks: { font: { size: 11 } } } }
                }
            });
        });
    </script>
    <style>
        .item-visual-card {
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            transition: transform 0.12s, box-shadow 0.12s;
        }
        .item-visual-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 6px 24px rgba(0,0,0,0.13);
        }
        .graph-card { min-height: 220px; max-height: 220px; }
    </style>
</body>
</html> 