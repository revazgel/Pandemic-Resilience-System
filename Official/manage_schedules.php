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
    
    // Get official info
    $stmt = $pdo->prepare("SELECT * FROM government_officials WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $official = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$official) {
        $_SESSION['error_message'] = "Official profile not found.";
        header("Location: dashboard_official.php");
        exit();
    }
    
    // Get all critical items
    $stmt = $pdo->query("SELECT * FROM critical_items WHERE is_restricted = 1 ORDER BY item_category, item_name");
    $critical_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all purchase schedules
    $stmt = $pdo->query("
        SELECT ps.*, ci.item_name, ci.item_category, 
               go.department, go.role as official_role
        FROM purchase_schedule ps
        JOIN critical_items ci ON ps.item_id = ci.item_id
        JOIN government_officials go ON ps.created_by = go.official_id
        ORDER BY ci.item_category, ci.item_name, ps.day_of_week
    ");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group schedules by item for display
    $items_with_schedules = [];
    foreach ($schedules as $schedule) {
        $item_id = $schedule['item_id'];
        if (!isset($items_with_schedules[$item_id])) {
            $items_with_schedules[$item_id] = [
                'item_name' => $schedule['item_name'],
                'item_category' => $schedule['item_category'],
                'schedules' => []
            ];
        }
        $items_with_schedules[$item_id]['schedules'][] = $schedule;
    }
    
    // Day names for display
    $day_names = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Purchase Schedules - PRS</title>
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
            <h2><i class="fas fa-calendar-alt"></i> Manage Purchase Schedules</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="schedule_add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Schedule
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> 
                <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($items_with_schedules)): ?>
            <div class="alert alert-info fade-in">
                <i class="fas fa-info-circle"></i> No purchase schedules found.
            </div>
        <?php else: ?>
            <!-- Items with Schedules -->
            <div class="row fade-in">
                <?php foreach ($items_with_schedules as $item_id => $item): ?>
                    <div class="col-md-6 mb-3">
                        <div class="panel">
                            <div class="panel-header">
                                <h5 class="mb-0">
                                    <i class="fas <?= $item['item_category'] === 'Medical' ? 'fa-medkit' : 'fa-shopping-basket' ?>"></i>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                    <span class="badge <?= $item['item_category'] === 'Medical' ? 'bg-danger' : 'bg-success' ?>">
                                        <?= htmlspecialchars($item['item_category']) ?>
                                    </span>
                                </h5>
                            </div>
                            <div class="panel-body">
                                <?php foreach ($day_names as $day_num => $day_name): ?>
                                    <?php
                                    $day_schedules = array_filter($item['schedules'], function($s) use ($day_num) {
                                        return $s['day_of_week'] == $day_num;
                                    });
                                    if (!empty($day_schedules)): ?>
                                        <div class="day-card">
                                            <h6 class="mb-2">
                                                <span class="badge bg-primary"><?= $day_name ?></span>
                                            </h6>
                                            <?php foreach ($day_schedules as $schedule): ?>
                                                <div class="mb-2">
                                                    <span class="dob-badge">
                                                        Birth years ending in: <?= htmlspecialchars($schedule['dob_year_ending']) ?>
                                                    </span>
                                                    <div class="schedule-details">
                                                        Effective: <?= date('M d, Y', strtotime($schedule['effective_from'])) ?>
                                                        <?php if ($schedule['effective_to']): ?>
                                                            to <?= date('M d, Y', strtotime($schedule['effective_to'])) ?>
                                                        <?php else: ?>
                                                            (Indefinite)
                                                        <?php endif; ?>
                                                        <br>
                                                        Created by: <?= htmlspecialchars($schedule['department']) ?> - <?= htmlspecialchars($schedule['official_role']) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 