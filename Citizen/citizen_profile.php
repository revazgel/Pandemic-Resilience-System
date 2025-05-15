<?php
require_once '../Authentication/session_check.php';

// Check for citizen role
if ($_SESSION['role'] !== 'Citizen') {
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
    
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error_message'] = "User profile not found.";
        header("Location: dashboard_citizen.php");
        exit();
    }
    
    // Get stats
    $user_id = $user['user_id'];
    
    // Total vaccination records
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vaccination_records WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $vaccination_count = $stmt->fetchColumn();
    
    // Total purchases
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $purchase_count = $stmt->fetchColumn();
    
    // Registration date
    $registration_date = new DateTime($user['created_at']);
    $now = new DateTime();
    $days_registered = $registration_date->diff($now)->days;
    
    // Get today's eligible items
    $today_day = date('N'); // 1-7 (Mon-Sun)
    $dob_year = date('Y', strtotime($user['dob']));
    $last_digit = substr($dob_year, -1);
    
    $stmt = $pdo->prepare("
        SELECT ci.item_id, ci.item_name FROM purchase_schedule ps
        JOIN critical_items ci ON ps.item_id = ci.item_id
        WHERE ps.day_of_week = ? 
        AND FIND_IN_SET(?, ps.dob_year_ending) > 0
        AND CURDATE() BETWEEN ps.effective_from AND IFNULL(ps.effective_to, '9999-12-31')
    ");
    $stmt->execute([$today_day, $last_digit]);
    $eligible_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: dashboard_citizen.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Pandemic Resilience System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/citizen_styles.css">
</head>
<body>
    <?php include 'citizen_navbar.php'; ?>
    
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
        
        <div class="header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-user-circle"></i> My Profile</h2>
            <a href="dashboard_citizen.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="row">
            <!-- Profile Information Card -->
            <div class="col-md-8 mb-4">
                <div class="card fade-in">
                    <div class="card-body">
                        <h5 class="card-title">Personal Information</h5>
                        
                        <div class="merchant-info mb-4">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <div class="merchant-details">
                                    PRS ID: <?php echo htmlspecialchars($user['prs_id']); ?> | 
                                    DOB: <?php echo date('F j, Y', strtotime($user['dob'])); ?>
                                </div>
                            </div>
                            <span class="badge bg-<?php echo $user['is_visitor'] ? 'warning text-dark' : 'success'; ?>">
                                <?php echo $user['is_visitor'] ? 'Visitor' : 'Resident'; ?>
                            </span>
                        </div>
                        
                        <div class="customer-details">
                            <h5>Contact Information</h5>
                            <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></p>
                            <p><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                            <p><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></p>
                        </div>
                        
                        <div class="customer-details mt-3">
                            <h5>Account Details</h5>
                            <p><i class="fas fa-fingerprint"></i> <strong>PRS ID:</strong> <?php echo htmlspecialchars($user['prs_id']); ?></p>
                            <p><i class="fas fa-user-shield"></i> <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                            <p><i class="fas fa-birthday-cake"></i> <strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($user['dob'])); ?> (Year ending in <?php echo substr(date('Y', strtotime($user['dob'])), -1); ?>)</p>
                            <p><i class="fas fa-calendar-check"></i> <strong>Registered:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?> (<?php echo $days_registered; ?> days ago)</p>
                        </div>
                        
                        <div class="customer-details mt-3">
                            <h5>Purchase Eligibility</h5>
                            <p><i class="fas fa-info-circle"></i> Based on your birth year ending in <strong><?php echo substr(date('Y', strtotime($user['dob'])), -1); ?></strong>, you are eligible to purchase specific items on certain days.</p>
                            
                            <?php if (count($eligible_items) > 0): ?>
                                <div class="alert alert-success p-3">
                                    <h6 class="mb-2"><i class="fas fa-check-circle"></i> Today's Eligible Items</h6>
                                    <ul class="mb-0">
                                    <?php foreach ($eligible_items as $item): ?>
                                        <li><?php echo htmlspecialchars($item['item_name']); ?></li>
                                    <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning p-3">
                                    <i class="fas fa-exclamation-triangle"></i> You are not eligible to purchase any restricted items today.
                                </div>
                            <?php endif; ?>
                            
                            <a href="citizen_purchase_schedule.php" class="btn btn-outline-primary btn-sm mt-2">
                                <i class="fas fa-calendar-alt"></i> View Full Purchase Schedule
                            </a>
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
                                <i class="fas fa-syringe text-primary mb-1"></i>
                                <p class="stat-value"><?php echo $vaccination_count; ?></p>
                                <p class="stat-label">Vaccination Records</p>
                            </div>
                            
                            <div class="stat-box">
                                <i class="fas fa-shopping-cart text-primary mb-1"></i>
                                <p class="stat-value"><?php echo $purchase_count; ?></p>
                                <p class="stat-label">Purchase Transactions</p>
                            </div>
                            
                            <div class="stat-box">
                                <i class="fas fa-calendar-alt text-primary mb-1"></i>
                                <p class="stat-value"><?php echo count($eligible_items); ?></p>
                                <p class="stat-label">Items Eligible Today</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="panel fade-in">
                    <div class="panel-header">
                        <i class="fas fa-info-circle"></i> Quick Actions
                    </div>
                    <div class="panel-body">
                        <p class="small mb-3">Access these features to manage your pandemic readiness and supplies:</p>
                        
                        <div class="d-grid gap-2">
                            <a href="citizen_purchase_history.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-history"></i> View Purchase History
                            </a>
                            <a href="citizen_vaccinations.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-syringe"></i> Manage Vaccination Records
                            </a>
                            <a href="citizen_find_supplies.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-search"></i> Find Available Supplies
                            </a>
                            <a href="#" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                                <i class="fas fa-edit"></i> Update Profile Information
                            </a>
                        </div>
                        
                        <div class="alert-item alert-info mt-3">
                            <i class="fas fa-shield-virus"></i> Thank you for using the Pandemic Resilience System.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Profile Modal (placeholder - would need backend processing) -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateProfileModalLabel">Update Profile Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="update_profile.php" method="post" id="updateProfileForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password (required)</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password (leave empty to keep current)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="updateProfileForm" class="btn btn-primary">Save Changes</button>
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
            
            // Password confirmation validation
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const updateForm = document.getElementById('updateProfileForm');
            
            updateForm.addEventListener('submit', function(event) {
                if (newPasswordInput.value && newPasswordInput.value !== confirmPasswordInput.value) {
                    event.preventDefault();
                    alert('New passwords do not match. Please try again.');
                }
            });
        });
    </script>
</body>
</html>