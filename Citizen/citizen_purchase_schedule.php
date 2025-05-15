<?php
require_once '../Authentication/session_check.php';
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
    
    // Get birth year last digit
    $dob_year = date('Y', strtotime($user_details['dob']));
    $last_digit = substr($dob_year, -1);
    
    // Get all critical items
    $stmt = $pdo->query("SELECT * FROM critical_items WHERE is_restricted = 1 ORDER BY item_category, item_name");
    $critical_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create an array to store eligible days for each item
    $item_schedules = [];
    
    // Get purchase schedules for each item relevant to this user
    foreach ($critical_items as $item) {
        $query = "
            SELECT ps.day_of_week, ci.item_name, ci.item_id, ci.item_category, ci.max_quantity_per_day, ci.max_quantity_per_week
            FROM purchase_schedule ps
            JOIN critical_items ci ON ps.item_id = ci.item_id
            WHERE ps.item_id = ?
            AND ps.dob_year_ending LIKE ?
            AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')
            ORDER BY ps.day_of_week
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$item['item_id'], "%$last_digit%"]);
        $schedule_days = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($schedule_days)) {
            $item_schedules[$item['item_id']] = [
                'item_name' => $item['item_name'],
                'item_category' => $item['item_category'],
                'max_per_day' => $item['max_quantity_per_day'],
                'max_per_week' => $item['max_quantity_per_week'],
                'days' => $schedule_days
            ];
        }
    }
    
    // Get today's day of the week (1-7, Monday-Sunday)
    $today_day = date('N');
    
    // Create a calendar-like data structure for visualization
    $weekly_calendar = [];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    foreach ($days as $index => $day_name) {
        $day_num = $index + 1;
        $is_today = ($day_num == $today_day);
        
        $day_items = [];
        foreach ($item_schedules as $item_id => $item) {
            foreach ($item['days'] as $schedule) {
                if ($schedule['day_of_week'] == $day_num) {
                    $day_items[] = [
                        'item_id' => $item_id,
                        'item_name' => $item['item_name'],
                        'category' => $item['item_category'],
                        'max_per_day' => $item['max_per_day']
                    ];
                    break;
                }
            }
        }
        
        $weekly_calendar[] = [
            'day_num' => $day_num,
            'day_name' => $day_name,
            'is_today' => $is_today,
            'items' => $day_items
        ];
    }
    
    // Group items by category for the detailed list view
    $items_by_category = [];
    foreach ($item_schedules as $item_id => $item) {
        $category = $item['item_category'];
        if (!isset($items_by_category[$category])) {
            $items_by_category[$category] = [];
        }
        $items_by_category[$category][$item_id] = $item;
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
    <title>My Purchase Schedule - COVID Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    
    <div class="container">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-calendar-alt"></i> My Purchase Schedule</h2>
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
                <h5><i class="fas fa-info-circle"></i> Your Purchase Eligibility</h5>
                <p>Based on your date of birth (<strong><?php echo date('Y-m-d', strtotime($user_details['dob'])); ?></strong>), 
                you are assigned to the group with birth years ending in <strong><?php echo $last_digit; ?></strong>.</p>
                <p>The schedule below shows which items you can purchase on specific days of the week.</p>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #f8d7da;"></div>
                        <span>Medical Items</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #d1e7dd;"></div>
                        <span>Grocery Items</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #d1e7dd; border: 2px solid #28a745;"></div>
                        <span>Today</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Visual Weekly Calendar -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-week"></i> Weekly View</h5>
            </div>
            <div class="card-body">
                <div class="calendar-container">
                    <div class="calendar-row">
                        <?php foreach ($weekly_calendar as $day): ?>
                            <div class="calendar-day <?php echo $day['is_today'] ? 'today' : ''; ?>">
                                <div class="day-header">
                                    <?php echo $day['day_name']; ?>
                                    <?php if ($day['is_today']): ?>
                                        <span class="today-badge">Today</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($day['items'])): ?>
                                    <div class="text-muted text-center small mt-3">
                                        <i class="fas fa-ban"></i><br>
                                        No eligible items
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($day['items'] as $item): ?>
                                        <div class="item-pill <?php echo $item['category']; ?>" 
                                             title="<?php echo $item['item_name']; ?> - Max: <?php echo $item['max_per_day']; ?>/day">
                                            <i class="fas <?php echo $item['category'] === 'Medical' ? 'fa-medkit' : 'fa-shopping-basket'; ?>"></i>
                                            <?php echo $item['item_name']; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Purchase Schedule Chart -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Detailed Purchase Schedule</h5>
            </div>
            <div class="card-body">
                <div class="purchase-schedule-chart">
                    <div class="chart-container">
                        <div class="chart-header">
                            <div class="chart-item-name">Item</div>
                            <?php foreach ($days as $day): ?>
                                <div class="chart-cell"><?php echo $day; ?></div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php foreach ($item_schedules as $item_id => $item): ?>
                            <div class="chart-row">
                                <div class="chart-item-name">
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                    <div class="small text-muted"><?php echo $item['item_category']; ?></div>
                                </div>
                                
                                <?php for ($day_num = 1; $day_num <= 7; $day_num++): ?>
                                    <div class="chart-day">
                                        <?php 
                                            $is_eligible_day = false;
                                            foreach ($item['days'] as $schedule) {
                                                if ((int)$schedule['day_of_week'] === $day_num) {
                                                    $is_eligible_day = true;
                                                    break;
                                                }
                                            }
                                            
                                            $day_class = 'day-indicator';
                                            if ($is_eligible_day) $day_class .= ' eligible';
                                            if ($day_num === $today_day) $day_class .= ' today';
                                        ?>
                                        <div class="<?php echo $day_class; ?>"></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if (empty($item_schedules)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x mb-3 text-muted"></i>
                        <p class="text-muted">You don't have any scheduled purchase eligibilities for restricted items.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Item Details Section -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list-alt"></i> Item Details</h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="itemDetailsAccordion">
                    <?php foreach ($items_by_category as $category => $items): ?>
                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#collapse-<?php echo str_replace(' ', '-', strtolower($category)); ?>"
                                        aria-expanded="false" aria-controls="collapse-<?php echo str_replace(' ', '-', strtolower($category)); ?>">
                                    <i class="fas fa-tags me-2"></i> <?php echo htmlspecialchars($category); ?> Items
                                </button>
                            </h2>
                            <div id="collapse-<?php echo str_replace(' ', '-', strtolower($category)); ?>" 
                                 class="accordion-collapse collapse" 
                                 aria-labelledby="heading<?php echo str_replace(' ', '-', strtolower($category)); ?>"
                                 data-bs-parent="#itemDetailsAccordion">
                                <div class="accordion-body">
                                    <div class="row">
                                        <?php foreach ($items as $item_id => $item): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                                                        <p class="mb-2">
                                                            <strong>Maximum per day:</strong> <?php echo $item['max_per_day']; ?>
                                                        </p>
                                                        <p class="mb-2">
                                                            <strong>Maximum per week:</strong> <?php echo $item['max_per_week']; ?>
                                                        </p>
                                                        <p class="mb-2">
                                                            <strong>Available on:</strong> 
                                                            <?php 
                                                                $eligible_days = array_map(function($schedule) use ($days) {
                                                                    return $days[$schedule['day_of_week']-1];
                                                                }, $item['days']);
                                                                echo implode(', ', $eligible_days);
                                                            ?>
                                                        </p>
                                                        <a href="citizen_find_supplies.php" class="btn btn-sm btn-primary mt-2">
                                                            <i class="fas fa-search"></i> Find Stores with This Item
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between mt-3">
            <a href="dashboard_citizen.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
            <a href="citizen_find_supplies.php" class="btn btn-success">
                <i class="fas fa-shopping-cart"></i> Find Available Supplies
            </a>
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
            
            // Initialize accordions
            var accordionElementList = [].slice.call(document.querySelectorAll('.accordion-button'));
            accordionElementList.forEach(function (accordionEl) {
                accordionEl.addEventListener('click', function() {
                    // This will be handled by Bootstrap automatically
                });
            });
        });
    </script>
</body>
</html>