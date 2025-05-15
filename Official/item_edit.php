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
    
    // Get item details if editing
    $item = null;
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM critical_items WHERE item_id = ?");
        $stmt->execute([$_GET['id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            $_SESSION['error_message'] = "Item not found";
            header("Location: manage_items.php");
            exit();
        }
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("
            UPDATE critical_items 
            SET item_name = ?, 
                item_description = ?, 
                item_category = ?, 
                unit_of_measure = ?, 
                max_quantity_per_day = ?, 
                max_quantity_per_week = ?
            WHERE item_id = ?
        ");
        
        $stmt->execute([
            $_POST['item_name'],
            $_POST['item_description'],
            $_POST['item_category'],
            $_POST['unit_of_measure'],
            $_POST['max_quantity_per_day'],
            $_POST['max_quantity_per_week'],
            $_POST['item_id']
        ]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                user_id, 
                ip_address, 
                action_type, 
                action_details, 
                entity_type, 
                entity_id
            ) VALUES (?, ?, 'edit_item', ?, 'critical_item', ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            "Edited critical item: " . $_POST['item_name'],
            $_POST['item_id']
        ]);
        
        $_SESSION['success_message'] = "Item updated successfully!";
        header("Location: manage_items.php");
        exit();
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
    <title>Edit Critical Item - PRS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/official_styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
        <div class="page-header">
            <h2><i class="fas fa-edit"></i> Edit Critical Item</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="manage_items.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-boxes"></i> Back to Items
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="card fade-in">
            <div class="card-body">
                <form action="item_edit.php" method="POST">
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" class="form-control" name="item_name" 
                               value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="item_description" rows="3"><?php 
                            echo htmlspecialchars($item['item_description']); 
                        ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="item_category" required>
                                <option value="Medical" <?php echo $item['item_category'] === 'Medical' ? 'selected' : ''; ?>>Medical</option>
                                <option value="Grocery" <?php echo $item['item_category'] === 'Grocery' ? 'selected' : ''; ?>>Grocery</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">Unit of Measure</label>
                            <input type="text" class="form-control" name="unit_of_measure" 
                                   value="<?php echo htmlspecialchars($item['unit_of_measure']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Daily Limit</label>
                            <input type="number" class="form-control" name="max_quantity_per_day" 
                                   value="<?php echo $item['max_quantity_per_day']; ?>" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Weekly Limit</label>
                            <input type="number" class="form-control" name="max_quantity_per_week" 
                                   value="<?php echo $item['max_quantity_per_week']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 