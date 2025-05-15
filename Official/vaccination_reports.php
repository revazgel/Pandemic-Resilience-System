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
    
    // Get vaccination statistics
    $stats = [];
    
    // Total vaccinations
    $stmt = $pdo->query("SELECT COUNT(*) FROM vaccination_records");
    $stats['total'] = $stmt->fetchColumn();
    
    // Verified vaccinations
    $stmt = $pdo->query("SELECT COUNT(*) FROM vaccination_records WHERE verified = 1");
    $stats['verified'] = $stmt->fetchColumn();
    
    // Pending vaccinations
    $stats['pending'] = $stats['total'] - $stats['verified'];
    
    // Vaccinations by vaccine type
    $stmt = $pdo->query("
        SELECT v.vaccine_name, COUNT(*) as count
        FROM vaccination_records vr
        JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
        GROUP BY v.vaccine_id
    ");
    $vaccine_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Reports - PRS</title>
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
            <h2><i class="fas fa-chart-bar"></i> Vaccination Reports</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-syringe fa-3x text-primary mb-3"></i>
                        <h3><?php echo number_format($stats['total']); ?></h3>
                        <p class="text-muted">Total Vaccinations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h3><?php echo number_format($stats['verified']); ?></h3>
                        <p class="text-muted">Verified Records</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                        <h3><?php echo number_format($stats['pending']); ?></h3>
                        <p class="text-muted">Pending Verification</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie"></i> Vaccinations by Type</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Vaccine Type</th>
                                <th>Number of Doses</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vaccine_stats as $vaccine): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></td>
                                    <td><?php echo number_format($vaccine['count']); ?></td>
                                    <td><?php echo number_format(($vaccine['count'] / $stats['total']) * 100, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>