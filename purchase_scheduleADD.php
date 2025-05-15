<?php
require_once 'session_check.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB config
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

$redirectAfterSuccess = false;
$success_message = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'];
    $day_of_week = (int)$_POST['day_of_week'];
    $dob_year_ending = trim($_POST['dob_year_ending']);
    $effective_from = $_POST['effective_from'];
    $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
    $created_by = (int)$_POST['created_by'];
    
    // Validate item exists
    $checkItem = $pdo->prepare("SELECT item_id FROM critical_items WHERE item_id = ?");
    $checkItem->execute([$item_id]);
    if ($checkItem->rowCount() == 0) {
        $error_message = "⚠️ Item ID does not exist. Redirecting back...";
        $redirectAfterError = true;
    } else {
        // Validate official exists
        $checkOfficial = $pdo->prepare("SELECT official_id FROM government_officials WHERE official_id = ?");
        $checkOfficial->execute([$created_by]);
        if ($checkOfficial->rowCount() == 0) {
            $error_message = "⚠️ Official ID does not exist. Redirecting back...";
            $redirectAfterError = true;
        } else {
            // Insert with default CURRENT_TIMESTAMP for created_at
            $stmt = $pdo->prepare("INSERT INTO purchase_schedule (item_id, day_of_week, dob_year_ending, effective_from, effective_to, created_by) VALUES (?, ?, ?, ?, ?, ?)");

            try {
                $stmt->execute([$item_id, $day_of_week, $dob_year_ending, $effective_from, $effective_to, $created_by]);
                // Set success flag to show message with redirect
                $success_message = "✅ Added successfully! Redirecting to Purchase Schedule...";
                $redirectAfterSuccess = true;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    // Duplicate error
                    $error_message = "⚠️ This schedule already exists for the specified item, day and DOB year ending. Redirecting back...";
                    $redirectAfterError = true;
                } else {
                    $error_message = "Database error: " . $e->getMessage();
                    $redirectAfterError = true;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Purchase Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($redirectAfterSuccess): ?>
    <script>
        // Redirect after 2 seconds
        setTimeout(function() {
            window.location.href = "purchase_schedule.php";
        }, 2000);
    </script>
    <?php endif; ?>
    <?php if (isset($redirectAfterError)): ?>
    <script>
        // Redirect after 3 seconds
        setTimeout(function() {
            window.location.href = "purchase_scheduleADD.php";
        }, 3000);
    </script>
    <?php endif; ?>
</head>

<body class="container mt-5">
    <h2>Add Purchase Schedule</h2>
    
    <?php
    // Display error message
    if (isset($error_message)) {
        echo '<div class="alert alert-danger text-center" role="alert">' . $error_message . '</div>';
    }
    
    // Display success message if set
    if (isset($success_message)) {
        echo '<div class="alert alert-success text-center" role="alert">' . $success_message . '</div>';
    } else {
    ?>

    <form action="purchase_scheduleADD.php" method="POST" class="mt-4">
        <div class="mb-3">
            <label for="item_id" class="form-label">Item ID:</label>
            <input type="number" name="item_id" class="form-control" required>
            
            <label for="day_of_week" class="form-label">Day of Week:</label>
            <select name="day_of_week" class="form-control" required>
                <option value="" disabled selected>Select a day</option>
                <option value="1">Monday (1)</option>
                <option value="2">Tuesday (2)</option>
                <option value="3">Wednesday (3)</option>
                <option value="4">Thursday (4)</option>
                <option value="5">Friday (5)</option>
                <option value="6">Saturday (6)</option>
                <option value="7">Sunday (7)</option>
            </select>
            
            <label for="dob_year_ending" class="form-label">DOB Year Ending (comma-separated numbers):</label>
            <input type="text" name="dob_year_ending" class="form-control" placeholder="e.g., 0,1,2" required>
            <small class="text-muted">Enter the last digits of birth years separated by commas (e.g., 0,1,2)</small>
            
            <label for="effective_from" class="form-label">Effective From:</label>
            <input type="date" name="effective_from" class="form-control" required>
            
            <label for="effective_to" class="form-label">Effective To (optional):</label>
            <input type="date" name="effective_to" class="form-control">
            <small class="text-muted">Leave empty if indefinite</small>
            
            <label for="created_by" class="form-label">Created By (Official ID):</label>
            <input type="number" name="created_by" class="form-control" required>
            
            <button type="submit" class="btn btn-dark w-100 mt-3">Add Purchase Schedule</button>
        </div>
    </form>
    <div class="d-flex justify-content-between mt-3">
        <a href="index.php" class="btn btn-primary">Back to Menu</a>
        <a href="purchase_schedule.php" class="btn btn-secondary">View Purchase Schedule List</a>
    </div>
    <?php } // End of else clause for success message check ?>
</body>
</html>