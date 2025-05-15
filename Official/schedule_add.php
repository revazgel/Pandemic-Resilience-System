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

$error_message = null;
$success_message = null;

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
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $item_id = (int)$_POST['item_id'];
        $day_of_week = (int)$_POST['day_of_week'];
        $dob_year_ending = trim($_POST['dob_year_ending']);
        $effective_from = $_POST['effective_from'];
        $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
        
        // Validate DOB year ending format (comma-separated numbers)
        if (!preg_match('/^[0-9](,[0-9])*$/', $dob_year_ending)) {
            $error_message = "Invalid DOB year ending format. Please use comma-separated numbers (e.g., 0,2,4)";
        } else {
            // Check for duplicate schedule
            $stmt = $pdo->prepare("
                SELECT schedule_id FROM purchase_schedule 
                WHERE item_id = ? AND day_of_week = ? AND dob_year_ending = ?
                AND effective_from = ?
            ");
            $stmt->execute([$item_id, $day_of_week, $dob_year_ending, $effective_from]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = "A schedule already exists for this item, day, and DOB year ending combination.";
            } else {
                // Insert new schedule
                $stmt = $pdo->prepare("
                    INSERT INTO purchase_schedule 
                    (item_id, day_of_week, dob_year_ending, effective_from, effective_to, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                try {
                    $stmt->execute([
                        $item_id, 
                        $day_of_week, 
                        $dob_year_ending, 
                        $effective_from, 
                        $effective_to, 
                        $official['official_id']
                    ]);
                    
                    $success_message = "Schedule added successfully!";
                    // Redirect after 2 seconds
                    header("refresh:2;url=manage_schedules.php");
                } catch (PDOException $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            }
        }
    }
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Purchase Schedule - PRS</title>
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
            <h2><i class="fas fa-calendar-plus"></i> Add New Purchase Schedule</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="manage_schedules.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-calendar-alt"></i> Back to Schedules
                </a>
            </div>
        </div>
        
        <div class="panel fade-in">
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <?= $success_message ?>
                </div>
            <?php else: ?>
                <form method="POST" action="schedule_add.php">
                    <div class="mb-3">
                        <label for="item_id" class="form-label">Critical Item</label>
                        <select class="form-select" id="item_id" name="item_id" required>
                            <option value="">Select an item</option>
                            <?php foreach ($critical_items as $item): ?>
                                <option value="<?= $item['item_id'] ?>">
                                    <?= htmlspecialchars($item['item_name']) ?> 
                                    (<?= htmlspecialchars($item['item_category']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="day_of_week" class="form-label">Day of Week</label>
                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                            <option value="">Select a day</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dob_year_ending" class="form-label">DOB Year Ending</label>
                        <input type="text" class="form-control" id="dob_year_ending" name="dob_year_ending" 
                               placeholder="e.g., 0,2,4" required>
                        <div class="form-text">
                            Enter the last digits of birth years separated by commas (e.g., 0,2,4)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="effective_from" class="form-label">Effective From</label>
                        <input type="date" class="form-control" id="effective_from" name="effective_from" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="effective_to" class="form-label">Effective To (Optional)</label>
                        <input type="date" class="form-control" id="effective_to" name="effective_to">
                        <div class="form-text">
                            Leave empty if the schedule should be indefinite
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Schedule
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 