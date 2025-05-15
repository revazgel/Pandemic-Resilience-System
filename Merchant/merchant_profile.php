<?php
require_once '../Authentication/session_check.php';

// Check for merchant role (including admin viewing as merchant)
if ($_SESSION['role'] !== 'Merchant') {
    header("Location: ../Authentication/login.html");
    exit();
}

// DB connection
$host = 'localhost';
$db = 'CovidSystem';
$user = 'root';
$pass = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get merchant info along with user details
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name, u.username, u.email, u.phone, u.prs_id
        FROM merchants m
        JOIN Users u ON m.user_id = u.user_id
        WHERE m.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize default values
    $inventory_count = 0;
    $purchase_count = 0;
    $merchant_id = null;
    
    if (!$merchant) {
        // If admin is viewing as merchant but doesn't have a merchant profile,
        // create a temporary profile for display purposes
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin') {
            // Create a temporary merchant display for admin viewing
            $merchant = [
                'merchant_id' => 'TEMP_ADMIN',
                'business_name' => 'Admin Viewing Mode',
                'business_type' => 'Administration',
                'business_license_number' => 'N/A',
                'is_active' => 1,
                'registration_date' => date('Y-m-d'),
                'address' => 'Admin Panel',
                'city' => 'System',
                'postal_code' => 'N/A',
                'full_name' => $_SESSION['full_name'] ?? 'Admin User',
                'username' => $_SESSION['username'],
                'email' => 'admin@system.local',
                'phone' => 'N/A',
                'prs_id' => 'ADMIN-VIEW'
            ];
            
            // Show a notice that this is admin viewing mode
            $_SESSION['info_message'] = "Currently viewing as Merchant role. This is a temporary profile for demonstration purposes.";
        } else {
            $_SESSION['error_message'] = "Merchant profile not found.";
            header("Location: dashboard_merchant.php");
            exit();
        }
    } else {
        // Get stats for real merchant
        $merchant_id = $merchant['merchant_id'];
        
        // Total inventory items
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE merchant_id = ?");
        $stmt->execute([$merchant_id]);
        $inventory_count = $stmt->fetchColumn();
        
        // Total purchases
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE merchant_id = ?");
        $stmt->execute([$merchant_id]);
        $purchase_count = $stmt->fetchColumn();
    }
    
    // Calculate registration duration
    $registration_date = new DateTime($merchant['registration_date']);
    $now = new DateTime();
    $days_registered = $registration_date->diff($now)->days;
    
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
    <title>Merchant Profile - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/merchant_styles.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-4">
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
        
        <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info fade-in">
                <i class="fas fa-info-circle"></i> 
                <?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="page-header fade-in">
            <h2><i class="fas fa-user-circle"></i> Merchant Profile</h2>
            <?php if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Admin'): ?>
                <div class="bg-warning p-2 rounded text-dark">
                    <i class="fas fa-eye"></i> Admin viewing merchant interface
                </div>
            <?php endif; ?>
            <a href="dashboard_merchant.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="row">
            <!-- Profile Information Card -->
            <div class="col-md-8 mb-4">
                <div class="card fade-in">
                    <div class="card-body">
                        <h5 class="card-title">Business Information</h5>
                        
                        <div class="merchant-info mb-4">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($merchant['business_name']); ?></h4>
                                <div class="merchant-details">
                                    <?php echo htmlspecialchars($merchant['business_type']); ?> | 
                                    License #<?php echo htmlspecialchars($merchant['business_license_number']); ?>
                                </div>
                            </div>
                            <span class="badge bg-<?php echo $merchant['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $merchant['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <div class="customer-details">
                            <h5>Contact Information</h5>
                            <p><i class="fas fa-user"></i> <strong>Contact Person:</strong> <?php echo htmlspecialchars($merchant['full_name']); ?></p>
                            <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($merchant['email'] ?? 'Not provided'); ?></p>
                            <p><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($merchant['phone'] ?? 'Not provided'); ?></p>
                        </div>
                        
                        <div class="customer-details mt-3">
                            <h5>Location</h5>
                            <p><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> <?php echo htmlspecialchars($merchant['address']); ?></p>
                            <p><i class="fas fa-city"></i> <strong>City:</strong> <?php echo htmlspecialchars($merchant['city']); ?></p>
                            <p><i class="fas fa-mailbox"></i> <strong>Postal Code:</strong> <?php echo htmlspecialchars($merchant['postal_code']); ?></p>
                        </div>
                        
                        <div class="customer-details mt-3">
                            <h5>Account Details</h5>
                            <p><i class="fas fa-fingerprint"></i> <strong>PRS ID:</strong> <?php echo htmlspecialchars($merchant['prs_id']); ?></p>
                            <p><i class="fas fa-user-shield"></i> <strong>Username:</strong> <?php echo htmlspecialchars($merchant['username']); ?></p>
                            <p><i class="fas fa-calendar-check"></i> <strong>Registered:</strong> <?php echo date('F j, Y', strtotime($merchant['registration_date'])); ?> (<?php echo $days_registered; ?> days ago)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Summary Card -->
            <div class="col-md-4">
                <div class="card fade-in mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Activity Summary</h5>
                        
                        <div class="stats-row flex-column">
                            <div class="stat-box">
                                <i class="fas fa-boxes text-primary mb-1"></i>
                                <p class="stat-value"><?php echo $inventory_count; ?></p>
                                <p class="stat-label">Items in Inventory</p>
                            </div>
                            
                            <div class="stat-box">
                                <i class="fas fa-shopping-cart text-primary mb-1"></i>
                                <p class="stat-value"><?php echo $purchase_count; ?></p>
                                <p class="stat-label">Total Purchases</p>
                            </div>
                            
                            <div class="stat-box">
                                <i class="fas fa-calendar-alt text-primary mb-1"></i>
                                <p class="stat-value"><?php echo $days_registered; ?></p>
                                <p class="stat-label">Days as Member</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="panel fade-in">
                    <div class="panel-header">
                        <i class="fas fa-info-circle"></i> Quick Info
                    </div>
                    <div class="panel-body">
                        <?php if ($merchant_id === 'TEMP_ADMIN'): ?>
                            <p class="small mb-3">You are currently viewing the merchant interface as an administrator. This is a demonstration view with sample data.</p>
                            
                            <div class="alert-item alert-warning">
                                <i class="fas fa-user-shield"></i> Administrator Viewing Mode Active
                            </div>
                            
                            <div class="d-grid gap-2 mt-3">
                                <a href="../Admin/admin_dashboard.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Return to Admin Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="small mb-3">As a registered merchant in the Pandemic Resilience System, you play a crucial role in ensuring equitable distribution of critical supplies during emergencies.</p>
                            
                            <div class="alert-item alert-info">
                                <i class="fas fa-shield-virus"></i> Thank you for participating in our resilience network.
                            </div>
                            
                            <div class="d-grid gap-2 mt-3">
                                <a href="critical_items.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-first-aid"></i> View Critical Items
                                </a>
                                <a href="purchase_schedule.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-alt"></i> Check Purchase Schedule
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to elements
            document.querySelectorAll('.fade-in').forEach(function(element) {
                element.style.opacity = '0';
                setTimeout(function() {
                    element.style.opacity = '1';
                }, 100);
            });
        });
    </script>
</body>
</html>