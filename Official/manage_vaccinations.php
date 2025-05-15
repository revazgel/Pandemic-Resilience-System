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
    
    // Get vaccination records
    $stmt = $pdo->query("
        SELECT vr.*, u.full_name, u.prs_id, v.vaccine_name
        FROM vaccination_records vr
        JOIN users u ON vr.user_id = u.user_id
        JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
        ORDER BY vr.vaccination_date DESC
        LIMIT 100
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vaccination Records - PRS</title>
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
            <h2><i class="fas fa-syringe"></i> Manage Vaccination Records</h2>
            <div>
                <a href="dashboard_official.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Citizen</th>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Date</th>
                                <th>Provider</th>
                                <th>Verified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($record['full_name']); ?>
                                        <div class="small text-muted">PRS-ID: <?php echo htmlspecialchars($record['prs_id']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['vaccine_name']); ?></td>
                                    <td><?php echo $record['dose_number']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($record['vaccination_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['healthcare_provider']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $record['verified'] ? 'success' : 'warning'; ?>">
                                            <?php echo $record['verified'] ? 'Verified' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$record['verified']): ?>
                                            <button class="btn btn-sm btn-success" onclick="verifyRecord(<?php echo $record['record_id']; ?>)">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verifyRecord(recordId) {
            if (confirm('Verify this vaccination record?')) {
                // Add verification logic here
                window.location.reload();
            }
        }
    </script>
</body>
</html>